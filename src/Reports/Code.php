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
class Code implements Report
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
        // How many lines to show about and below the error line.
        $surrounding_lines = 2;
        $file = $report['filename'];
        $tokens = $phpcs_file->get_tokens();
        if (empty($tokens) === true) {
            if (PHP_CODESNIFFER_VERBOSITY === 1) {
                $start_time = microtime(true);
                echo 'CODE report is parsing ' . basename($file) . ' ';
            } elseif (PHP_CODESNIFFER_VERBOSITY > 1) {
                echo "CODE report is forcing parse of {$file}" . PHP_EOL;
            }
            try {
                $phpcs_file->parse();
            } catch (\Exception $e) {
                // This is a second parse, so ignore exceptions.
                // They would have been added to the file's error list already.
            }
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
            $tokens = $phpcs_file->get_tokens();
        }
        //end if
        // Create an array that maps lines to the first token on the line.
        $line_tokens = [];
        $last_line = 0;
        $stack_ptr = 0;
        foreach ($tokens as $stack_ptr => $token) {
            if ($token['line'] !== $last_line) {
                if ($last_line > 0) {
                    $line_tokens[$last_line]['end'] = $stack_ptr - 1;
                }
                $last_line++;
                $line_tokens[$last_line] = ['start' => $stack_ptr, 'end' => null];
            }
        }
        // Make sure the last token in the file sits on an imaginary
        // last line so it is easier to generate code snippets at the
        // end of the file.
        $line_tokens[$last_line]['end'] = $stack_ptr;
        // Determine the longest code line we will be showing.
        $max_snippet_length = 0;
        $eol_len = strlen($phpcs_file->eol_char);
        foreach ($report['messages'] as $line => $line_errors) {
            $start_line = max($line - $surrounding_lines, 1);
            $end_line = min($line + $surrounding_lines, $last_line);
            $max_line_num_length = strlen($end_line);
            for ($i = $start_line; $i <= $end_line; $i++) {
                if ($i === 1) {
                    continue;
                }
                $line_length = $tokens[$line_tokens[$i]['start'] - 1]['column'] + $tokens[$line_tokens[$i]['start'] - 1]['length'] - $eol_len;
                $max_snippet_length = max($line_length, $max_snippet_length);
            }
        }
        $max_snippet_length += $max_line_num_length + 8;
        // Determine the longest error message we will be showing.
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
        // The padding that all lines will require that are printing an error message overflow.
        if ($report['warnings'] > 0) {
            $type_length = 7;
        } else {
            $type_length = 5;
        }
        $error_padding = str_repeat(' ', $max_line_num_length + 7);
        $error_padding .= str_repeat(' ', $type_length);
        $error_padding .= ' ';
        if ($report['fixable'] > 0) {
            $error_padding .= '    ';
        }
        $error_padding_length = strlen($error_padding);
        // The maximum amount of space an error message can use.
        $max_error_space = $width - $error_padding_length;
        if ($show_sources === true) {
            // Account for the chars used to print colors.
            $max_error_space += 8;
        }
        // Figure out the max report width we need and can use.
        $file_length = strlen($file);
        $max_width = max($file_length + 6, $max_error_length + $error_padding_length);
        $width = max(min($width, $max_width), $max_snippet_length);
        if ($width < 70) {
            $width = 70;
        }
        // Print the file header.
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
        foreach ($report['messages'] as $line => $line_errors) {
            $start_line = max($line - $surrounding_lines, 1);
            $end_line = min($line + $surrounding_lines, $last_line);
            $snippet = '';
            if (isset($line_tokens[$start_line]) === true) {
                for ($i = $line_tokens[$start_line]['start']; $i <= $line_tokens[$end_line]['end']; $i++) {
                    $snippet_line = $tokens[$i]['line'];
                    if ($line_tokens[$snippet_line]['start'] === $i) {
                        // Starting a new line.
                        if ($snippet_line === $line) {
                            $snippet .= "\x1b[1m" . '>> ';
                        } else {
                            $snippet .= '   ';
                        }
                        $snippet .= str_repeat(' ', $max_line_num_length - strlen($snippet_line));
                        $snippet .= $snippet_line . ':  ';
                        if ($snippet_line === $line) {
                            $snippet .= "\x1b[0m";
                        }
                    }
                    if (isset($tokens[$i]['orig_content']) === true) {
                        $token_content = $tokens[$i]['orig_content'];
                    } else {
                        $token_content = $tokens[$i]['content'];
                    }
                    if (strpos($token_content, "\t") !== false) {
                        $token = $tokens[$i];
                        $token['content'] = $token_content;
                        if (stripos(PHP_OS, 'WIN') === 0) {
                            $tab = "\x00";
                        } else {
                            $tab = "\x1b[30;1m»\x1b[0m";
                        }
                        $phpcs_file->tokenizer->replace_tabs_in_token($token, $tab, "\x00");
                        $token_content = $token['content'];
                    }
                    $token_content = Util\Common::prepare_for_output($token_content, ["\r", "\n", "\t"]);
                    $token_content = str_replace("\x00", ' ', $token_content);
                    $underline = false;
                    if ($snippet_line === $line && isset($line_errors[$tokens[$i]['column']]) === true) {
                        $underline = true;
                    }
                    // Underline invisible characters as well.
                    if ($underline === true && trim($token_content) === '') {
                        $snippet .= "\x1b[4m" . ' ' . "\x1b[0m" . $token_content;
                    } else {
                        if ($underline === true) {
                            $snippet .= "\x1b[4m";
                        }
                        $snippet .= $token_content;
                        if ($underline === true) {
                            $snippet .= "\x1b[0m";
                        }
                    }
                }
                //end for
            }
            //end if
            echo str_repeat('-', $width) . PHP_EOL;
            foreach ($line_errors as $col_errors) {
                foreach ($col_errors as $error) {
                    $padding = $max_line_num_length - strlen($line);
                    echo 'LINE ' . str_repeat(' ', $padding) . $line . ': ';
                    if ($error['type'] === 'ERROR') {
                        echo "\x1b[31mERROR\x1b[0m";
                        if ($report['warnings'] > 0) {
                            echo '  ';
                        }
                    } else {
                        echo "\x1b[33mWARNING\x1b[0m";
                    }
                    echo ' ';
                    if ($report['fixable'] > 0) {
                        echo '[';
                        if ($error['fixable'] === true) {
                            echo 'x';
                        } else {
                            echo ' ';
                        }
                        echo '] ';
                    }
                    $message = $error['message'];
                    $message = str_replace("\n", "\n" . $error_padding, $message);
                    if ($show_sources === true) {
                        $message = "\x1b[1m" . $message . "\x1b[0m" . ' (' . $error['source'] . ')';
                    }
                    $error_msg = wordwrap($message, $max_error_space, PHP_EOL . $error_padding);
                    echo $error_msg . PHP_EOL;
                }
                //end foreach
            }
            //end foreach
            echo str_repeat('-', $width) . PHP_EOL;
            echo rtrim($snippet) . PHP_EOL;
        }
        //end foreach
        echo str_repeat('-', $width) . PHP_EOL;
        if ($report['fixable'] > 0) {
            echo "\x1b[1m" . 'PHPCBF CAN FIX THE ' . $report['fixable'] . ' MARKED SNIFF VIOLATIONS AUTOMATICALLY' . "\x1b[0m" . PHP_EOL;
            echo str_repeat('-', $width) . PHP_EOL;
        }
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