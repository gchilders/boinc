#!/usr/bin/env php
<?php

$boinc = "/home/boincadm/projects/nfs";

if ($argc < 2) {
    fwrite(STDERR, "Usage: {$argv[0]} failed_workunits.txt [--dry-run]\n");
    exit(1);
}

$filename = $argv[1];
$dry_run = in_array("--dry-run", $argv, true);

if (!is_readable($filename)) {
    fwrite(STDERR, "Cannot read $filename\n");
    exit(1);
}

$lines = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

$count = 0;

foreach ($lines as $line) {

    /*
     * Accept either:
     *
     * C236_133_111_101000
     *
     * or MySQL output:
     *
     * | C236_133_111_101000 |
     */
    if (!preg_match('/C236_133_111_(\d+)/', $line, $matches)) {
        continue;
    }

    $suffix = (int)$matches[1];

    $old_wuname = "C236_133_111_" . $suffix;

    /*
     * The original WU still exists in the database, so create_work
     * needs a new unique workunit name.
     */
    $wuname = $old_wuname . "_r2";

    $q_first = $suffix * 1000;
    $q_last  = $q_first + 250000;

    $command  = "cd " . escapeshellarg($boinc) . " && bin/create_work ";
    $command .= "--batch 120 ";
    $command .= "--priority 120 ";
    $command .= "--appname cudasieve ";
    $command .= "--wu_name " . escapeshellarg($wuname) . " ";
    $command .= "--wu_template templates/la_wucs2 ";
    $command .= "--result_template templates/la_resultcs ";
    $command .= "--command_line " .
        escapeshellarg(
            "--pipeline --cofactor " .
            "--poly input.poly " .
            "--logI 15 " .
            "--relations output.dat " .
            "--sq-side 1 " .
            "--qrange $q_first:$q_last"
        ) . " ";
    $command .= "--rsc_memory_bound 800000000 ";
    $command .= "--credit 2800 ";
    $command .= "C236_133_111.poly";

    echo "$old_wuname -> $wuname  qrange=$q_first:$q_last\n";

    if ($dry_run) {
        echo "$command\n\n";
        continue;
    }

    passthru($command, $retval);

    if ($retval != 0) {
        fwrite(
            STDERR,
            "ERROR: create_work failed for $wuname (return code $retval)\n"
        );
        exit($retval);
    }

    $count++;
}

if ($dry_run) {
    echo "Dry run complete.\n";
} else {
    echo "Created $count replacement workunits.\n";
}

