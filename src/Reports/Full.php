<?php

declare (strict_types=1);
/**
 * Full report for PHP_CodeSniffer.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Reports;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Util;
class Full implements Report
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
        // The length of the word ERROR or WARNING; used for padding.
        if ($report['warnings'] > 0) {
            $type_length = 7;
        } else {
            $type_length = 5;
        }
        // Work out the max line number length for formatting.
        $max_line_num_length = max(array_map('strlen', array_keys($report['messages'])));
        // The padding that all lines will require that are
        // printing an error message overflow.
        $padding_line2 = str_repeat(' ', $max_line_num_length + 1);
        $padding_line2 .= ' | ';
        $padding_line2 .= str_repeat(' ', $type_length);
        $padding_line2 .= ' | ';
        if ($report['fixable'] > 0) {
            $padding_line2 .= '    ';
        }
        $padding_length = strlen($padding_line2);
        // Make sure the report width isn't too big.
        $max_error_length = 0;
        foreach ($report['messages'] as $line => $line_errors) {
            foreach ($line_errors as $col_errors) {
                foreach ($col_errors as $error) {
                    $length = strlen($error['message']);
                    if ($show_sources === true) {
                        $length += strlen($error['source']) + 3;
                    }
                    $max_error_length = max($max_error_length, $length + 1);
                }
            }
        }
        $file = $report['filename'];
        $file_length = strlen($file);
        $max_width = max($file_length + 6, $max_error_length + $padding_length);
        $width = min($width, $max_width);
        if ($width < 70) {
            $width = 70;
        }
        echo PHP_EOL . "\x1b[1mFILE: ";
        if ($file_length <= $width - 6) {
            echo $file;
        } else {
            echo '...' . substr($file, $file_length - ($width - 6));
        }
        echo "\x1b[0m" . PHP_EOL;
        echo str_repeat('-', $width) . PHP_EOL;
        echo "\x1b[1m" . 'FOUND ' . $report['errors'] . ' ERROR';
        if ($report['errors'] !== 1) {
            echo 'S';
        }
        if ($report['warnings'] > 0) {
            echo ' AND ' . $report['warnings'] . ' WARNING';
            if ($report['warnings'] !== 1) {
                echo 'S';
            }
        }
        echo ' AFFECTING ' . count($report['messages']) . ' LINE';
        if (count($report['messages']) !== 1) {
            echo 'S';
        }
        echo "\x1b[0m" . PHP_EOL;
        echo str_repeat('-', $width) . PHP_EOL;
        // The maximum amount of space an error message can use.
        $max_error_space = $width - $padding_length - 1;
        foreach ($report['messages'] as $line => $line_errors) {
            foreach ($line_errors as $col_errors) {
                foreach ($col_errors as $error) {
                    $message = $error['message'];
                    $msg_lines = [$message];
                    if (strpos($message, "\n") !== false) {
                        $msg_lines = explode("\n", $message);
                    }
                    $error_msg = '';
                    $last_line = count($msg_lines) - 1;
                    foreach ($msg_lines as $k => $msg_line) {
                        if ($k === 0) {
                            if ($show_sources === true) {
                                $error_msg .= "\x1b[1m";
                            }
                        } else {
                            $error_msg .= PHP_EOL . $padding_line2;
                        }
                        if ($k === $last_line && $show_sources === true) {
                            $msg_line .= "\x1b[0m" . ' (' . $error['source'] . ')';
                        }
                        $error_msg .= wordwrap($msg_line, $max_error_space, PHP_EOL . $padding_line2);
                    }
                    // The padding that goes on the front of the line.
                    $padding = $max_line_num_length - strlen($line);
                    echo ' ' . str_repeat(' ', $padding) . $line . ' | ';
                    if ($error['type'] === 'ERROR') {
                        echo "\x1b[31mERROR\x1b[0m";
                        if ($report['warnings'] > 0) {
                            echo '  ';
                        }
                    } else {
                        echo "\x1b[33mWARNING\x1b[0m";
                    }
                    echo ' | ';
                    if ($report['fixable'] > 0) {
                        echo '[';
                        if ($error['fixable'] === true) {
                            echo 'x';
                        } else {
                            echo ' ';
                        }
                        echo '] ';
                    }
                    echo $error_msg . PHP_EOL;
                }
                //end foreach
            }
            //end foreach
        }
        //end foreach
        echo str_repeat('-', $width) . PHP_EOL;
        if ($report['fixable'] > 0) {
            echo "\x1b[1m" . 'PHPCBF CAN FIX THE ' . $report['fixable'] . ' MARKED SNIFF VIOLATIONS AUTOMATICALLY' . "\x1b[0m" . PHP_EOL;
            echo str_repeat('-', $width) . PHP_EOL;
        }
        echo PHP_EOL;
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
        if ($cached_data === '') {
            return;
        }
        echo $cached_data;
        if ($to_screen === true && $interactive === false) {
            Util\Timing::print_run_time();
        }
    }
    //end generate()
}
//end class