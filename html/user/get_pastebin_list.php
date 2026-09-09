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

// Get all rows that have a pastebin ID
// $numbers = BoincNumber_fs::enum("pastebin IS NOT NULL");

$numbers = array_merge(
    BoincNumber::enum("pastebin IS NOT NULL"),
    BoincNumber_es::enum("pastebin IS NOT NULL"),
    BoincNumber_e::enum("pastebin IS NOT NULL"),
    BoincNumber_fs::enum("pastebin IS NOT NULL"),
    BoincNumber_f::enum("pastebin IS NOT NULL")
);

// Use associative array as a set to deduplicate
$missing = array();

foreach ($numbers as $number) {
    if (empty($number->pastebin)) {
        continue;
    }

    $pasteId = $number->pastebin;

    if (!is_valid_pastebin_id($pasteId)) {
        // Skip anything suspicious
        continue;
    }

    $localFile = pastebin_cache_abspath($pasteId);

    if (!is_file($localFile) || filesize($localFile) === 0) {
        $missing[$pasteId] = true;
    }
}

// Sort for stable output
$ids = array_keys($missing);
sort($ids, SORT_STRING);

// Write to file
$result = file_put_contents($outFile, implode("\n", $ids) . "\n");

if ($result === false) {
    fwrite(STDERR, "Failed to write to $outFile\n");
    exit(1);
}

echo "Wrote " . count($ids) . " IDs to <a href=\"todownload.txt\">todownload.txt</a>\n";
?>
