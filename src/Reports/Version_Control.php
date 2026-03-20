<?php

declare (strict_types=1);
/**
 * Version control report base class for PHP_CodeSniffer.
 *
 * @author    Ben Selby <benmatselby@gmail.com>
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Reports;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Util\Timing;
abstract class Version_Control implements Report
{
    /**
     * The name of the report we want in the output.
     *
     * @var string
     */
    protected $report_name = 'VERSION CONTROL';
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
        $blames = $this->get_blame_content($report['filename']);
        $author_cache = [];
        $praise_cache = [];
        $source_cache = [];
        foreach ($report['messages'] as $line => $line_errors) {
            $author = 'Unknown';
            if (isset($blames[$line - 1]) === true) {
                $blame_author = $this->get_author($blames[$line - 1]);
                if ($blame_author !== false) {
                    $author = $blame_author;
                }
            }
            if (isset($author_cache[$author]) === false) {
                $author_cache[$author] = 0;
                $praise_cache[$author] = ['good' => 0, 'bad' => 0];
            }
            $praise_cache[$author]['bad']++;
            foreach ($line_errors as $col_errors) {
                foreach ($col_errors as $error) {
                    $author_cache[$author]++;
                    if ($show_sources === true) {
                        $source = $error['source'];
                        if (isset($source_cache[$author][$source]) === false) {
                            $source_cache[$author][$source] = ['count' => 1, 'fixable' => $error['fixable']];
                        } else {
                            $source_cache[$author][$source]['count']++;
                        }
                    }
                }
            }
            unset($blames[$line - 1]);
        }
        //end foreach
        // Now go through and give the authors some credit for
        // all the lines that do not have errors.
        foreach ($blames as $line) {
            $author = $this->get_author($line);
            if ($author === false) {
                $author = 'Unknown';
            }
            if (isset($author_cache[$author]) === false) {
                // This author doesn't have any errors.
                if (PHP_CODESNIFFER_VERBOSITY === 0) {
                    continue;
                }
                $author_cache[$author] = 0;
                $praise_cache[$author] = ['good' => 0, 'bad' => 0];
            }
            $praise_cache[$author]['good']++;
        }
        //end foreach
        foreach ($author_cache as $author => $errors) {
            echo "AUTHOR>>{$author}>>{$errors}" . PHP_EOL;
        }
        foreach ($praise_cache as $author => $praise) {
            echo "PRAISE>>{$author}>>" . $praise['good'] . '>>' . $praise['bad'] . PHP_EOL;
        }
        foreach ($source_cache as $author => $sources) {
            foreach ($sources as $source => $source_data) {
                $count = $source_data['count'];
                $fixable = (int) $source_data['fixable'];
                echo "SOURCE>>{$author}>>{$source}>>{$count}>>{$fixable}" . PHP_EOL;
            }
        }
        return true;
    }
    //end generateFileReport()
    /**
     * Prints the author of all errors and warnings, as given by "version control blame".
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
        $errors_shown = $total_errors + $total_warnings;
        if ($errors_shown === 0) {
            // Nothing to show.
            return;
        }
        $lines = explode(PHP_EOL, $cached_data);
        array_pop($lines);
        if (empty($lines) === true) {
            return;
        }
        $author_cache = [];
        $praise_cache = [];
        $source_cache = [];
        foreach ($lines as $line) {
            $parts = explode('>>', $line);
            switch ($parts[0]) {
                case 'AUTHOR':
                    if (isset($author_cache[$parts[1]]) === false) {
                        $author_cache[$parts[1]] = $parts[2];
                    } else {
                        $author_cache[$parts[1]] += $parts[2];
                    }
                    break;
                case 'PRAISE':
                    if (isset($praise_cache[$parts[1]]) === false) {
                        $praise_cache[$parts[1]] = ['good' => $parts[2], 'bad' => $parts[3]];
                    } else {
                        $praise_cache[$parts[1]]['good'] += $parts[2];
                        $praise_cache[$parts[1]]['bad'] += $parts[3];
                    }
                    break;
                case 'SOURCE':
                    if (isset($praise_cache[$parts[1]]) === false) {
                        $praise_cache[$parts[1]] = [];
                    }
                    if (isset($source_cache[$parts[1]][$parts[2]]) === false) {
                        $source_cache[$parts[1]][$parts[2]] = ['count' => $parts[3], 'fixable' => (bool) $parts[4]];
                    } else {
                        $source_cache[$parts[1]][$parts[2]]['count'] += $parts[3];
                    }
                    break;
                default:
                    break;
            }
            //end switch
        }
        //end foreach
        // Make sure the report width isn't too big.
        $max_length = 0;
        foreach ($author_cache as $author => $count) {
            $max_length = max($max_length, strlen($author));
            if ($show_sources === true && isset($source_cache[$author]) === true) {
                foreach ($source_cache[$author] as $source => $source_data) {
                    if ($source === 'count') {
                        continue;
                    }
                    $max_length = max($max_length, strlen($source) + 9);
                }
            }
        }
        $width = min($width, $max_length + 30);
        $width = max($width, 70);
        arsort($author_cache);
        echo PHP_EOL . "\x1b[1m" . 'PHP CODE SNIFFER ' . $this->report_name . ' BLAME SUMMARY' . "\x1b[0m" . PHP_EOL;
        echo str_repeat('-', $width) . PHP_EOL . "\x1b[1m";
        if ($show_sources === true) {
            echo 'AUTHOR   SOURCE' . str_repeat(' ', $width - 43) . '(Author %) (Overall %) COUNT' . PHP_EOL;
            echo str_repeat('-', $width) . PHP_EOL;
        } else {
            echo 'AUTHOR' . str_repeat(' ', $width - 34) . '(Author %) (Overall %) COUNT' . PHP_EOL;
            echo str_repeat('-', $width) . PHP_EOL;
        }
        echo "\x1b[0m";
        if ($show_sources === true) {
            $max_sniff_width = $width - 15;
            if ($total_fixable > 0) {
                $max_sniff_width -= 4;
            }
        }
        $fixable_sources = 0;
        foreach ($author_cache as $author => $count) {
            if ($praise_cache[$author]['good'] === 0) {
                $percent = 0;
            } else {
                $total = $praise_cache[$author]['bad'] + $praise_cache[$author]['good'];
                $percent = round($praise_cache[$author]['bad'] / $total * 100, 2);
            }
            $overall_percent = '(' . round($count / $errors_shown * 100, 2) . ')';
            $author_percent = '(' . $percent . ')';
            $line = str_repeat(' ', 6 - strlen($count)) . $count;
            $line = str_repeat(' ', 12 - strlen($overall_percent)) . $overall_percent . $line;
            $line = str_repeat(' ', 11 - strlen($author_percent)) . $author_percent . $line;
            $line = $author . str_repeat(' ', $width - strlen($author) - strlen($line)) . $line;
            if ($show_sources === true) {
                $line = "\x1b[1m{$line}\x1b[0m";
            }
            echo $line . PHP_EOL;
            if ($show_sources === true && isset($source_cache[$author]) === true) {
                $errors = $source_cache[$author];
                asort($errors);
                $errors = array_reverse($errors);
                foreach ($errors as $source => $source_data) {
                    if ($source === 'count') {
                        continue;
                    }
                    $count = $source_data['count'];
                    $src_length = strlen($source);
                    if ($src_length > $max_sniff_width) {
                        $source = substr($source, 0, $max_sniff_width);
                    }
                    $line = str_repeat(' ', 5 - strlen($count)) . $count;
                    echo '         ';
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
                    echo $source;
                    if ($total_fixable > 0) {
                        echo str_repeat(' ', $width - 18 - strlen($source));
                    } else {
                        echo str_repeat(' ', $width - 14 - strlen($source));
                    }
                    echo $line . PHP_EOL;
                }
                //end foreach
            }
            //end if
        }
        //end foreach
        echo str_repeat('-', $width) . PHP_EOL;
        echo "\x1b[1m" . 'A TOTAL OF ' . $errors_shown . ' SNIFF VIOLATION';
        if ($errors_shown !== 1) {
            echo 'S';
        }
        echo ' WERE COMMITTED BY ' . count($author_cache) . ' AUTHOR';
        if (count($author_cache) !== 1) {
            echo 'S';
        }
        echo "\x1b[0m";
        if ($total_fixable > 0) {
            if ($show_sources === true) {
                echo PHP_EOL . str_repeat('-', $width) . PHP_EOL;
                echo "\x1b[1mPHPCBF CAN FIX THE {$fixable_sources} MARKED SOURCES AUTOMATICALLY ({$total_fixable} VIOLATIONS IN TOTAL)\x1b[0m";
            } else {
                echo PHP_EOL . str_repeat('-', $width) . PHP_EOL;
                echo "\x1b[1mPHPCBF CAN FIX {$total_fixable} OF THESE SNIFF VIOLATIONS AUTOMATICALLY\x1b[0m";
            }
        }
        echo PHP_EOL . str_repeat('-', $width) . PHP_EOL . PHP_EOL;
        if ($to_screen === true && $interactive === false) {
            Timing::print_run_time();
        }
    }
    //end generate()
    /**
     * Extract the author from a blame line.
     *
     * @param string $line Line to parse.
     *
     * @return mixed string or false if impossible to recover.
     */
    abstract protected function get_author($line);
    /**
     * Gets the blame output.
     *
     * @param string $filename File to blame.
     *
     * @return array
     */
    abstract protected function get_blame_content($filename);
}
//end class