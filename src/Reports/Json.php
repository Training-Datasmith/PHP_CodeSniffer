<?php

declare (strict_types=1);
/**
 * JSON report for PHP_CodeSniffer.
 *
 * @author    Jeffrey Fisher <jeffslofish@gmail.com>
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Reports;

use Php_code_Sniffer\Files\File;
class Json implements Report
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
        $filename = str_replace('\\', '\\\\', $report['filename']);
        $filename = str_replace('"', '\"', $filename);
        $filename = str_replace('/', '\/', $filename);
        echo '"' . $filename . '":{';
        echo '"errors":' . $report['errors'] . ',"warnings":' . $report['warnings'] . ',"messages":[';
        $messages = '';
        foreach ($report['messages'] as $line => $line_errors) {
            foreach ($line_errors as $column => $col_errors) {
                foreach ($col_errors as $error) {
                    $error['message'] = str_replace("\n", '\n', $error['message']);
                    $error['message'] = str_replace("\r", '\r', $error['message']);
                    $error['message'] = str_replace("\t", '\t', $error['message']);
                    $fixable = false;
                    if ($error['fixable'] === true) {
                        $fixable = true;
                    }
                    $messages_object = (object) $error;
                    $messages_object->line = $line;
                    $messages_object->column = $column;
                    $messages_object->fixable = $fixable;
                    $messages .= json_encode($messages_object) . ',';
                }
            }
        }
        //end foreach
        echo rtrim($messages, ',');
        echo ']},';
        return true;
    }
    //end generateFileReport()
    /**
     * Generates a JSON report.
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
        echo '{"totals":{"errors":' . $total_errors . ',"warnings":' . $total_warnings . ',"fixable":' . $total_fixable . '},"files":{';
        echo rtrim($cached_data, ',');
        echo '}}' . PHP_EOL;
    }
    //end generate()
}
//end class