<?php
// expire_host_results.php
//
// Force all IN_PROGRESS results belonging to either:
//   1. one specific BOINC host ID, or
//   2. all hosts whose domain_name contains a supplied substring
// to time out now.
//
// The BOINC transitioner then performs the normal timeout handling:
// IN_PROGRESS -> OVER / NO_REPLY, followed by replacement result creation
// where the workunit still needs another result.
//
// Install in PROJECT_ROOT/html/ops/

require_once("../inc/util_ops.inc");

function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8");
}

function inprogress_state() {
    return defined("RESULT_SERVER_STATE_IN_PROGRESS")
        ? RESULT_SERVER_STATE_IN_PROGRESS
        : 4;
}

function fmt_time($t) {
    $t = (int)$t;
    if ($t <= 0) return "never";
    return date("Y-m-d H:i:s T", $t);
}

function positive_int($s) {
    $s = trim((string)$s);
    if ($s === "" || !ctype_digit($s)) return 0;
    $v = (int)$s;
    return $v > 0 ? $v : 0;
}

function parse_hostids($s) {
    $out = array();
    foreach (explode(",", (string)$s) as $x) {
        $x = trim($x);
        if ($x === "" || !ctype_digit($x)) continue;
        $id = (int)$x;
        if ($id > 0) $out[$id] = $id;
    }
    return array_values($out);
}

function query_hosts_with_counts($where_clause) {
    $db = BoincDb::get();
    $state = inprogress_state();

    $q = "
        SELECT
            h.id,
            h.domain_name,
            h.p_model,
            h.rpc_time,
            COUNT(r.id) AS nresults,
            COUNT(DISTINCT r.workunitid) AS nworkunits
        FROM host h
        LEFT JOIN result r
            ON r.hostid = h.id
           AND r.server_state = $state
        WHERE $where_clause
        GROUP BY
            h.id,
            h.domain_name,
            h.p_model,
            h.rpc_time
        ORDER BY h.domain_name, h.id
    ";

    $r = $db->do_query($q);
    if (!$r) {
        admin_error_page("Database query failed while finding hosts: ".h(BoincDb::error()));
    }

    $rows = array();
    while ($row = $r->fetch_object()) {
        $rows[] = $row;
    }
    $r->free();
    return $rows;
}

function find_host_by_id($hostid) {
    return query_hosts_with_counts("h.id=".(int)$hostid);
}

function find_hosts_by_domain_substring($domain) {
    // LOCATE() gives literal substring semantics, so '%' and '_' in the
    // search text are not treated as SQL LIKE wildcards.
    $needle = BoincDb::escape_string(trim($domain));
    return query_hosts_with_counts("LOCATE('$needle', h.domain_name) > 0");
}

function show_form($hostid_value = "", $domain_value = "") {
    echo '
        <p>
        Enter either a BOINC host ID, or part of a host domain name.
        The matching host(s) and outstanding work will be shown before
        anything is changed.
        </p>

        <form method="post" action="">
            <div class="form-group" style="max-width:400px">
                <label for="hostid">Host ID</label>
                <input
                    class="form-control"
                    id="hostid"
                    name="hostid"
                    type="text"
                    inputmode="numeric"
                    value="'.h($hostid_value).'"
                >
            </div>

            <p><strong>or</strong></p>

            <div class="form-group" style="max-width:500px">
                <label for="domain">Domain name contains</label>
                <input
                    class="form-control"
                    id="domain"
                    name="domain"
                    type="text"
                    value="'.h($domain_value).'"
                    placeholder="e.g. a100-2u or cluster.example.edu"
                >
            </div>

            <input type="hidden" name="action" value="preview">
            <button class="btn btn-primary" type="submit">Preview results</button>
        </form>
    ';
}

function show_preview($rows, $description) {
    if (!$rows) {
        echo '<div class="alert alert-info">No matching hosts were found.</div>';
        echo '<p><a class="btn btn-default" href="'.h($_SERVER["PHP_SELF"]).'">Back</a></p>';
        return;
    }

    $total_results = 0;
    $total_workunits = 0;
    $eligible_hostids = array();

    echo "<h3>".h($description)."</h3>";

    echo '
        <table class="table table-striped table-condensed">
        <tr>
            <th>Host ID</th>
            <th>Domain name</th>
            <th>CPU</th>
            <th>Last RPC</th>
            <th>Results to expire</th>
            <th>Workunits affected</th>
        </tr>
    ';

    foreach ($rows as $row) {
        $nresults = (int)$row->nresults;
        $nworkunits = (int)$row->nworkunits;

        $total_results += $nresults;
        $total_workunits += $nworkunits;

        // Freeze only hosts that actually had outstanding work on the
        // confirmation page.  A zero-work host that receives a task after
        // preview therefore won't be unexpectedly included.
        if ($nresults > 0) {
            $eligible_hostids[] = (int)$row->id;
        }

        echo "<tr>";
        echo "<td>".h($row->id)."</td>";
        echo "<td>".h($row->domain_name)."</td>";
        echo "<td>".h($row->p_model)."</td>";
        echo "<td>".h(fmt_time($row->rpc_time))."</td>";
        echo "<td>".h($nresults)."</td>";
        echo "<td>".h($nworkunits)."</td>";
        echo "</tr>";
    }

    echo "</table>";

    echo '<p><strong>'.h(count($rows)).'</strong> matching host(s); '
        .'<strong>'.h($total_results).'</strong> in-progress result(s) across '
        .'<strong>'.h($total_workunits).'</strong> workunit(s).</p>';

    if ($total_results === 0) {
        echo '<div class="alert alert-info">None of the matching hosts currently has IN_PROGRESS results.</div>';
        echo '<p><a class="btn btn-default" href="'.h($_SERVER["PHP_SELF"]).'">Back</a></p>';
        return;
    }

    echo '
        <div class="alert alert-warning" style="max-width:1000px">
            <strong>Confirm:</strong>
            this will force the '.h($total_results).' currently IN_PROGRESS
            result(s) shown above to expire.  The BOINC transitioner will
            process them as ordinary timeouts and generate replacement
            results where needed.
        </div>

        <form method="post" action="" style="display:inline-block">
            <input type="hidden" name="action" value="expire">
            <input type="hidden" name="hostids" value="'.h(implode(",", $eligible_hostids)).'">
            <button class="btn btn-danger" type="submit">
                Expire '.h($total_results).' result(s) now
            </button>
        </form>
        &nbsp;
        <a class="btn btn-default" href="'.h($_SERVER["PHP_SELF"]).'">Cancel</a>
    ';
}

