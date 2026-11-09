<?php

/**
 * Enforce allowed PHPUnit skips and summary hygiene.
 */

$logFile = $argv[1] ?? '';
$allowedFile = dirname(__DIR__).'/scripts/allowed-skipped-tests.txt';

if ($logFile === '' || !is_readable($logFile)) {
    fwrite(STDERR, "Usage: php scripts/check-phpunit-gate.php <phpunit-log>\n");
    exit(1);
}

$output = file_get_contents($logFile);
if ($output === false) {
    fwrite(STDERR, "Could not read {$logFile}\n");
    exit(1);
}

$output = preg_replace('/\x1b\[[0-9;]*[a-zA-Z]/', '', $output);
$output = preg_replace('/\x1b\][^\x07]*\x07/', '', $output);

$allowed = array();
if (is_readable($allowedFile)) {
    $lines = file($allowedFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        $allowed[$line] = true;
    }
}

$skipped = array();
$skipSection = $output;
if (preg_match('/There were [0-9]+ skipped tests:\s*/', $output, $heading, PREG_OFFSET_CAPTURE)) {
    $skipSection = substr($output, $heading[0][1] + strlen($heading[0][0]));
}

if (preg_match_all('/^\d+\) ([A-Za-z0-9_\\\\]+::[A-Za-z0-9_]+)\b/m', $skipSection, $matches)) {
    foreach ($matches[1] as $id) {
        $skipped[] = $id;
    }
}

$skipped = array_values(array_unique($skipped));

$failed = false;
foreach ($skipped as $id) {
    if (!isset($allowed[$id])) {
        fwrite(STDERR, "Disallowed skipped test: {$id}\n");
        $failed = true;
    }
}

foreach ($allowed as $id => $_) {
    if (!in_array($id, $skipped, true)) {
        fwrite(STDERR, "Allowlisted skip not seen in verbose output: {$id}\n");
        $failed = true;
    }
}

$summarySkipped = null;
if (preg_match('/^Tests: [0-9]+, Assertions: [0-9]+, Skipped: ([0-9]+)\.$/m', $output, $summaryMatch)) {
    $summarySkipped = (int) $summaryMatch[1];
}

if ($summarySkipped !== null && $summarySkipped !== count($skipped)) {
    fwrite(STDERR, sprintf(
        "Skipped count mismatch: summary says %d but verbose listing has %d.\n",
        $summarySkipped,
        count($skipped)
    ));
    $failed = true;
}

if (preg_match('/\bWarnings:\s+[1-9]/', $output)) {
    fwrite(STDERR, "PHPUnit summary reports Warnings.\n");
    $failed = true;
}
if (preg_match('/\bFailures:\s+[1-9]/', $output)) {
    fwrite(STDERR, "PHPUnit summary reports Failures.\n");
    $failed = true;
}
if (preg_match('/\bErrors:\s+[1-9]/', $output)) {
    fwrite(STDERR, "PHPUnit summary reports Errors.\n");
    $failed = true;
}
if (preg_match('/\bRisky:\s+[1-9]/', $output)) {
    fwrite(STDERR, "PHPUnit summary reports Risky.\n");
    $failed = true;
}
if (preg_match('/\bIncomplete:\s+[1-9]/', $output)) {
    fwrite(STDERR, "PHPUnit summary reports Incomplete.\n");
    $failed = true;
}

if ($failed) {
    exit(1);
}

fwrite(STDOUT, 'PHPUnit skip gate passed ('.count($skipped)." allowed skips).\n");
exit(0);
