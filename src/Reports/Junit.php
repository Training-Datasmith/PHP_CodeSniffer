<?php

declare (strict_types=1);
/**
 * JUnit report for PHP_CodeSniffer.
 *
 * @author    Oleg Lobach <oleg@lobach.info>
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Reports;

use Php_code_Sniffer\Config;
use Php_code_Sniffer\Files\File;
class Junit implements Report
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
        $out->start_element('testsuite');
        $out->write_attribute('name', $report['filename']);
        $out->write_attribute('errors', 0);
        if (count($report['messages']) === 0) {
            $out->write_attribute('tests', 1);
            $out->write_attribute('failures', 0);
            $out->start_element('testcase');
            $out->write_attribute('name', $report['filename']);
            $out->end_element();
        } else {
            $failures = $report['errors'] + $report['warnings'];
            $out->write_attribute('tests', $failures);
            $out->write_attribute('failures', $failures);
            foreach ($report['messages'] as $line => $line_errors) {
                foreach ($line_errors as $column => $col_errors) {
                    foreach ($col_errors as $error) {
                        $out->start_element('testcase');
                        $out->write_attribute('name', $error['source'] . ' at ' . $report['filename'] . " ({$line}:{$column})");
                        $error['type'] = strtolower($error['type']);
                        if ($phpcs_file->config->encoding !== 'utf-8') {
                            $error['message'] = iconv($phpcs_file->config->encoding, 'utf-8', $error['message']);
                        }
                        $out->start_element('failure');
                        $out->write_attribute('type', $error['type']);
                        $out->write_attribute('message', $error['message']);
                        $out->end_element();
                        $out->end_element();
                    }
                }
            }
        }
        //end if
        $out->end_element();
        echo $out->flush();
        return true;
    }
    //end generateFileReport()
    /**
     * Prints all violations for processed files, in a proprietary XML format.
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
        // Figure out the total number of tests.
        $tests = 0;
        $matches = [];
        preg_match_all('/tests="([0-9]+)"/', $cached_data, $matches);
        if (isset($matches[1]) === true) {
            foreach ($matches[1] as $match) {
                $tests += $match;
            }
        }
        $failures = $total_errors + $total_warnings;
        echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        echo '<testsuites name="PHP_CodeSniffer ' . Config::VERSION . '" errors="0" tests="' . $tests . '" failures="' . $failures . '">' . PHP_EOL;
        echo $cached_data;
        echo '</testsuites>' . PHP_EOL;
    }
    //end generate()
}
//end class