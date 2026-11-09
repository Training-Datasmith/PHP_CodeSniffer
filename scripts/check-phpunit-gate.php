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
if (preg_match_all('/^There were (\d+) skipped tests?:$/m', $output, $countMatches)) {
    // PHPUnit may print detail blocks below.
}

if (preg_match_all('/^([^\s].*) skipped$/m', $output, $matches)) {
    foreach ($matches[1] as $line) {
        $line = trim($line);
        if (strpos($line, 'Skipped:') === 0) {
            continue;
        }
        if (strpos($line, 'There were') === 0) {
            continue;
        }
        $skipped[] = $line;
    }
}

// PHPUnit 9 text UI lists skips as "- Class::method with message"
if (preg_match_all('/^\- ([^\s]+)::([^\s]+) .*/m', $output, $dashMatches, PREG_SET_ORDER)) {
    foreach ($dashMatches as $match) {
        $skipped[] = $match[1].'::'.$match[2];
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

if (preg_match('/\bWarnings:\s+[1-9]/', $output)) {
    fwrite(STDERR, "PHPUnit summary reports Warnings.\n");
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
