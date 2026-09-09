<?php
// Render the "Sieving now" band on the front page from the JSON file that
// html/cron/update_sieve_status.php writes.
//
// Install as html/user/sieve_status.inc.
//
// Nothing here touches the database. The front page is the master URL, so it
// reads a file and nothing more.
//
// Degrades in three stages, because the master page must never break:
//   file present and fresh  -> numbers, sizes and progress bars
//   file present but stale  -> same, with the age called out
//   file missing or broken  -> a plain list of links to the detail pages

// Resolved against this file's own directory, not the process working
// directory. Apache and php-fpm do not guarantee cwd is html/user, and a
// bare relative path silently fails there while working fine from the
// command line.
if (!defined('SIEVE_STATUS_FILE')) {
    define('SIEVE_STATUS_FILE', dirname(__FILE__).'/sieve_status.json');
}

// Older than this and the band says so rather than implying the numbers are
// current. Set a little above your cron interval.
if (!defined('SIEVE_STALE_AFTER')) {
    define('SIEVE_STALE_AFTER', 3600);      // one hour
}

// Used only when the JSON file cannot be read at all.
if (!isset($sieve_fallback_apps)) {
    $sieve_fallback_apps = array(
        array('app' => 'lasieved',       'url' => 'crunching.php'),
        array('app' => 'lasievee_small', 'url' => 'crunching_es.php'),
        array('app' => 'lasievee',       'url' => 'crunching_e.php'),
        array('app' => 'lasievef_small', 'url' => 'crunching_fs.php'),
        array('app' => 'lasievef',       'url' => 'crunching_myf.php'),
        array('app' => 'cudasieve',      'url' => 'crunching_cs.php'),
    );
}

function sieve_h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// Returns the decoded structure, or null if it cannot be trusted.
//
function sieve_status_read($path = SIEVE_STATUS_FILE) {
    if (!is_readable($path)) {
        return null;
    }
    $raw = @file_get_contents($path);
    if ($raw === false || $raw === '') {
        return null;
    }
    $data = json_decode($raw, true);
    if (!is_array($data) || !isset($data['apps']) || !is_array($data['apps'])) {
        return null;
    }
    if (!isset($data['generated'])) {
        $data['generated'] = 0;
    }
    return $data;
}

function sieve_age_str($generated) {
    $age = time() - (int)$generated;
    if ($age < 0)    return 'just now';
    if ($age < 120)  return 'a moment ago';
    if ($age < 5400) return round($age / 60).' minutes ago';
    if ($age < 172800) return round($age / 3600).' hours ago';
    return round($age / 86400).' days ago';
}

// One <tr> per number. The app name is repeated rather than row-spanned so
// the table stays flat and the mobile stylesheet can reflow it.
//
function sieve_row($app, $url, $number, $show_app) {
    echo '<tr>';
    echo '<th scope="row" class="app-name">';
    if ($show_app) {
        echo '<a href="'.sieve_h($url).'">'.sieve_h($app).'</a>';
    } else {
        echo '<span class="sr-only">'.sieve_h($app).'</span>';
    }
    echo "</th>\n";

    echo '<td class="num">'.sieve_h($number['name']).'</td>';

    // The Type column, as crunching.php shows it, except joined with a hyphen
    // rather than brackets: snfs-240 instead of snfs(240). .nobr keeps the
    // hyphen from becoming a line break in the narrow mobile column.
    $type = isset($number['type']) ? trim((string)$number['type']) : '';
    $diff = isset($number['difficulty']) ? trim((string)$number['difficulty']) : '';
    if ($type === '' && $diff === '') {
        $label = '&mdash;';
    } else if ($diff === '') {
        $label = sieve_h($type);
    } else if ($type === '') {
        $label = sieve_h($diff);
    } else {
        $label = sieve_h($type).'-'.sieve_h($diff);
    }
    echo '<td class="digits nobr">'.$label."</td>\n";

    echo '<td>';
    $pct = isset($number['percent']) ? $number['percent'] : null;
    if ($pct === null) {
        // crunching.php calls a number with no Q range "(manual)".
        echo '<span class="digits">manual range</span>';
    } else {
        $p = max(0, min(100, (float)$pct));
        echo '<div class="bar">';
        echo '<span class="bar-track" aria-hidden="true">';
        echo '<span class="bar-fill" style="width:'.round($p, 1).'%"></span></span>';
        echo '<span class="bar-pct">'.round($p).'%</span>';
        echo '</div>';
    }
    echo "</td>\n</tr>\n";
}

