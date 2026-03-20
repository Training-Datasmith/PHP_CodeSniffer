<?php

declare (strict_types=1);
/**
 * Source report for PHP_CodeSniffer.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Reports;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Util\Timing;
class Source implements Report
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
        $sources = [];
        foreach ($report['messages'] as $line_errors) {
            foreach ($line_errors as $col_errors) {
                foreach ($col_errors as $error) {
                    $src = $error['source'];
                    if (isset($sources[$src]) === false) {
                        $sources[$src] = ['fixable' => (int) $error['fixable'], 'count' => 1];
                    } else {
                        $sources[$src]['count']++;
                    }
                }
            }
        }
        foreach ($sources as $source => $data) {
            echo $source . '>>' . $data['fixable'] . '>>' . $data['count'] . PHP_EOL;
        }
        return true;
    }
    //end generateFileReport()
    /**
     * Prints the source of all errors and warnings.
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
        $sources = [];
        $max_length = 0;
        foreach ($lines as $line) {
            $parts = explode('>>', $line);
            $source = $parts[0];
            $fixable = (bool) $parts[1];
            $count = $parts[2];
            if (isset($sources[$source]) === false) {
                if ($show_sources === true) {
                    $parts = null;
                    $sniff = $source;
                } else {
                    $parts = explode('.', $source);
                    if ($parts[0] === 'Internal') {
                        $parts[2] = $parts[1];
                        $parts[1] = '';
                    }
                    $parts[1] = $this->make_friendly_name($parts[1]);
                    $sniff = $this->make_friendly_name($parts[2]);
                    if (isset($parts[3]) === true) {
                        $name = $this->make_friendly_name($parts[3]);
                        $name[0] = strtolower($name[0]);
                        $sniff .= ' ' . $name;
                        unset($parts[3]);
                    }
                    $parts[2] = $sniff;
                }
                //end if
                $max_length = max($max_length, strlen($sniff));
                $sources[$source] = ['count' => $count, 'fixable' => $fixable, 'parts' => $parts];
            } else {
                $sources[$source]['count'] += $count;
            }
            //end if
        }
        //end foreach
        if ($show_sources === true) {
            $width = min($width, $max_length + 11);
        } else {
            $width = min($width, $max_length + 41);
        }
        $width = max($width, 70);
        // Sort the data based on counts and source code.
        $source_codes = array_keys($sources);
        $counts = [];
        foreach ($sources as $source => $data) {
            $counts[$source] = $data['count'];
        }
        array_multisort($counts, SORT_DESC, $source_codes, SORT_ASC, SORT_NATURAL, $sources);
        echo PHP_EOL . "\x1b[1mPHP CODE SNIFFER VIOLATION SOURCE SUMMARY\x1b[0m" . PHP_EOL;
        echo str_repeat('-', $width) . PHP_EOL . "\x1b[1m";
        if ($show_sources === true) {
            if ($total_fixable > 0) {
                echo '    SOURCE' . str_repeat(' ', $width - 15) . 'COUNT' . PHP_EOL;
            } else {
                echo 'SOURCE' . str_repeat(' ', $width - 11) . 'COUNT' . PHP_EOL;
            }
        } else if ($total_fixable > 0) {
            echo '    STANDARD  CATEGORY            SNIFF' . str_repeat(' ', $width - 44) . 'COUNT' . PHP_EOL;
        } else {
            echo 'STANDARD  CATEGORY            SNIFF' . str_repeat(' ', $width - 40) . 'COUNT' . PHP_EOL;
        }
        echo "\x1b[0m" . str_repeat('-', $width) . PHP_EOL;
        $fixable_sources = 0;
        if ($show_sources === true) {
            $max_sniff_width = $width - 7;
        } else {
            $max_sniff_width = $width - 37;
        }
        if ($total_fixable > 0) {
            $max_sniff_width -= 4;
        }
        foreach ($sources as $source => $source_data) {
            if ($total_fixable > 0) {
                echo '[';
                if ($source_data['fixable'] === true) {
                    echo 'x';
                    $fixable_sources++;
                } else {
                    echo ' ';
                }
                echo '] ';
            }
            if ($show_sources === true) {
                if (strlen($source) > $max_sniff_width) {
                    $source = substr($source, 0, $max_sniff_width);
                }
                echo $source;
                if ($total_fixable > 0) {
                    echo str_repeat(' ', $width - 9 - strlen($source));
                } else {
                    echo str_repeat(' ', $width - 5 - strlen($source));
                }
            } else {
                $parts = $source_data['parts'];
                if (strlen($parts[0]) > 8) {
                    $parts[0] = substr($parts[0], 0, (strlen($parts[0]) - 8) * -1);
                }
                echo $parts[0] . str_repeat(' ', 10 - strlen($parts[0]));
                $category = $parts[1];
                if (strlen($category) > 18) {
                    $category = substr($category, 0, (strlen($category) - 18) * -1);
                }
                echo $category . str_repeat(' ', 20 - strlen($category));
                $sniff = $parts[2];
                if (strlen($sniff) > $max_sniff_width) {
                    $sniff = substr($sniff, 0, $max_sniff_width);
                }
                if ($total_fixable > 0) {
                    echo $sniff . str_repeat(' ', $width - 39 - strlen($sniff));
                } else {
                    echo $sniff . str_repeat(' ', $width - 35 - strlen($sniff));
                }
            }
            //end if
            echo $source_data['count'] . PHP_EOL;
        }
        //end foreach
        echo str_repeat('-', $width) . PHP_EOL;
        echo "\x1b[1m" . 'A TOTAL OF ' . ($total_errors + $total_warnings) . ' SNIFF VIOLATION';
        if ($total_errors + $total_warnings > 1) {
            echo 'S';
        }
        echo ' WERE FOUND IN ' . count($sources) . ' SOURCE';
        if (count($sources) !== 1) {
            echo 'S';
        }
        echo "\x1b[0m";
        if ($total_fixable > 0) {
            echo PHP_EOL . str_repeat('-', $width) . PHP_EOL;
            echo "\x1b[1mPHPCBF CAN FIX THE {$fixable_sources} MARKED SOURCES AUTOMATICALLY ({$total_fixable} VIOLATIONS IN TOTAL)\x1b[0m";
        }
        echo PHP_EOL . str_repeat('-', $width) . PHP_EOL . PHP_EOL;
        if ($to_screen === true && $interactive === false) {
            Timing::print_run_time();
        }
    }
    //end generate()
    /**
     * Converts a camel caps name into a readable string.
     *
     * @param string $name The camel caps name to convert.
     *
     * @return string
     */
    public function make_friendly_name($name)
    {
        if (trim($name) === '') {
            return '';
        }
        $friendly_name = '';
        $length = strlen($name);
        $last_was_upper = false;
        $last_was_numeric = false;
        for ($i = 0; $i < $length; $i++) {
            if (is_numeric($name[$i]) === true) {
                if ($last_was_numeric === false) {
                    $friendly_name .= ' ';
                }
                $last_was_upper = false;
                $last_was_numeric = true;
            } else {
                $last_was_numeric = false;
                $char = strtolower($name[$i]);
                if ($char === $name[$i]) {
                    // Lowercase.
                    $last_was_upper = false;
                } else {
                    // Uppercase.
                    if ($last_was_upper === false) {
                        $friendly_name .= ' ';
                        if ($i < $length - 1) {
                            $next = $name[$i + 1];
                            if (strtolower($next) === $next) {
                                // Next char is lowercase so it is a word boundary.
                                $name[$i] = strtolower($name[$i]);
                            }
                        }
                    }
                    $last_was_upper = true;
                }
            }
            //end if
            $friendly_name .= $name[$i];
        }
        //end for
        $friendly_name = trim($friendly_name);
        $friendly_name[0] = strtoupper($friendly_name[0]);
        return $friendly_name;
    }
    //end makeFriendlyName()
}
//end class