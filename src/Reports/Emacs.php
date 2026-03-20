<?php

declare (strict_types=1);
/**
 * Emacs report for PHP_CodeSniffer.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Reports;

use Php_code_Sniffer\Files\File;
class Emacs implements Report
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
        if ($report['errors'] === 0 && $report['warnings'] === 0) {
            // Nothing to print.
            return false;
        }
        foreach ($report['messages'] as $line => $line_errors) {
            foreach ($line_errors as $column => $col_errors) {
                foreach ($col_errors as $error) {
                    $message = $error['message'];
                    if ($show_sources === true) {
                        $message .= ' (' . $error['source'] . ')';
                    }
                    $type = strtolower($error['type']);
                    echo $report['filename'] . ':' . $line . ':' . $column . ': ' . $type . ' - ' . $message . PHP_EOL;
                }
            }
        }
        return true;
    }
    //end generateFileReport()
    /**
     * Generates an emacs report.
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
    }
    //end generate()
}
//end class