// The whole band, heading included.
//
function show_sieve_band($path = SIEVE_STATUS_FILE) {
    global $sieve_fallback_apps;

    $data = sieve_status_read($path);
    echo '<section class="sieve" aria-labelledby="sieve-h">'."\n";
    echo '<div class="container-fluid">'."\n";
    echo '<div class="sieve-head"><h2 id="sieve-h">Sieving now</h2>'."\n";

    if ($data === null) {
        // No usable file. Say nothing about freshness and just offer the links.
        echo '</div>'."\n";
        echo '<p class="sieve-line">Current status by application: ';
        $bits = array();
        $fallback = is_array($sieve_fallback_apps) ? $sieve_fallback_apps : array();
        foreach ($fallback as $a) {
            $bits[] = '<a href="'.sieve_h($a['url']).'">'.sieve_h($a['app']).'</a>';
        }
        echo implode(', ', $bits).'.</p>'."\n";
        echo '<p class="sieve-line"><a href="server_status.php">Server status</a></p>'."\n";
        echo '</div></section>'."\n";
        return;
    }

    $stale = (time() - (int)$data['generated']) > SIEVE_STALE_AFTER;
    echo '<p class="sieve-note">';
    if ($stale) {
        echo 'Last updated '.sieve_h(sieve_age_str($data['generated'])).
             ' &mdash; this may be out of date';
    } else {
        echo 'Updated '.sieve_h(sieve_age_str($data['generated']));
    }
    echo '</p></div>'."\n";

    echo '<div class="table-responsive" role="region" tabindex="0" aria-labelledby="sieve-h">'."\n";
    echo '<table>'."\n";
    echo '<caption>Each application name links to its detailed status page.</caption>'."\n";
    echo "<thead><tr>\n";
    echo '<th scope="col">Application</th>';
    echo '<th scope="col">Number</th>';
    echo '<th scope="col">Type</th>';
    echo '<th scope="col">Sieving progress</th>';
    echo "\n</tr></thead>\n<tbody>\n";

    $any = false;
    foreach ($data['apps'] as $a) {
        $app = isset($a['app']) ? $a['app'] : '';
        $url = isset($a['url']) ? $a['url'] : '';
        $numbers = (isset($a['numbers']) && is_array($a['numbers'])) ? $a['numbers'] : array();
        $total = isset($a['total']) ? (int)$a['total'] : count($numbers);

        if (!$numbers) {
            // Idle or errored: keep the app visible and linked so the row is
            // still a route to the detail page.
            echo '<tr><th scope="row" class="app-name">';
            echo '<a href="'.sieve_h($url).'">'.sieve_h($app).'</a></th>';
            echo '<td class="digits" colspan="3">';
            echo isset($a['error']) ? 'status unavailable' : 'nothing sieving right now';
            echo "</td></tr>\n";
            continue;
        }

        $any = true;
        $first = true;
        foreach ($numbers as $n) {
            if (!isset($n['name'])) continue;
            sieve_row($app, $url, $n, $first);
            $first = false;
        }

        $shown = count($numbers);
        if ($total > $shown) {
            $more = $total - $shown;
            echo '<tr><th scope="row" class="app-name">';
            echo '<span class="sr-only">'.sieve_h($app).'</span></th>';
            echo '<td class="digits" colspan="3"><a href="'.sieve_h($url).'">';
            echo 'and '.$more.' more '.($more == 1 ? 'number' : 'numbers');
            echo ' under '.sieve_h($app).'</a></td></tr>'."\n";
        }
    }

    if (!$any) {
        echo '<tr><td class="digits" colspan="4">';
        echo 'No sieving jobs are active. See the detail pages for queued work.';
        echo "</td></tr>\n";
    }

    echo "</tbody>\n</table>\n</div>\n";
    echo '<p class="sieve-line"><a href="server_status.php">Server status</a></p>'."\n";
    echo '</div></section>'."\n";
}

?>
