<?php

declare (strict_types=1);
/**
 * CBF report for PHP_CodeSniffer.
 *
 * This report implements the various auto-fixing features of the
 * PHPCBF script and is not intended (or allowed) to be selected as a
 * report from the command line.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Reports;

use Php_code_Sniffer\Exceptions\Deep_Exit_Exception;
use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Util;
class Cbf implements Report
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
     * @throws \PHP_CodeSniffer\Exceptions\DeepExitException
     */
    public function generate_file_report($report, File $phpcs_file, $show_sources = false, $width = 80)
    {
        $errors = $phpcs_file->get_fixable_count();
        if ($errors !== 0) {
            if (PHP_CODESNIFFER_VERBOSITY > 0) {
                ob_end_clean();
                $start_time = microtime(true);
                echo "\t=> Fixing file: {$errors}/{$errors} violations remaining";
                if (PHP_CODESNIFFER_VERBOSITY > 1) {
                    echo PHP_EOL;
                }
            }
            $fixed = $phpcs_file->fixer->fix_file();
        }
        if ($phpcs_file->config->stdin === true) {
            // Replacing STDIN, so output current file to STDOUT
            // even if nothing was fixed. Exit here because we
            // can't process any more than 1 file in this setup.
            $fixed_content = $phpcs_file->fixer->get_contents();
            throw new Deep_Exit_Exception($fixed_content, 1);
        }
        if ($errors === 0) {
            return false;
        }
        if (PHP_CODESNIFFER_VERBOSITY > 0) {
            if ($fixed === false) {
                echo 'ERROR';
            } else {
                echo 'DONE';
            }
            $time_taken = (microtime(true) - $start_time) * 1000;
            if ($time_taken < 1000) {
                $time_taken = round($time_taken);
                echo " in {$time_taken}ms" . PHP_EOL;
            } else {
                $time_taken = round($time_taken / 1000, 2);
                echo " in {$time_taken} secs" . PHP_EOL;
            }
        }
        if ($fixed === true) {
            // The filename in the report may be truncated due to a basepath setting
            // but we are using it for writing here and not display,
            // so find the correct path if basepath is in use.
            $new_filename = $report['filename'] . $phpcs_file->config->suffix;
            if ($phpcs_file->config->basepath !== null) {
                $new_filename = $phpcs_file->config->basepath . DIRECTORY_SEPARATOR . $new_filename;
            }
            $new_content = $phpcs_file->fixer->get_contents();
            file_put_contents($new_filename, $new_content);
            if (PHP_CODESNIFFER_VERBOSITY > 0) {
                if ($new_filename === $report['filename']) {
                    echo "\t=> File was overwritten" . PHP_EOL;
                } else {
                    echo "\t=> Fixed file written to " . basename($new_filename) . PHP_EOL;
                }
            }
        }
        if (PHP_CODESNIFFER_VERBOSITY > 0) {
            ob_start();
        }
        $error_count = $phpcs_file->get_error_count();
        $warning_count = $phpcs_file->get_warning_count();
        $fixable_count = $phpcs_file->get_fixable_count();
        $fixed_count = $errors - $fixable_count;
        echo $report['filename'] . ">>{$error_count}>>{$warning_count}>>{$fixable_count}>>{$fixed_count}" . PHP_EOL;
        return $fixed;
    }
    //end generateFileReport()
    /**
     * Prints a summary of fixed files.
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
            echo PHP_EOL . 'No fixable errors were found' . PHP_EOL;
            return;
        }
        $report_files = [];
        $max_length = 0;
        $total_fixed = 0;
        $failures = 0;
        foreach ($lines as $line) {
            $parts = explode('>>', $line);
            $file_len = strlen($parts[0]);
            $report_files[$parts[0]] = ['errors' => $parts[1], 'warnings' => $parts[2], 'fixable' => $parts[3], 'fixed' => $parts[4], 'strlen' => $file_len];
            $max_length = max($max_length, $file_len);
            $total_fixed += $parts[4];
            if ($parts[3] > 0) {
                $failures++;
            }
        }
        $width = min($width, $max_length + 21);
        $width = max($width, 70);
        echo PHP_EOL . "\x1b[1m" . 'PHPCBF RESULT SUMMARY' . "\x1b[0m" . PHP_EOL;
        echo str_repeat('-', $width) . PHP_EOL;
        echo "\x1b[1m" . 'FILE' . str_repeat(' ', $width - 20) . 'FIXED  REMAINING' . "\x1b[0m" . PHP_EOL;
        echo str_repeat('-', $width) . PHP_EOL;
        foreach ($report_files as $file => $data) {
            $padding = $width - 18 - $data['strlen'];
            if ($padding < 0) {
                $file = '...' . substr($file, $padding * -1 + 3);
                $padding = 0;
            }
            echo $file . str_repeat(' ', $padding) . '  ';
            if ($data['fixable'] > 0) {
                echo "\x1b[31mFAILED TO FIX\x1b[0m" . PHP_EOL;
                continue;
            }
            $remaining = $data['errors'] + $data['warnings'];
            if ($data['fixed'] !== 0) {
                echo $data['fixed'];
                echo str_repeat(' ', 7 - strlen($data['fixed']));
            } else {
                echo '0      ';
            }
            if ($remaining !== 0) {
                echo $remaining;
            } else {
                echo '0';
            }
            echo PHP_EOL;
        }
        //end foreach
        echo str_repeat('-', $width) . PHP_EOL;
        echo "\x1b[1mA TOTAL OF {$total_fixed} ERROR";
        if ($total_fixed !== 1) {
            echo 'S';
        }
        $num_files = count($report_files);
        echo ' WERE FIXED IN ' . $num_files . ' FILE';
        if ($num_files !== 1) {
            echo 'S';
        }
        echo "\x1b[0m";
        if ($failures > 0) {
            echo PHP_EOL . str_repeat('-', $width) . PHP_EOL;
            echo "\x1b[1mPHPCBF FAILED TO FIX {$failures} FILE";
            if ($failures !== 1) {
                echo 'S';
            }
            echo "\x1b[0m";
        }
        echo PHP_EOL . str_repeat('-', $width) . PHP_EOL . PHP_EOL;
        if ($to_screen === true && $interactive === false) {
            Util\Timing::print_run_time();
        }
    }
    //end generate()
}
//end class