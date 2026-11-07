<?php

// soft_cancel.php
//
// Soft-cancel BOINC workunits by name prefix.
//
// Behavior:
//
//   * Workunits that have never been sent, or otherwise have no
//     IN_PROGRESS or SUCCESS result:
//         - cancel any UNSENT results
//         - set WU_ERROR_CANCELLED
//
//   * Workunits that have an IN_PROGRESS or SUCCESS result:
//         - leave the WU error_mask alone
//         - cancel any UNSENT results
//         - set max_total_results to the number of result rows that
//           already exist
//
//     This allows already-issued work to complete, validate, and
//     receive credit, but prevents BOINC from creating replacements
//     if those results later fail or time out.
//
// Only unresolved, error-free workunits are modified:
//     canonical_resultid = 0
//     error_mask = 0
//
// For safety this script only operates on:
//     min_quorum = 1
//     target_nresults = 1
//

session_start();

require_once("../inc/util_ops.inc");

define('SOFT_CANCEL_MIN_PREFIX', 4);
define('SOFT_CANCEL_TOKEN_LIFETIME', 900);    // 15 minutes


function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}


// Return a LIKE pattern that treats %, _, and ! in the supplied
// prefix literally.  We use ! as the SQL LIKE escape character.
function make_like_pattern($db, $prefix) {
    $s = str_replace(
        array('!', '%', '_'),
        array('!!', '!%', '!_'),
        $prefix
    );

    $s = $db->base_escape_string($s);

    return $s . '%';
}


function must_query($db, $sql) {
    $r = $db->do_query($sql);

    if ($r === false || $r === null) {
        throw new Exception(
            "Database query failed: " . $db->base_error()
        );
    }

    return $r;
}


function query_one($db, $sql) {
    $r = must_query($db, $sql);
    $row = $r->fetch_assoc();
    $r->free();

    return $row;
}


function query_all($db, $sql) {
    $r = must_query($db, $sql);

    $rows = array();

    while ($row = $r->fetch_assoc()) {
        $rows[] = $row;
    }

    $r->free();

    return $rows;
}


