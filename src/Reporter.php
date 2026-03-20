<?php

declare (strict_types=1);
/**
 * Manages reporting of errors and warnings.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer;

use Php_code_Sniffer\Exceptions\Deep_Exit_Exception;
use Php_code_Sniffer\Exceptions\RuntimeException;
use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Reports\Report;
use Php_code_Sniffer\Util\Common;
class Reporter
{
    /**
     * The config data for the run.
     *
     * @var \PHP_CodeSniffer\Config
     */
    public $config;
    /**
     * Total number of files that contain errors or warnings.
     *
     * @var integer
     */
    public $total_files = 0;
    /**
     * Total number of errors found during the run.
     *
     * @var integer
     */
    public $total_errors = 0;
    /**
     * Total number of warnings found during the run.
     *
     * @var integer
     */
    public $total_warnings = 0;
    /**
     * Total number of errors/warnings that can be fixed.
     *
     * @var integer
     */
    public $total_fixable = 0;
    /**
     * Total number of errors/warnings that were fixed.
     *
     * @var integer
     */
    public $total_fixed = 0;
    /**
     * When the PHPCS run started.
     *
     * @var float
     */
    public static $start_time = 0;
    /**
     * A cache of report objects.
     *
     * @var array
     */
    private $reports = [];
    /**
     * A cache of opened temporary files.
     *
     * @var array
     */
    private $tmp_files = [];
    /**
     * Initialise the reporter.
     *
     * All reports specified in the config will be created and their
     * output file (or a temp file if none is specified) initialised by
     * clearing the current contents.
     *
     * @param \PHP_CodeSniffer\Config $config The config data for the run.
     *
     * @throws \PHP_CodeSniffer\Exceptions\DeepExitException If a custom report class could not be found.
     * @throws \PHP_CodeSniffer\Exceptions\RuntimeException  If a report class is incorrectly set up.
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
        foreach ($config->reports as $type => $output) {
            if ($output === null) {
                $output = $config->report_file;
            }
            $report_class_name = '';
            if (strpos($type, '.') !== false) {
                // This is a path to a custom report class.
                $filename = realpath($type);
                if ($filename === false) {
                    $error = "ERROR: Custom report \"{$type}\" not found" . PHP_EOL;
                    throw new Deep_Exit_Exception($error, 3);
                }
                $report_class_name = Autoload::load_file($filename);
            } elseif (class_exists('PHP_CodeSniffer\Reports\\' . ucfirst($type)) === true) {
                // PHPCS native report.
                $report_class_name = 'PHP_CodeSniffer\Reports\\' . ucfirst($type);
            } elseif (class_exists($type) === true) {
                // FQN of a custom report.
                $report_class_name = $type;
            } else {
                // OK, so not a FQN, try and find the report using the registered namespaces.
                $registered_namespaces = Autoload::get_search_paths();
                $trimmed_type = ltrim($type, '\\');
                foreach ($registered_namespaces as $ns_prefix) {
                    if ($ns_prefix === '') {
                        continue;
                    }
                    if (class_exists($ns_prefix . '\\' . $trimmed_type) === true) {
                        $report_class_name = $ns_prefix . '\\' . $trimmed_type;
                        break;
                    }
                }
            }
            //end if
            if ($report_class_name === '') {
                $error = "ERROR: Class file for report \"{$type}\" not found" . PHP_EOL;
                throw new Deep_Exit_Exception($error, 3);
            }
            $report_class = new $report_class_name();
            if ($report_class instanceof Report === false) {
                throw new RuntimeException('Class "' . $report_class_name . '" must implement the "PHP_CodeSniffer\Report" interface.');
            }
            $this->reports[$type] = ['output' => $output, 'class' => $report_class];
            if ($output === null) {
                // Using a temp file.
                // This needs to be set in the constructor so that all
                // child procs use the same report file when running in parallel.
                $this->tmp_files[$type] = tempnam(sys_get_temp_dir(), 'phpcs');
                file_put_contents($this->tmp_files[$type], '');
            } else {
                file_put_contents($output, '');
            }
        }
        //end foreach
    }
    //end __construct()
    /**
     * Generates and prints final versions of all reports.
     *
     * Returns TRUE if any of the reports output content to the screen
     * or FALSE if all reports were silently printed to a file.
     *
     * @return bool
     */
    public function print_reports()
    {
        $to_screen = false;
        foreach ($this->reports as $type => $report) {
            if ($report['output'] === null) {
                $to_screen = true;
            }
            $this->print_report($type);
        }
        return $to_screen;
    }
    //end printReports()
    /**
     * Generates and prints a single final report.
     *
     * @param string $report The report type to print.
     *
     * @return void
     */
    public function print_report($report)
    {
        $report_class = $this->reports[$report]['class'];
        $report_file = $this->reports[$report]['output'];
        if ($report_file !== null) {
            $filename = $report_file;
            $to_screen = false;
        } else {
            if (isset($this->tmp_files[$report]) === true) {
                $filename = $this->tmp_files[$report];
            } else {
                $filename = null;
            }
            $to_screen = true;
        }
        $report_cache = '';
        if ($filename !== null) {
            $report_cache = file_get_contents($filename);
        }
        ob_start();
        $report_class->generate($report_cache, $this->total_files, $this->total_errors, $this->total_warnings, $this->total_fixable, $this->config->show_sources, $this->config->report_width, $this->config->interactive, $to_screen);
        $generated_report = ob_get_contents();
        ob_end_clean();
        if ($this->config->colors !== true || $report_file !== null) {
            $generated_report = preg_replace('`\033\[[0-9;]+m`', '', $generated_report);
        }
        if ($report_file !== null) {
            if (PHP_CODESNIFFER_VERBOSITY > 0) {
                echo $generated_report;
            }
            file_put_contents($report_file, $generated_report . PHP_EOL);
        } else {
            echo $generated_report;
            if ($filename !== null && file_exists($filename) === true) {
                unlink($filename);
                unset($this->tmp_files[$report]);
            }
        }
    }
    //end printReport()
    /**
     * Caches the result of a single processed file for all reports.
     *
     * The report content that is generated is appended to the output file
     * assigned to each report. This content may be an intermediate report format
     * and not reflect the final report output.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file that has been processed.
     *
     * @return void
     */
    public function cache_file_report(File $phpcs_file)
    {
        if (isset($this->config->reports) === false) {
            // This happens during unit testing, or any time someone just wants
            // the error data and not the printed report.
            return;
        }
        $report_data = $this->prepare_file_report($phpcs_file);
        $errors_shown = false;
        foreach ($this->reports as $type => $report) {
            $report_class = $report['class'];
            ob_start();
            $result = $report_class->generate_file_report($report_data, $phpcs_file, $this->config->show_sources, $this->config->report_width);
            if ($result === true) {
                $errors_shown = true;
            }
            $generated_report = ob_get_contents();
            ob_end_clean();
            if ($report['output'] === null) {
                // Using a temp file.
                if (isset($this->tmp_files[$type]) === false) {
                    // When running in interactive mode, the reporter prints the full
                    // report many times, which will unlink the temp file. So we need
                    // to create a new one if it doesn't exist.
                    $this->tmp_files[$type] = tempnam(sys_get_temp_dir(), 'phpcs');
                    file_put_contents($this->tmp_files[$type], '');
                }
                file_put_contents($this->tmp_files[$type], $generated_report, FILE_APPEND | LOCK_EX);
            } else {
                file_put_contents($report['output'], $generated_report, FILE_APPEND | LOCK_EX);
            }
            //end if
        }
        //end foreach
        if ($errors_shown === true || PHP_CODESNIFFER_CBF === true) {
            $this->total_files++;
            $this->total_errors += $report_data['errors'];
            $this->total_warnings += $report_data['warnings'];
            // When PHPCBF is running, we need to use the fixable error values
            // after the report has run and fixed what it can.
            if (PHP_CODESNIFFER_CBF === true) {
                $this->total_fixable += $phpcs_file->get_fixable_count();
                $this->total_fixed += $phpcs_file->get_fixed_count();
            } else {
                $this->total_fixable += $report_data['fixable'];
            }
        }
    }
    //end cacheFileReport()
    /**
     * Generate summary information to be used during report generation.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file that has been processed.
     *
     * @return array
     */
    public function prepare_file_report(File $phpcs_file)
    {
        $report = ['filename' => Common::strip_basepath($phpcs_file->get_filename(), $this->config->basepath), 'errors' => $phpcs_file->get_error_count(), 'warnings' => $phpcs_file->get_warning_count(), 'fixable' => $phpcs_file->get_fixable_count(), 'messages' => []];
        if ($report['errors'] === 0 && $report['warnings'] === 0) {
            // Prefect score!
            return $report;
        }
        if ($this->config->record_errors === false) {
            $message = 'Errors are not being recorded but this report requires error messages. ';
            $message .= 'This report will not show the correct information.';
            $report['messages'][1][1] = [['message' => $message, 'source' => 'Internal.RecordErrors', 'severity' => 5, 'fixable' => false, 'type' => 'ERROR']];
            return $report;
        }
        $errors = [];
        // Merge errors and warnings.
        foreach ($phpcs_file->get_errors() as $line => $line_errors) {
            foreach ($line_errors as $column => $col_errors) {
                $new_errors = [];
                foreach ($col_errors as $data) {
                    $new_errors[] = ['message' => $data['message'], 'source' => $data['source'], 'severity' => $data['severity'], 'fixable' => $data['fixable'], 'type' => 'ERROR'];
                }
                $errors[$line][$column] = $new_errors;
            }
            ksort($errors[$line]);
        }
        //end foreach
        foreach ($phpcs_file->get_warnings() as $line => $line_warnings) {
            foreach ($line_warnings as $column => $col_warnings) {
                $new_warnings = [];
                foreach ($col_warnings as $data) {
                    $new_warnings[] = ['message' => $data['message'], 'source' => $data['source'], 'severity' => $data['severity'], 'fixable' => $data['fixable'], 'type' => 'WARNING'];
                }
                if (isset($errors[$line]) === false) {
                    $errors[$line] = [];
                }
                if (isset($errors[$line][$column]) === true) {
                    $errors[$line][$column] = array_merge($new_warnings, $errors[$line][$column]);
                } else {
                    $errors[$line][$column] = $new_warnings;
                }
            }
            //end foreach
            ksort($errors[$line]);
        }
        //end foreach
        ksort($errors);
        $report['messages'] = $errors;
        return $report;
    }
    //end prepareFileReport()
}
//end class