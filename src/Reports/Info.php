<?php

declare (strict_types=1);
/**
 * Info report for PHP_CodeSniffer.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Reports;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Util\Timing;
class Info implements Report
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
        $metrics = $phpcs_file->get_metrics();
        foreach ($metrics as $metric => $data) {
            foreach ($data['values'] as $value => $count) {
                echo "{$metric}>>{$value}>>{$count}" . PHP_EOL;
            }
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
        $metrics = [];
        foreach ($lines as $line) {
            $parts = explode('>>', $line);
            $metric = $parts[0];
            $value = $parts[1];
            $count = $parts[2];
            if (isset($metrics[$metric]) === false) {
                $metrics[$metric] = [];
            }
            if (isset($metrics[$metric][$value]) === false) {
                $metrics[$metric][$value] = $count;
            } else {
                $metrics[$metric][$value] += $count;
            }
        }
        ksort($metrics);
        echo PHP_EOL . "\x1b[1m" . 'PHP CODE SNIFFER INFORMATION REPORT' . "\x1b[0m" . PHP_EOL;
        echo str_repeat('-', 70) . PHP_EOL;
        foreach ($metrics as $metric => $values) {
            if (count($values) === 1) {
                $count = reset($values);
                $value = key($values);
                echo "{$metric}: \x1b[4m{$value}\x1b[0m [{$count}/{$count}, 100%]" . PHP_EOL;
            } else {
                $total_count = 0;
                $value_width = 0;
                foreach ($values as $value => $count) {
                    $total_count += $count;
                    $value_width = max($value_width, strlen($value));
                }
                // Length of the total string, plus however many
                // thousands separators there are.
                $count_width = strlen($total_count);
                $thousand_separator_count = floor($count_width / 3);
                $count_width += $thousand_separator_count;
                // Account for 'total' line.
                $value_width = max(5, $value_width);
                echo "{$metric}:" . PHP_EOL;
                ksort($values, SORT_NATURAL);
                arsort($values);
                $percent_prefix_width = 0;
                $percent_width = 6;
                foreach ($values as $value => $count) {
                    $percent = round($count / $total_count * 100, 2);
                    $percent_prefix = '';
                    if ($percent === 0.0) {
                        $percent = 0.01;
                        $percent_prefix = '<';
                        $percent_prefix_width = 2;
                        $percent_width = 4;
                    }
                    printf("\t%-{$value_width}s => %{$count_width}s (%{$percent_prefix_width}s%{$percent_width}.2f%%)" . PHP_EOL, $value, number_format($count), $percent_prefix, $percent);
                }
                echo "\t" . str_repeat('-', $value_width + $count_width + 15) . PHP_EOL;
                printf("\t%-{$value_width}s => %{$count_width}s (100.00%%)" . PHP_EOL, 'total', number_format($total_count));
            }
            //end if
            echo PHP_EOL;
        }
        //end foreach
        echo str_repeat('-', 70) . PHP_EOL;
        if ($to_screen === true && $interactive === false) {
            Timing::print_run_time();
        }
    }
    //end generate()
}
//end class