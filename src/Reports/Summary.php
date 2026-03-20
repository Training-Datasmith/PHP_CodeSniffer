<?php

declare (strict_types=1);
/**
 * Summary report for PHP_CodeSniffer.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Reports;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Util;
class Summary implements Report
{
    /**
     * Generate a partial report for a single processed file.
     *
     * Function should return TRUE if it printed or stored data about the file
     * and FALSE if it ignored the file. Returning TRUE indicates that the file and
     * its data should be counted in the grand totals.
     *
     * @param array                       $report      Prepared report data.
     * @param \PHP_CodeSniffer\Files\File $phpcsFile   The file being reported on.
     * @param bool                        $showSources Show sources?
     * @param int                         $width       Maximum allowed line width.
     *
     * @return bool
     */
    public function generate_file_report($report, File $phpcs_file, $show_sources = false, $width = 80)
    {
        if (PHP_CODESNIFFER_VERBOSITY === 0 && $report['errors'] === 0 && $report['warnings'] === 0) {
            // Nothing to print.
            return false;
        }
        echo $report['filename'] . '>>' . $report['errors'] . '>>' . $report['warnings'] . PHP_EOL;
        return true;
    }
    //end generateFileReport()
    /**
     * Generates a summary of errors and warnings for each file processed.
     *
     * @param string $cachedData    Any partial report data that was returned from
     *                              generateFileReport during the run.
     * @param int    $totalFiles    Total number of files processed during the run.
     * @param int    $totalErrors   Total number of errors found during the run.
     * @param int    $totalWarnings Total number of warnings found during the run.
     * @param int    $totalFixable  Total number of problems that can be fixed.
     * @param bool   $showSources   Show sources?
     * @param int    $width         Maximum allowed line width.
     * @param bool   $interactive   Are we running in interactive mode?
     * @param bool   $toScreen      Is the report being printed to screen?
     *
     * @return void
     */
    public function generate($cached_data, $total_files, $total_errors, $total_warnings, $total_fixable, $show_sources = false, $width = 80, $interactive = false, $to_screen = true)
    {
        $lines = explode(PHP_EOL, $cached_data);
        array_pop($lines);
        if (empty($lines) === true) {
            return;
        }
        $report_files = [];
        $max_length = 0;
        foreach ($lines as $line) {
            $parts = explode('>>', $line);
            $file_len = strlen($parts[0]);
            $report_files[$parts[0]] = ['errors' => $parts[1], 'warnings' => $parts[2], 'strlen' => $file_len];
            $max_length = max($max_length, $file_len);
        }
        uksort($report_files, function ($key_a, $key_b) {
            $path_parts_a = explode(DIRECTORY_SEPARATOR, $key_a);
            $path_parts_b = explode(DIRECTORY_SEPARATOR, $key_b);
            do {
                $part_a = array_shift($path_parts_a);
                $part_b = array_shift($path_parts_b);
            } while ($part_a === $part_b && empty($path_parts_a) === false && empty($path_parts_b) === false);
            if (empty($path_parts_a) === false && empty($path_parts_b) === true) {
                return 1;
            }
            if (empty($path_parts_a) === true && empty($path_parts_b) === false) {
                return -1;
            }
            return strcasecmp($part_a, $part_b);
        });
        $width = min($width, $max_length + 21);
        $width = max($width, 70);
        echo PHP_EOL . "\x1b[1m" . 'PHP CODE SNIFFER REPORT SUMMARY' . "\x1b[0m" . PHP_EOL;
        echo str_repeat('-', $width) . PHP_EOL;
        echo "\x1b[1m" . 'FILE' . str_repeat(' ', $width - 20) . 'ERRORS  WARNINGS' . "\x1b[0m" . PHP_EOL;
        echo str_repeat('-', $width) . PHP_EOL;
        foreach ($report_files as $file => $data) {
            $padding = $width - 18 - $data['strlen'];
            if ($padding < 0) {
                $file = '...' . substr($file, $padding * -1 + 3);
                $padding = 0;
            }
            echo $file . str_repeat(' ', $padding) . '  ';
            if ($data['errors'] !== 0) {
                echo "\x1b[31m" . $data['errors'] . "\x1b[0m";
                echo str_repeat(' ', 8 - strlen($data['errors']));
            } else {
                echo '0       ';
            }
            if ($data['warnings'] !== 0) {
                echo "\x1b[33m" . $data['warnings'] . "\x1b[0m";
            } else {
                echo '0';
            }
            echo PHP_EOL;
        }
        //end foreach
        echo str_repeat('-', $width) . PHP_EOL;
        echo "\x1b[1mA TOTAL OF {$total_errors} ERROR";
        if ($total_errors !== 1) {
            echo 'S';
        }
        echo ' AND ' . $total_warnings . ' WARNING';
        if ($total_warnings !== 1) {
            echo 'S';
        }
        echo ' WERE FOUND IN ' . $total_files . ' FILE';
        if ($total_files !== 1) {
            echo 'S';
        }
        echo "\x1b[0m";
        if ($total_fixable > 0) {
            echo PHP_EOL . str_repeat('-', $width) . PHP_EOL;
            echo "\x1b[1mPHPCBF CAN FIX {$total_fixable} OF THESE SNIFF VIOLATIONS AUTOMATICALLY\x1b[0m";
        }
        echo PHP_EOL . str_repeat('-', $width) . PHP_EOL . PHP_EOL;
        if ($to_screen === true && $interactive === false) {
            Util\Timing::print_run_time();
        }
    }
    //end generate()
}
//end class