function get_stats($db, $prefix) {
    $pattern = make_like_pattern($db, $prefix);

    $prefix_where =
        "w.name LIKE '$pattern' ESCAPE '!'";

    $eligible_where =
        "$prefix_where
         AND w.canonical_resultid = 0
         AND w.error_mask = 0";

    $row = query_one(
        $db,
        "
        SELECT COUNT(*) AS matching_wus
        FROM workunit w
        WHERE $prefix_where
        "
    );

    $matching_wus = (int)$row['matching_wus'];

    $row = query_one(
        $db,
        "
        SELECT
            COUNT(*) AS eligible_wus,

            COALESCE(
                SUM(
                    n_inprogress = 0
                    AND n_success = 0
                ),
                0
            ) AS cancel_wus,

            COALESCE(
                SUM(
                    n_inprogress > 0
                    OR n_success > 0
                ),
                0
            ) AS soft_wus,

            COALESCE(
                SUM(nresults = 0),
                0
            ) AS no_result_wus,

            COALESCE(
                SUM(n_unsent),
                0
            ) AS unsent_results,

            COALESCE(
                SUM(n_inprogress),
                0
            ) AS inprogress_results,

            COALESCE(
                SUM(n_success),
                0
            ) AS success_results,

            COALESCE(
                SUM(
                    min_quorum <> 1
                    OR target_nresults <> 1
                ),
                0
            ) AS incompatible_wus

        FROM (
            SELECT
                w.id,
                w.min_quorum,
                w.target_nresults,

                COUNT(r.id) AS nresults,

                COALESCE(
                    SUM(r.server_state = " .
                        RESULT_SERVER_STATE_UNSENT . "),
                    0
                ) AS n_unsent,

                COALESCE(
                    SUM(r.server_state = " .
                        RESULT_SERVER_STATE_IN_PROGRESS . "),
                    0
                ) AS n_inprogress,

                COALESCE(
                    SUM(
                        r.server_state = " .
                            RESULT_SERVER_STATE_OVER . "
                        AND r.outcome = " .
                            RESULT_OUTCOME_SUCCESS . "
                    ),
                    0
                ) AS n_success

            FROM workunit w

            LEFT JOIN result r
                ON r.workunitid = w.id

            WHERE $eligible_where

            GROUP BY
                w.id,
                w.min_quorum,
                w.target_nresults
        ) x
        "
    );

    $stats = array(
        'matching_wus'       => $matching_wus,
        'eligible_wus'       => (int)$row['eligible_wus'],
        'cancel_wus'         => (int)$row['cancel_wus'],
        'soft_wus'           => (int)$row['soft_wus'],
        'no_result_wus'      => (int)$row['no_result_wus'],
        'unsent_results'     => (int)$row['unsent_results'],
        'inprogress_results' => (int)$row['inprogress_results'],
        'success_results'    => (int)$row['success_results'],
        'incompatible_wus'   => (int)$row['incompatible_wus']
    );

    $stats['ignored_wus'] =
        $stats['matching_wus'] - $stats['eligible_wus'];

    $stats['quorum_rows'] = query_all(
        $db,
        "
        SELECT
            w.min_quorum,
            w.target_nresults,
            COUNT(*) AS n
        FROM workunit w
        WHERE $eligible_where
        GROUP BY
            w.min_quorum,
            w.target_nresults
        ORDER BY
            w.min_quorum,
            w.target_nresults
        "
    );

    return $stats;
}


function validate_prefix($prefix) {
    $prefix = trim($prefix);

    if (strlen($prefix) < SOFT_CANCEL_MIN_PREFIX) {
        throw new Exception(
            "The workunit prefix must be at least " .
            SOFT_CANCEL_MIN_PREFIX .
            " characters long."
        );
    }

    if (strlen($prefix) > 191) {
        throw new Exception(
            "The workunit prefix is too long."
        );
    }

    return $prefix;
}


function do_soft_cancel($db, $prefix) {
    $pattern = make_like_pattern($db, $prefix);
    $now = time();

    $transaction_started = false;

    try {
        must_query($db, "START TRANSACTION");
        $transaction_started = true;

        // Snapshot the currently eligible WUs and the number of result
        // records that currently exist for each one.
        //
        // InnoDB is used instead of MEMORY so this remains safe even
        // for a very large prefix.
        must_query(
            $db,
            "
            CREATE TEMPORARY TABLE soft_cancel_targets (
                id INT NOT NULL PRIMARY KEY,
                nresults INT NOT NULL
            ) ENGINE=InnoDB
            "
        );

        must_query(
            $db,
            "
            INSERT INTO soft_cancel_targets (id, nresults)
            SELECT
                w.id,
                COUNT(r.id)
            FROM workunit w
            LEFT JOIN result r
                ON r.workunitid = w.id
            WHERE
                w.name LIKE '$pattern' ESCAPE '!'
                AND w.canonical_resultid = 0
                AND w.error_mask = 0
            GROUP BY w.id
            "
        );

        $row = query_one(
            $db,
            "
            SELECT COUNT(*) AS n
            FROM soft_cancel_targets
            "
        );

        $target_wus = (int)$row['n'];

        // Re-check the quorum requirement at execution time rather
        // than trusting the earlier preview.
        $row = query_one(
            $db,
            "
            SELECT COUNT(*) AS n
            FROM soft_cancel_targets t
            JOIN workunit w
                ON w.id = t.id
            WHERE
                w.min_quorum <> 1
                OR w.target_nresults <> 1
            "
        );

        if ((int)$row['n'] != 0) {
            throw new Exception(
                "One or more matching workunits no longer have " .
                "min_quorum=1 and target_nresults=1. " .
                "No changes were committed."
            );
        }

        // First freeze the total result count.
        //
        // This is intentionally done BEFORE closing UNSENT results.
        // If an existing WU has:
        //
        //     old failed result
        //     current live result
        //
        // its max_total_results becomes 2, not 1.
        //
        // Therefore the current live task is not immediately made
        // invalid merely because another result already exists.
        must_query(
            $db,
            "
            UPDATE workunit w
            JOIN soft_cancel_targets t
                ON t.id = w.id
            SET
                w.max_total_results = t.nresults
            WHERE
                w.canonical_resultid = 0
                AND w.error_mask = 0
            "
        );

        // Close currently UNSENT results.
        //
        // Because these rows are updated inside this transaction,
        // they remain locked until COMMIT.  A scheduler attempting
        // to dispatch one concurrently must wait; after COMMIT its
        // guarded server_state=UNSENT update will no longer match.
        must_query(
            $db,
            "
            UPDATE result r
            JOIN soft_cancel_targets t
                ON t.id = r.workunitid
            JOIN workunit w
                ON w.id = r.workunitid
            SET
                r.server_state = " .
                    RESULT_SERVER_STATE_OVER . ",
                r.outcome = " .
                    RESULT_OUTCOME_DIDNT_NEED . "
            WHERE
                r.server_state = " .
                    RESULT_SERVER_STATE_UNSENT . "
                AND w.canonical_resultid = 0
                AND w.error_mask = 0
            "
        );

        $unsent_closed = $db->affected_rows();

        // Fully cancel WUs which have nothing worth preserving.
        //
        // IMPORTANT:
        // Do not set WU_ERROR_CANCELLED on a WU that has either:
        //
        //     - an IN_PROGRESS result
        //     - a SUCCESS result waiting for validation
        //
        // Those must remain error-free so the validator can still
        // grant credit.
        must_query(
            $db,
            "
            UPDATE workunit w
            JOIN soft_cancel_targets t
                ON t.id = w.id
            SET
                w.error_mask =
                    w.error_mask | " .
                    WU_ERROR_CANCELLED . ",
                w.transition_time = $now
            WHERE
                w.canonical_resultid = 0
                AND w.error_mask = 0

                AND NOT EXISTS (
                    SELECT 1
                    FROM result r
                    WHERE
                        r.workunitid = w.id
                        AND (
                            r.server_state = " .
                                RESULT_SERVER_STATE_IN_PROGRESS . "

                            OR (
                                r.server_state = " .
                                    RESULT_SERVER_STATE_OVER . "
                                AND r.outcome = " .
                                    RESULT_OUTCOME_SUCCESS . "
                            )
                        )
                )
            "
        );

        $fully_cancelled = $db->affected_rows();

        // Trigger the transitioner on the remaining soft-frozen WUs.
        // These still have error_mask=0 and therefore remain eligible
        // for ordinary validation/credit.
        must_query(
            $db,
            "
            UPDATE workunit w
            JOIN soft_cancel_targets t
                ON t.id = w.id
            SET
                w.transition_time = $now
            WHERE
                w.canonical_resultid = 0
                AND w.error_mask = 0
            "
        );

        $row = query_one(
            $db,
            "
            SELECT COUNT(*) AS n
            FROM soft_cancel_targets t
            JOIN workunit w
                ON w.id = t.id
            WHERE
                w.canonical_resultid = 0
                AND w.error_mask = 0
            "
        );

        $soft_frozen = (int)$row['n'];

        must_query($db, "COMMIT");
        $transaction_started = false;

        // No longer needed; temporary table would disappear when
        // the connection closes anyway.
        $db->do_query(
            "DROP TEMPORARY TABLE IF EXISTS soft_cancel_targets"
        );

        return array(
            'target_wus'       => $target_wus,
            'unsent_closed'    => $unsent_closed,
            'fully_cancelled'  => $fully_cancelled,
            'soft_frozen'      => $soft_frozen,
            'other'            =>
                $target_wus -
                $fully_cancelled -
                $soft_frozen
        );

    } catch (Throwable $e) {
        if ($transaction_started) {
            $db->do_query("ROLLBACK");
        }

        $db->do_query(
            "DROP TEMPORARY TABLE IF EXISTS soft_cancel_targets"
        );

        throw $e;
    }
}


$db = BoincDb::get();

$mode = isset($_POST['mode']) ? $_POST['mode'] : '';


// ----------------------------------------------------------------------
// Confirmation action
// ----------------------------------------------------------------------

if ($mode === 'confirm') {
    $token = isset($_POST['token']) ? $_POST['token'] : '';

    $saved = isset($_SESSION['soft_cancel_confirmation'])
        ? $_SESSION['soft_cancel_confirmation']
        : null;

    // Make confirmation one-shot.
    unset($_SESSION['soft_cancel_confirmation']);

    if (
        !$saved
        || !isset($saved['token'])
        || !isset($saved['prefix'])
        || !isset($saved['expires'])
        || time() > $saved['expires']
        || !hash_equals($saved['token'], $token)
    ) {
        admin_error_page(
            "The confirmation has expired or is invalid. " .
            "Please preview the operation again."
        );
    }

    try {
        $prefix = validate_prefix($saved['prefix']);
        $result = do_soft_cancel($db, $prefix);

    } catch (Throwable $e) {
        admin_error_page(
            "<b>Soft cancel failed.</b><p>" .
            h($e->getMessage())
        );
    }

    admin_page_head("Soft-cancel workunits");

    echo "<h3>Soft cancel completed</h3>";

    echo "<p>Prefix: <b>" . h($prefix) . "</b></p>";

    echo "<table class=\"table table-striped table-bordered\" " .
         "style=\"width:auto\">";

    echo "<tr>
        <th>Action</th>
        <th>Count</th>
    </tr>";

    echo "<tr>
        <td>Workunits selected</td>
        <td>" . number_format($result['target_wus']) . "</td>
    </tr>";

    echo "<tr>
        <td>UNSENT results closed as DIDNT_NEED</td>
        <td>" . number_format($result['unsent_closed']) . "</td>
    </tr>";

    echo "<tr>
        <td>Workunits fully canceled</td>
        <td>" . number_format($result['fully_cancelled']) . "</td>
    </tr>";

    echo "<tr>
        <td>Workunits left open for existing results</td>
        <td>" . number_format($result['soft_frozen']) . "</td>
    </tr>";

    if ($result['other']) {
        echo "<tr>
            <td>Workunits whose state changed during processing</td>
            <td>" . number_format($result['other']) . "</td>
        </tr>";
    }

    echo "</table>";

    echo "<p>
        Existing in-progress results were not canceled.
        They may still complete, validate, and receive credit.
        No new replacement results will be generated for the
        soft-frozen workunits.
    </p>";

    echo "<p>
        <a class=\"btn btn-primary\" href=\"soft_cancel.php\">
            Soft-cancel another prefix
        </a>
    </p>";

    admin_page_tail();
    exit;
}


// ----------------------------------------------------------------------
// Preview
// ----------------------------------------------------------------------

$prefix = '';

if ($mode === 'preview') {
    try {
        $prefix = isset($_POST['prefix'])
            ? validate_prefix($_POST['prefix'])
            : validate_prefix('');

        $stats = get_stats($db, $prefix);

    } catch (Throwable $e) {
        admin_page_head("Soft-cancel workunits");

        echo "<div class=\"alert alert-danger\">" .
            h($e->getMessage()) .
            "</div>";

        $mode = '';
    }
}


// ----------------------------------------------------------------------
// Page
// ----------------------------------------------------------------------

if ($mode !== 'preview') {
    admin_page_head("Soft-cancel workunits");
}

echo "
<p>
    This page retires excess work without canceling work that has
    already been issued to volunteers.
</p>

<p>
    Enter the beginning of the workunit name.  Percent and underscore
    characters are treated literally, not as SQL wildcards.
</p>
";

if ($mode === 'preview') {
    echo "<h3>Preview</h3>";

    echo "<p>
        Workunit prefix:
        <b>" . h($prefix) . "</b>
    </p>";

    echo "<table class=\"table table-striped table-bordered\" " .
         "style=\"width:auto\">";

    echo "<tr>
        <th>Category</th>
        <th>Count</th>
    </tr>";

    echo "<tr>
        <td>All workunits matching prefix</td>
        <td>" . number_format($stats['matching_wus']) . "</td>
    </tr>";

    echo "<tr>
        <td><b>Unresolved, error-free workunits to process</b></td>
        <td><b>" .
            number_format($stats['eligible_wus']) .
        "</b></td>
    </tr>";

    echo "<tr>
        <td>Will be completely canceled</td>
        <td>" .
            number_format($stats['cancel_wus']) .
        "</td>
    </tr>";

    echo "<tr>
        <td>Will remain open for existing work/results</td>
        <td>" .
            number_format($stats['soft_wus']) .
        "</td>
    </tr>";

    echo "<tr>
        <td>Workunits with no result row yet</td>
        <td>" .
            number_format($stats['no_result_wus']) .
        "</td>
    </tr>";

    echo "<tr>
        <td>UNSENT results that will be closed</td>
        <td>" .
            number_format($stats['unsent_results']) .
        "</td>
    </tr>";

    echo "<tr>
        <td>IN_PROGRESS results that will be preserved</td>
        <td>" .
            number_format($stats['inprogress_results']) .
        "</td>
    </tr>";

    echo "<tr>
        <td>Successful results awaiting completion/validation</td>
        <td>" .
            number_format($stats['success_results']) .
        "</td>
    </tr>";

    echo "<tr>
        <td>Already finalized/error workunits ignored</td>
        <td>" .
            number_format($stats['ignored_wus']) .
        "</td>
    </tr>";

    echo "</table>";

    echo "<h4>Quorum settings</h4>";

    echo "<table class=\"table table-striped table-bordered\" " .
         "style=\"width:auto\">";

    echo "<tr>
        <th>min_quorum</th>
        <th>target_nresults</th>
        <th>Workunits</th>
    </tr>";

    foreach ($stats['quorum_rows'] as $row) {
        echo "<tr>
            <td>" . h($row['min_quorum']) . "</td>
            <td>" . h($row['target_nresults']) . "</td>
            <td>" . number_format((int)$row['n']) . "</td>
        </tr>";
    }

    echo "</table>";

    if ($stats['eligible_wus'] == 0) {
        echo "
        <div class=\"alert alert-info\">
            There are no unresolved, error-free workunits matching
            this prefix.
        </div>
        ";

    } else if ($stats['incompatible_wus'] != 0) {
        echo "
        <div class=\"alert alert-danger\">
            <b>Operation blocked.</b><br>
            " . number_format($stats['incompatible_wus']) . "
            matching workunits do not have both
            <code>min_quorum=1</code> and
            <code>target_nresults=1</code>.
            This page will not modify them.
        </div>
        ";

    } else {
        $token = bin2hex(random_bytes(32));

        $_SESSION['soft_cancel_confirmation'] = array(
            'token'   => $token,
            'prefix'  => $prefix,
            'expires' => time() + SOFT_CANCEL_TOKEN_LIFETIME
        );

        echo "
        <div class=\"alert alert-warning\">
            <b>Please verify the counts above.</b><br>
            This will immediately stop the UNSENT work from being
            dispatched.  Existing IN_PROGRESS work will be allowed
            to finish normally, but will not be replaced if it fails
            or times out.
        </div>

        <form method=\"post\" action=\"soft_cancel.php\">
            <input type=\"hidden\" name=\"mode\" value=\"confirm\">
            <input type=\"hidden\" name=\"token\" value=\"" .
                h($token) . "\">

            <input
                class=\"btn btn-danger\"
                type=\"submit\"
                value=\"Confirm soft cancel\"
            >
        </form>
        ";
    }

    echo "<hr>";
}


echo "
<h3>Workunit prefix</h3>

<form method=\"post\" action=\"soft_cancel.php\">
    <input type=\"hidden\" name=\"mode\" value=\"preview\">

    <input
        type=\"text\"
        name=\"prefix\"
        size=\"40\"
        maxlength=\"191\"
        value=\"" . h($prefix) . "\"
        placeholder=\"7p6_392\"
        required
    >

    <input
        class=\"btn btn-primary\"
        type=\"submit\"
        value=\"Preview\"
    >
</form>
";

admin_page_tail();

?>
