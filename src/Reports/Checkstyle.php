<?php

declare (strict_types=1);
/**
 * Checkstyle report for PHP_CodeSniffer.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Reports;

use Php_code_Sniffer\Config;
use Php_code_Sniffer\Files\File;
class Checkstyle implements Report
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
        $out = new \Xml_Writer();
        $out->open_memory();
        $out->set_indent(true);
        if ($report['errors'] === 0 && $report['warnings'] === 0) {
            // Nothing to print.
            return false;
        }
        $out->start_element('file');
        $out->write_attribute('name', $report['filename']);
        foreach ($report['messages'] as $line => $line_errors) {
            foreach ($line_errors as $column => $col_errors) {
                foreach ($col_errors as $error) {
                    $error['type'] = strtolower($error['type']);
                    if ($phpcs_file->config->encoding !== 'utf-8') {
                        $error['message'] = iconv($phpcs_file->config->encoding, 'utf-8', $error['message']);
                    }
                    $out->start_element('error');
                    $out->write_attribute('line', $line);
                    $out->write_attribute('column', $column);
                    $out->write_attribute('severity', $error['type']);
                    $out->write_attribute('message', $error['message']);
                    $out->write_attribute('source', $error['source']);
                    $out->end_element();
                }
            }
        }
        //end foreach
        $out->end_element();
        echo $out->flush();
        return true;
    }
    //end generateFileReport()
    /**
     * Prints all violations for processed files, in a Checkstyle format.
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
        echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        echo '<checkstyle version="' . Config::VERSION . '">' . PHP_EOL;
        echo $cached_data;
        echo '</checkstyle>' . PHP_EOL;
    }
    //end generate()
}
//end class