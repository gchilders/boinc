<?php
require_once("../inc/boinc_db.inc");
require_once("../inc/boinc_db_rsals.inc");
require_once("../inc/util.inc");
require_once("../inc/translation.inc");

db_init();

function is_valid_pastebin_id($pasteId) {
    return is_string($pasteId) && preg_match('/^[A-Za-z0-9]{4,32}$/', $pasteId);
}

function pastebin_cache_abspath($pasteId) {
    return __DIR__ . "/pastebin/" . $pasteId;
}

$outFile = __DIR__ . "/todownload.txt";

// Query all tables
$numbers = array_merge(
    BoincNumber::enum("pastebin IS NOT NULL"),
    BoincNumber_es::enum("pastebin IS NOT NULL"),
    BoincNumber_e::enum("pastebin IS NOT NULL"),
    BoincNumber_fs::enum("pastebin IS NOT NULL"),
    BoincNumber_f::enum("pastebin IS NOT NULL")
);

// Use associative array to deduplicate by pastebin ID
$missing = array();

foreach ($numbers as $number) {
    if (empty($number->pastebin)) {
        continue;
    }

    $pasteId = $number->pastebin;

    if (!is_valid_pastebin_id($pasteId)) {
        continue;
    }

    $localFile = pastebin_cache_abspath($pasteId);

    if (!is_file($localFile) || filesize($localFile) === 0) {
        // Store name + id (first occurrence wins)
        if (!isset($missing[$pasteId])) {
            $name = isset($number->name) ? $number->name : '';
            $missing[$pasteId] = $name;
        }
    }
}

// Sort by pastebin ID for stable output
ksort($missing, SORT_STRING);

// Write to file: "name pastebin_id"
$lines = array();
foreach ($missing as $pasteId => $name) {
    $lines[] = $name . " " . $pasteId;
}

$result = file_put_contents($outFile, implode("\n", $lines) . "\n");

if ($result === false) {
    fwrite(STDERR, "Failed to write to $outFile\n");
    exit(1);
}

echo "Wrote " . count($lines) . " entries to <a href=\"todownload.txt\">todownload.txt</a>\n";
?>
