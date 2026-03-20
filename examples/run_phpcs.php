<?php

declare(strict_types=1);

/**
 * PHP_CodeSniffer — running PHPCS programmatically example.
 *
 * Demonstrates using the PHPCS API from PHP code.
 *
 * --- CLI usage (most common) ---
 *
 * # Check a file against PSR-12
 * vendor/bin/phpcs --standard=PSR12 src/
 *
 * # Auto-fix violations
 * vendor/bin/phpcbf --standard=PSR12 src/
 *
 * # Generate a checkstyle XML report
 * vendor/bin/phpcs --report=checkstyle --standard=PSR12 src/ > phpcs.xml
 *
 * # Ignore specific rules
 * vendor/bin/phpcs --standard=PSR12 --exclude=Generic.Files.LineLength src/
 *
 * --- PHP API usage ---
 *
 * use PHP_CodeSniffer\Config;
 * use PHP_CodeSniffer\Runner;
 *
 * // Set up configuration
 * $config = new Config(['--standard=PSR12', 'src/']);
 * $config->reports = ['summary' => null];
 *
 * $runner = new Runner();
 * $runner->config = $config;
 * $runner->init();
 * $exitCode = $runner->run();
 * // $exitCode === 0: no violations found
 * // $exitCode === 1: violations found
 * // $exitCode === 2: fixable violations found (phpcbf)
 *
 * --- Creating a custom sniff ---
 *
 * namespace MyStandard\Sniffs\Commenting;
 *
 * use PHP_CodeSniffer\Files\File;
 * use PHP_CodeSniffer\Sniffs\Sniff;
 *
 * class RequireFileHeaderSniff implements Sniff
 * {
 *     public function register(): array
 *     {
 *         return [T_OPEN_TAG];
 *     }
 *
 *     public function process(File $phpcsFile, int $stackPtr): void
 *     {
 *         $tokens    = $phpcsFile->getTokens();
 *         $nextToken = $phpcsFile->findNext(T_COMMENT, $stackPtr + 1);
 *
 *         if ($nextToken === false || $tokens[$nextToken]['line'] !== 3) {
 *             $phpcsFile->addError(
 *                 'File must have a file-level docblock starting at line 3',
 *                 $stackPtr,
 *                 'MissingFileHeader'
 *             );
 *         }
 *     }
 * }
 *
 * --- Register the sniff in a ruleset.xml ---
 *
 * <?xml version="1.0"?>
 * <ruleset name="MyStandard">
 *     <rule ref="PSR12"/>
 *     <rule ref="MyStandard.Commenting.RequireFileHeader"/>
 * </ruleset>
 */

echo 'PHP_CodeSniffer is a CLI tool. See the docblock above for usage.' . PHP_EOL;
echo 'Run: vendor/bin/phpcs --standard=PSR12 src/' . PHP_EOL;
