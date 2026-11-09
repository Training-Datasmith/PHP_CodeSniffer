#!/usr/bin/env php
<?php

declare(strict_types=1);
/**
 * Fail if legacy PHP_CodeSniffer repo namespaces or PHPCS class_alias shims remain.
 */

$root = dirname(__DIR__);

$paths = [
    $root.'/autoload.php',
    $root.'/tests/bootstrap.php',
];

$scanRoots = [
    $root.'/src',
    $root.'/tests',
    $root.'/bin',
    $root.'/scripts',
    $root.'/examples',
];

$legacyPatterns = [
    '/\bnamespace\s+PHP_CodeSniffer\b/',
    '/\buse\s+PHP_CodeSniffer\\\\/',
    '/\\\\PHP_CodeSniffer\\\\/',
    "/'PHP_CodeSniffer\\\\/",
];

$failed = false;

foreach ($paths as $path) {
    $contents = file_get_contents($path);
    if ($path === $root.'/tests/bootstrap.php') {
        if (preg_match('/class_alias\s*\([^;]*(?:Php_code_Sniffer|PHP_CodeSniffer)/', $contents) === 1) {
            fwrite(STDERR, "check-rename-consistency: PHPCS class_alias shim in {$path}\n");
            $failed = true;
        }
        continue;
    }

    if (strpos($contents, 'class_alias') !== false) {
        fwrite(STDERR, "check-rename-consistency: class_alias in {$path}\n");
        $failed = true;
    }
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

/** @var SplFileInfo $file */
foreach ($iterator as $file) {
    if ($file->isDir()) {
        continue;
    }

    $pathname = $file->getPathname();
    if (strpos($pathname, $root.'/vendor/') === 0
        || strpos($pathname, $root.'/.system-test/') === 0
    ) {
        continue;
    }

    $ext = $file->getExtension();
    if (!in_array($ext, ['php', 'inc', 'xml'], true)) {
        continue;
    }

    $inScope = false;
    foreach ($scanRoots as $scanRoot) {
        if (strpos($pathname, $scanRoot.'/') === 0 || dirname($pathname) === $scanRoot) {
            $inScope = true;
            break;
        }
    }
    if ($pathname === $root.'/scripts/check-rename-consistency.php') {
        continue;
    }

    if ($inScope === false && basename($pathname) !== 'autoload.php') {
        continue;
    }

    $lines = file($pathname);
    if ($lines === false) {
        continue;
    }

    foreach ($lines as $lineNum => $line) {
        if (strpos($line, 'squizlabs/PHP_CodeSniffer') !== false) {
            continue;
        }
        if (preg_match('/@package\s+PHP_CodeSniffer/', $line) === 1) {
            continue;
        }

        foreach ($legacyPatterns as $pattern) {
            if (preg_match($pattern, $line) === 1) {
                fwrite(STDERR, sprintf(
                    "check-rename-consistency: legacy name in %s:%d: %s",
                    str_replace($root.'/', '', $pathname),
                    $lineNum + 1,
                    trim($line)."\n"
                ));
                $failed = true;
                break;
            }
        }
    }
}

if ($failed) {
    exit(1);
}

fwrite(STDOUT, "check-rename-consistency: OK\n");
exit(0);
