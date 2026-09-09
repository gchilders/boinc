<?php
// Collect "what is being sieved right now" and write it to a JSON file for
// the front page to read.
//
// Install as html/cron/update_sieve_status.php and run it from crontab, e.g.
// every ten minutes:
//
//   */10 * * * * cd /home/boincadm/projects/nfs/html/cron && \
//                /usr/bin/php update_sieve_status.php >/dev/null 2>&1
//
// The point of doing this from cron rather than in index.php is that the front
// page is also the master URL. Every BOINC client, every stats site and every
// scraper hits it, so it should not be running five table scans per request.
// This writes a file; the front page reads a file.
//
// The write is atomic (temp file plus rename), and the destination is only
// replaced when every app queried cleanly. A stale file is much better than a
// half-written one, and index2.php degrades to a plain list of links if the
// file is missing entirely.

// Paths resolved against this script's own directory so the job works from
// any working directory. BOINC's own cron scripts use bare relative requires
// and assume the crontab does `cd html/cron` first; that assumption is easy
// to break when someone edits the crontab.
$nfs_here = dirname(__FILE__);

require_once($nfs_here."/../inc/util.inc");
require_once($nfs_here."/../project/project.inc");
require_once($nfs_here."/../inc/boinc_db.inc");

// Project-local models: BoincNumber, BoincNumber_e, BoincNumber_es,
// BoincNumber_fs, BoincNumber_f. This file is not in the git tree, it lives
// only on the server.
require_once($nfs_here."/../inc/boinc_db_rsals.inc");

// ---------------------------------------------------------------------------
// Configuration
// ---------------------------------------------------------------------------

// Where to write. Sitting in html/user means it is also reachable at
// https://escatter11.fullerton.edu/nfs/sieve_status.json, which is a useful
// little public endpoint. Move it outside the web root if you would rather it
// were not.
// Resolved against this script's own directory, not the working directory.
// The crontab line does cd into html/cron first, but a bare relative path
// silently writes somewhere else if anyone runs it from elsewhere — and then
// the front page reads a stale file with no error anywhere.
define('SIEVE_STATUS_FILE', $nfs_here.'/../user/sieve_status.json');

// One entry per sieving app: display name, its detail page, and the model
// class that wraps its table. Order here is the order shown on the front page.
//
// Note that crunching_cs.php and BoincNumber_cs are not in the git branch,
// only on the server, so an `upgrade` will not bring them along. Same for
// boinc_db_rsals.inc itself.
$sieve_apps = array(
    array('app' => 'lasieved',       'url' => 'crunching.php',     'model' => 'BoincNumber'),
    array('app' => 'lasievee_small', 'url' => 'crunching_es.php',  'model' => 'BoincNumber_es'),
    array('app' => 'lasievee',       'url' => 'crunching_e.php',   'model' => 'BoincNumber_e'),
    array('app' => 'lasievef_small', 'url' => 'crunching_fs.php',  'model' => 'BoincNumber_fs'),
    array('app' => 'lasievef',       'url' => 'crunching_myf.php', 'model' => 'BoincNumber_f'),
    array('app' => 'cudasieve',      'url' => 'crunching_cs.php',  'model' => 'BoincNumber_cs'),
);

// STATUS_SIEVING is status=1 in the number tables:
//   0 queued   1 now sieving   2 queued for post-processing
//   3 post-processing   4 completed
define('STATUS_SIEVING', 1);

// How many numbers to list per app before collapsing to "and N more".
define('SIEVE_ROWS_PER_APP', 3);

// ---------------------------------------------------------------------------

// The same calculation crunching.php labels "Pushed": how far through the Q
// range work has been generated. Returns null when there is no range to
// measure, which crunching.php shows as "(manual)".
//
function sieve_percent($n) {
    $start = (float)$n->q_start;
    $end   = (float)$n->q_end;
    if ($end <= $start) {
        return null;
    }
    $last = (float)$n->q_last;
    if ($last == 0) {
        $last = $start;
    }
    $pct = 100.0 * ($last - $start) / ($end - $start);
    if ($pct < 0)   $pct = 0.0;
    if ($pct > 100) $pct = 100.0;
    return round($pct, 1);
}

