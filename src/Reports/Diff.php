<?php

declare (strict_types=1);
/**
 * Diff report for PHP_CodeSniffer.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Reports;

use Php_code_Sniffer\Files\File;
class Diff implements Report
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
        $errors = $phpcs_file->get_fixable_count();
        if ($errors === 0) {
            return false;
        }
        $phpcs_file->disable_caching();
        $tokens = $phpcs_file->get_tokens();
        if (empty($tokens) === true) {
            if (PHP_CODESNIFFER_VERBOSITY === 1) {
                $start_time = microtime(true);
                echo 'DIFF report is parsing ' . basename($report['filename']) . ' ';
            } elseif (PHP_CODESNIFFER_VERBOSITY > 1) {
                echo 'DIFF report is forcing parse of ' . $report['filename'] . PHP_EOL;
            }
            $phpcs_file->parse();
            if (PHP_CODESNIFFER_VERBOSITY === 1) {
                $time_taken = (microtime(true) - $start_time) * 1000;
                if ($time_taken < 1000) {
                    $time_taken = round($time_taken);
                    echo "DONE in {$time_taken}ms";
                } else {
                    $time_taken = round($time_taken / 1000, 2);
                    echo "DONE in {$time_taken} secs";
                }
                echo PHP_EOL;
            }
            $phpcs_file->fixer->start_file($phpcs_file);
        }
        //end if
        if (PHP_CODESNIFFER_VERBOSITY > 1) {
            ob_end_clean();
            echo "\t*** START FILE FIXING ***" . PHP_EOL;
        }
        $fixed = $phpcs_file->fixer->fix_file();
        if (PHP_CODESNIFFER_VERBOSITY > 1) {
            echo "\t*** END FILE FIXING ***" . PHP_EOL;
            ob_start();
        }
        if ($fixed === false) {
            return false;
        }
        $diff = $phpcs_file->fixer->generate_diff();
        if ($diff === '') {
            // Nothing to print.
            return false;
        }
        echo $diff . PHP_EOL;
        return true;
    }
    //end generateFileReport()
    /**
     * Prints all errors and warnings for each file processed.
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
        echo $cached_data;
        if ($to_screen === true && $cached_data !== '') {
            echo PHP_EOL;
        }
    }
    //end generate()
}
//end class