function expire_host_results($hostids) {
    if (!$hostids) {
        admin_error_page("No valid host IDs were supplied.");
    }

    $db = BoincDb::get();
    $state = inprogress_state();
    $deadline = time() - 1;
    $id_list = implode(",", array_map("intval", $hostids));

    if (!$db->do_query("START TRANSACTION")) {
        admin_error_page("Could not start database transaction: ".h(BoincDb::error()));
    }

    // Re-count the exact frozen set of host IDs at confirmation time.
    $q = "
        SELECT
            COUNT(*) AS nresults,
            COUNT(DISTINCT workunitid) AS nworkunits,
            COUNT(DISTINCT hostid) AS nhosts
        FROM result
        WHERE hostid IN ($id_list)
          AND server_state = $state
    ";

    $r = $db->do_query($q);
    if (!$r) {
        $err = BoincDb::error();
        $db->do_query("ROLLBACK");
        admin_error_page("Could not count results: ".h($err));
    }

    $stats = $r->fetch_object();
    $r->free();

    if (!(int)$stats->nresults) {
        $db->do_query("ROLLBACK");
        echo '<div class="alert alert-info">Nothing was changed.  These hosts no longer have any IN_PROGRESS results.</div>';
        echo '<p><a class="btn btn-default" href="'.h($_SERVER["PHP_SELF"]).'">Back</a></p>';
        return;
    }

    // Put the deadlines in the past, but don't directly alter server_state
    // or outcome.  The transitioner should perform those normal state changes.
    $q = "
        UPDATE result
        SET report_deadline = LEAST(report_deadline, $deadline)
        WHERE hostid IN ($id_list)
          AND server_state = $state
    ";

    if (!$db->do_query($q)) {
        $err = BoincDb::error();
        $db->do_query("ROLLBACK");
        admin_error_page("Could not update result deadlines: ".h($err));
    }

    // Tell the transitioner to examine the affected workunits immediately.
    $q = "
        UPDATE workunit w
        INNER JOIN result r
            ON r.workunitid = w.id
        SET w.transition_time = 0
        WHERE r.hostid IN ($id_list)
          AND r.server_state = $state
    ";

    if (!$db->do_query($q)) {
        $err = BoincDb::error();
        $db->do_query("ROLLBACK");
        admin_error_page("Could not update workunit transition times: ".h($err));
    }

    if (!$db->do_query("COMMIT")) {
        admin_error_page("Database commit failed: ".h(BoincDb::error()));
    }

    echo '<div class="alert alert-success" style="max-width:1000px">';
    echo 'Forced <strong>'.h($stats->nresults).'</strong> in-progress result(s) '
        .'across <strong>'.h($stats->nworkunits).'</strong> workunit(s) on '
        .'<strong>'.h($stats->nhosts).'</strong> host(s) to expire.';
    echo '</div>';

    echo '
        <p>
        The transitioner will now process these as normal timeouts,
        marking the expired results <code>OVER / NO_REPLY</code> and
        creating replacement results where necessary.
        </p>

        <p>
        <a class="btn btn-default" href="'.h($_SERVER["PHP_SELF"]).'">
            Process another host or domain
        </a>
        </p>
    ';
}

// ----------------------------------------------------------------------
// Main
// ----------------------------------------------------------------------

admin_page_head("Expire outstanding results for host(s)");

$action = isset($_POST["action"]) ? $_POST["action"] : "";

if ($action === "") {
    show_form();

} elseif ($action === "preview") {
    $hostid_text = isset($_POST["hostid"]) ? trim($_POST["hostid"]) : "";
    $domain = isset($_POST["domain"]) ? trim($_POST["domain"]) : "";

    if ($hostid_text !== "" && $domain !== "") {
        echo '<div class="alert alert-danger">Enter either a host ID or a domain-name substring, not both.</div>';
        show_form($hostid_text, $domain);

    } elseif ($hostid_text !== "") {
        $hostid = positive_int($hostid_text);
        if (!$hostid) {
            echo '<div class="alert alert-danger">Enter a valid positive integer host ID.</div>';
            show_form($hostid_text, "");
        } else {
            $rows = find_host_by_id($hostid);
            show_preview($rows, "Host $hostid");
        }

    } elseif ($domain !== "") {
        $rows = find_hosts_by_domain_substring($domain);
        show_preview($rows, 'Hosts whose domain name contains "'.$domain.'"');

    } else {
        echo '<div class="alert alert-danger">Enter either a host ID or part of a domain name.</div>';
        show_form();
    }

} elseif ($action === "expire") {
    $hostids = parse_hostids(isset($_POST["hostids"]) ? $_POST["hostids"] : "");
    expire_host_results($hostids);

} else {
    admin_error_page("Invalid action.");
}

admin_page_tail();
?>