function sieve_collect($apps) {
    $out = array();
    foreach ($apps as $a) {
        $entry = array(
            'app'     => $a['app'],
            'url'     => $a['url'],
            'numbers' => array(),
            'total'   => 0,
        );

        if (!class_exists($a['model'])) {
            // Model missing on this server. Record it rather than dying, so
            // one absent app cannot take the whole file down.
            $entry['error'] = 'no model class '.$a['model'];
            $out[] = $entry;
            continue;
        }

        $rows = call_user_func(array($a['model'], 'enum'), 'status='.STATUS_SIEVING);
        if ($rows === false) {
            $entry['error'] = 'query failed';
            $out[] = $entry;
            continue;
        }
        if (!$rows) {
            $out[] = $entry;    // app idle: no numbers, not an error
            continue;
        }

        // enum() does not take an ORDER BY reliably, so sort here: furthest
        // along first, so the band leads with what is closest to finishing.
        $list = array();
        foreach ($rows as $n) {
            $list[] = array(
                'name'       => (string)$n->dispname,
                'project'    => isset($n->project) ? (string)$n->project : '',
                // crunching.php renders its Type column as
                //   "$number->type($number->difficulty)"
                // i.e. type immediately followed by difficulty in brackets.
                // Both parts are kept separate here so the front end can
                // format them however it likes.
                'type'       => isset($n->type) ? trim((string)$n->type) : '',
                'difficulty' => isset($n->difficulty) ? trim((string)$n->difficulty) : '',
                // primebits, the other difficulty measure. Not displayed on
                // the front page, but it is real data and costs nothing to
                // publish in the JSON.
                'bits'       => isset($n->primebits) ? (int)$n->primebits : 0,
                'percent'    => sieve_percent($n),
            );
        }
        usort($list, function ($x, $y) {
            $a = $x['percent'] === null ? -1 : $x['percent'];
            $b = $y['percent'] === null ? -1 : $y['percent'];
            if ($a == $b) return strcmp($x['name'], $y['name']);
            return ($b < $a) ? -1 : 1;
        });

        $entry['total']   = count($list);
        $entry['numbers'] = array_slice($list, 0, SIEVE_ROWS_PER_APP);
        $out[] = $entry;
    }
    return $out;
}

function sieve_write($path, $data) {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        fwrite(STDERR, "update_sieve_status: json_encode failed\n");
        return false;
    }
    $tmp = $path.'.tmp';
    if (file_put_contents($tmp, $json."\n") === false) {
        fwrite(STDERR, "update_sieve_status: cannot write $tmp\n");
        return false;
    }
    @chmod($tmp, 0644);
    if (!rename($tmp, $path)) {
        fwrite(STDERR, "update_sieve_status: cannot rename $tmp to $path\n");
        @unlink($tmp);
        return false;
    }
    return true;
}

// ---- main -----------------------------------------------------------------

db_init(true);      // read-only, so use the replica if there is one

$apps = sieve_collect($sieve_apps);

// Only publish if at least one app answered. If every single one errored,
// something is wrong with the DB and the existing file is better than an
// empty one.
$ok = false;
foreach ($apps as $a) {
    if (!isset($a['error'])) { $ok = true; break; }
}
if (!$ok) {
    fwrite(STDERR, "update_sieve_status: every app failed, keeping previous file\n");
    exit(1);
}

$data = array(
    'generated' => time(),
    'apps'      => $apps,
);

if (!sieve_write(SIEVE_STATUS_FILE, $data)) {
    exit(1);
}

// Surface any partial failures in cron mail without failing the run.
foreach ($apps as $a) {
    if (isset($a['error'])) {
        fwrite(STDERR, "update_sieve_status: ".$a['app'].": ".$a['error']."\n");
    }
}

?>
