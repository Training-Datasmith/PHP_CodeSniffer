<?php

declare (strict_types=1);
/**
 * Responsible for running PHPCS and PHPCBF.
 *
 * After creating an object of this class, you probably just want to
 * call runPHPCS() or runPHPCBF().
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer;

use Php_code_Sniffer\Exceptions\Deep_Exit_Exception;
use Php_code_Sniffer\Exceptions\RuntimeException;
use Php_code_Sniffer\Files\Dummy_File;
use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Files\File_List;
use Php_code_Sniffer\Util\Cache;
use Php_code_Sniffer\Util\Common;
use Php_code_Sniffer\Util\Standards;
class Runner
{
    /**
     * The config data for the run.
     *
     * @var \PHP_CodeSniffer\Config
     */
    public $config;
    /**
     * The ruleset used for the run.
     *
     * @var \PHP_CodeSniffer\Ruleset
     */
    public $ruleset;
    /**
     * The reporter used for generating reports after the run.
     *
     * @var \PHP_CodeSniffer\Reporter
     */
    public $reporter;
    /**
     * Run the PHPCS script.
     *
     * @return array
     */
    public function run_phpcs()
    {
        $this->register_out_of_memory_shutdown_message('phpcs');
        try {
            Util\Timing::start_timing();
            Runner::check_requirements();
            if (defined('PHP_CODESNIFFER_CBF') === false) {
                define('PHP_CODESNIFFER_CBF', false);
            }
            // Creating the Config object populates it with all required settings
            // based on the CLI arguments provided to the script and any config
            // values the user has set.
            $this->config = new Config();
            // Init the run and load the rulesets to set additional config vars.
            $this->init();
            // Print a list of sniffs in each of the supplied standards.
            // We fudge the config here so that each standard is explained in isolation.
            if ($this->config->explain === true) {
                $standards = $this->config->standards;
                foreach ($standards as $standard) {
                    $this->config->standards = [$standard];
                    $ruleset = new Ruleset($this->config);
                    $ruleset->explain();
                }
                return 0;
            }
            // Generate documentation for each of the supplied standards.
            if ($this->config->generator !== null) {
                $standards = $this->config->standards;
                foreach ($standards as $standard) {
                    $this->config->standards = [$standard];
                    $ruleset = new Ruleset($this->config);
                    $class = 'PHP_CodeSniffer\Generators\\' . $this->config->generator;
                    $generator = new $class($ruleset);
                    $generator->generate();
                }
                return 0;
            }
            // Other report formats don't really make sense in interactive mode
            // so we hard-code the full report here and when outputting.
            // We also ensure parallel processing is off because we need to do one file at a time.
            if ($this->config->interactive === true) {
                $this->config->reports = ['full' => null];
                $this->config->parallel = 1;
                $this->config->show_progress = false;
            }
            // Disable caching if we are processing STDIN as we can't be 100%
            // sure where the file came from or if it will change in the future.
            if ($this->config->stdin === true) {
                $this->config->cache = false;
            }
            $num_errors = $this->run();
            // Print all the reports for this run.
            $to_screen = $this->reporter->print_reports();
            // Only print timer output if no reports were
            // printed to the screen so we don't put additional output
            // in something like an XML report. If we are printing to screen,
            // the report types would have already worked out who should
            // print the timer info.
            if ($this->config->interactive === false && ($to_screen === false || $this->reporter->total_errors + $this->reporter->total_warnings === 0 && $this->config->show_progress === true)) {
                Util\Timing::print_run_time();
            }
        } catch (Deep_Exit_Exception $e) {
            echo $e->get_message();
            return $e->get_code();
        }
        //end try
        if ($num_errors === 0) {
            // No errors found.
            return 0;
        }
        if ($this->reporter->total_fixable === 0) {
            // Errors found, but none of them can be fixed by PHPCBF.
            return 1;
        }
        // Errors found, and some can be fixed by PHPCBF.
        return 2;
    }
    //end runPHPCS()
    /**
     * Run the PHPCBF script.
     *
     * @return array
     */
    public function run_phpcbf()
    {
        $this->register_out_of_memory_shutdown_message('phpcbf');
        if (defined('PHP_CODESNIFFER_CBF') === false) {
            define('PHP_CODESNIFFER_CBF', true);
        }
        try {
            Util\Timing::start_timing();
            Runner::check_requirements();
            // Creating the Config object populates it with all required settings
            // based on the CLI arguments provided to the script and any config
            // values the user has set.
            $this->config = new Config();
            // When processing STDIN, we can't output anything to the screen
            // or it will end up mixed in with the file output.
            if ($this->config->stdin === true) {
                $this->config->verbosity = 0;
            }
            // Init the run and load the rulesets to set additional config vars.
            $this->init();
            // When processing STDIN, we only process one file at a time and
            // we don't process all the way through, so we can't use the parallel
            // running system.
            if ($this->config->stdin === true) {
                $this->config->parallel = 1;
            }
            // Override some of the command line settings that might break the fixes.
            $this->config->generator = null;
            $this->config->explain = false;
            $this->config->interactive = false;
            $this->config->cache = false;
            $this->config->show_sources = false;
            $this->config->record_errors = false;
            $this->config->report_file = null;
            $this->config->reports = ['cbf' => null];
            // If a standard tries to set command line arguments itself, some
            // may be blocked because PHPCBF is running, so stop the script
            // dying if any are found.
            $this->config->die_on_unknown_arg = false;
            $this->run();
            $this->reporter->print_reports();
            echo PHP_EOL;
            Util\Timing::print_run_time();
        } catch (Deep_Exit_Exception $e) {
            echo $e->get_message();
            return $e->get_code();
        }
        //end try
        if ($this->reporter->total_fixed === 0) {
            // Nothing was fixed by PHPCBF.
            if ($this->reporter->total_fixable === 0) {
                // Nothing found that could be fixed.
                return 0;
            }
            // Something failed to fix.
            return 2;
        }
        if ($this->reporter->total_fixable === 0) {
            // PHPCBF fixed all fixable errors.
            return 1;
        }
        // PHPCBF fixed some fixable errors, but others failed to fix.
        return 2;
    }
    //end runPHPCBF()
    /**
     * Exits if the minimum requirements of PHP_CodeSniffer are not met.
     *
     * @return void
     * @throws \PHP_CodeSniffer\Exceptions\DeepExitException If the requirements are not met.
     */
    public function check_requirements()
    {
        $required_extensions = ['tokenizer', 'xmlwriter', 'SimpleXML'];
        $missing_extensions = [];
        foreach ($required_extensions as $extension) {
            if (extension_loaded($extension) === false) {
                $missing_extensions[] = $extension;
            }
        }
        if (empty($missing_extensions) === false) {
            $last = array_pop($required_extensions);
            $required = implode(', ', $required_extensions);
            $required .= ' and ' . $last;
            if (count($missing_extensions) === 1) {
                $missing = $missing_extensions[0];
            } else {
                $last = array_pop($missing_extensions);
                $missing = implode(', ', $missing_extensions);
                $missing .= ' and ' . $last;
            }
            $error = 'ERROR: PHP_CodeSniffer requires the %s extensions to be enabled. Please enable %s.' . PHP_EOL;
            $error = sprintf($error, $required, $missing);
            throw new Deep_Exit_Exception($error, 3);
        }
    }
    //end checkRequirements()
    /**
     * Init the rulesets and other high-level settings.
     *
     * @return void
     * @throws \PHP_CodeSniffer\Exceptions\DeepExitException If a referenced standard is not installed.
     */
    public function init()
    {
        if (defined('PHP_CODESNIFFER_CBF') === false) {
            define('PHP_CODESNIFFER_CBF', false);
        }
        // Ensure this option is enabled or else line endings will not always
        // be detected properly for files created on a Mac with the /r line ending.
        @ini_set('auto_detect_line_endings', true);
        // Disable the PCRE JIT as this caused issues with parallel running.
        ini_set('pcre.jit', false);
        // Check that the standards are valid.
        foreach ($this->config->standards as $standard) {
            if (Util\Standards::is_installed_standard($standard) === false) {
                // They didn't select a valid coding standard, so help them
                // out by letting them know which standards are installed.
                $error = 'ERROR: the "' . $standard . '" coding standard is not installed. ';
                ob_start();
                Util\Standards::print_installed_standards();
                $error .= ob_get_contents();
                ob_end_clean();
                throw new Deep_Exit_Exception($error, 3);
            }
        }
        // Saves passing the Config object into other objects that only need
        // the verbosity flag for debug output.
        if (defined('PHP_CODESNIFFER_VERBOSITY') === false) {
            define('PHP_CODESNIFFER_VERBOSITY', $this->config->verbosity);
        }
        // Create this class so it is autoloaded and sets up a bunch
        // of PHP_CodeSniffer-specific token type constants.
        new Util\Tokens();
        // Allow autoloading of custom files inside installed standards.
        $installed_standards = Standards::get_installed_standard_details();
        foreach ($installed_standards as $details) {
            Autoload::add_search_path($details['path'], $details['namespace']);
        }
        // The ruleset contains all the information about how the files
        // should be checked and/or fixed.
        try {
            $this->ruleset = new Ruleset($this->config);
        } catch (RuntimeException $e) {
            $error = 'ERROR: ' . $e->get_message() . PHP_EOL . PHP_EOL;
            $error .= $this->config->print_short_usage(true);
            throw new Deep_Exit_Exception($error, 3);
        }
    }
    //end init()
    /**
     * Performs the run.
     *
     * @return int The number of errors and warnings found.
     * @throws \PHP_CodeSniffer\Exceptions\DeepExitException
     * @throws \PHP_CodeSniffer\Exceptions\RuntimeException
     */
    private function run()
    {
        // The class that manages all reporters for the run.
        $this->reporter = new Reporter($this->config);
        // Include bootstrap files.
        foreach ($this->config->bootstrap as $bootstrap) {
            include $bootstrap;
        }
        if ($this->config->stdin === true) {
            $file_contents = $this->config->stdin_content;
            if ($file_contents === null) {
                $handle = fopen('php://stdin', 'r');
                stream_set_blocking($handle, true);
                $file_contents = stream_get_contents($handle);
                fclose($handle);
            }
            $todo = new File_List($this->config, $this->ruleset);
            $dummy = new Dummy_File($file_contents, $this->ruleset, $this->config);
            $todo->add_file($dummy->path, $dummy);
        } else {
            if (empty($this->config->files) === true) {
                $error = 'ERROR: You must supply at least one file or directory to process.' . PHP_EOL . PHP_EOL;
                $error .= $this->config->print_short_usage(true);
                throw new Deep_Exit_Exception($error, 3);
            }
            if (PHP_CODESNIFFER_VERBOSITY > 0) {
                echo 'Creating file list... ';
            }
            $todo = new File_List($this->config, $this->ruleset);
            if (PHP_CODESNIFFER_VERBOSITY > 0) {
                $num_files = count($todo);
                echo "DONE ({$num_files} files in queue)" . PHP_EOL;
            }
            if ($this->config->cache === true) {
                if (PHP_CODESNIFFER_VERBOSITY > 0) {
                    echo 'Loading cache... ';
                }
                Cache::load($this->ruleset, $this->config);
                if (PHP_CODESNIFFER_VERBOSITY > 0) {
                    $size = Cache::get_size();
                    echo "DONE ({$size} files in cache)" . PHP_EOL;
                }
            }
        }
        //end if
        // Turn all sniff errors into exceptions.
        set_error_handler([$this, 'handleErrors']);
        // If verbosity is too high, turn off parallelism so the
        // debug output is clean.
        if (PHP_CODESNIFFER_VERBOSITY > 1) {
            $this->config->parallel = 1;
        }
        // If the PCNTL extension isn't installed, we can't fork.
        if (function_exists('pcntl_fork') === false) {
            $this->config->parallel = 1;
        }
        $last_dir = '';
        $num_files = count($todo);
        if ($this->config->parallel === 1) {
            // Running normally.
            $num_processed = 0;
            foreach ($todo as $path => $file) {
                if ($file->ignored === false) {
                    $curr_dir = dirname($path);
                    if ($last_dir !== $curr_dir) {
                        if (PHP_CODESNIFFER_VERBOSITY > 0) {
                            echo 'Changing into directory ' . Common::strip_basepath($curr_dir, $this->config->basepath) . PHP_EOL;
                        }
                        $last_dir = $curr_dir;
                    }
                    $this->process_file($file);
                } elseif (PHP_CODESNIFFER_VERBOSITY > 0) {
                    echo 'Skipping ' . basename($file->path) . PHP_EOL;
                }
                $num_processed++;
                $this->print_progress($file, $num_files, $num_processed);
            }
        } else {
            // Batching and forking.
            $child_procs = [];
            $num_per_batch = ceil($num_files / $this->config->parallel);
            for ($batch = 0; $batch < $this->config->parallel; $batch++) {
                $start_at = $batch * $num_per_batch;
                if ($start_at >= $num_files) {
                    break;
                }
                $end_at = $start_at + $num_per_batch;
                if ($end_at > $num_files) {
                    $end_at = $num_files;
                }
                $child_out_filename = tempnam(sys_get_temp_dir(), 'phpcs-child');
                $pid = pcntl_fork();
                if ($pid === -1) {
                    throw new RuntimeException('Failed to create child process');
                }
                if ($pid !== 0) {
                    $child_procs[$pid] = $child_out_filename;
                } else {
                    // Move forward to the start of the batch.
                    $todo->rewind();
                    for ($i = 0; $i < $start_at; $i++) {
                        $todo->next();
                    }
                    // Reset the reporter to make sure only figures from this
                    // file batch are recorded.
                    $this->reporter->total_files = 0;
                    $this->reporter->total_errors = 0;
                    $this->reporter->total_warnings = 0;
                    $this->reporter->total_fixable = 0;
                    $this->reporter->total_fixed = 0;
                    // Process the files.
                    $paths_processed = [];
                    ob_start();
                    for ($i = $start_at; $i < $end_at; $i++) {
                        $path = $todo->key();
                        $file = $todo->current();
                        if ($file->ignored === true) {
                            $todo->next();
                            continue;
                        }
                        $curr_dir = dirname($path);
                        if ($last_dir !== $curr_dir) {
                            if (PHP_CODESNIFFER_VERBOSITY > 0) {
                                echo 'Changing into directory ' . Common::strip_basepath($curr_dir, $this->config->basepath) . PHP_EOL;
                            }
                            $last_dir = $curr_dir;
                        }
                        $this->process_file($file);
                        $paths_processed[] = $path;
                        $todo->next();
                    }
                    //end for
                    $debug_output = ob_get_contents();
                    ob_end_clean();
                    // Write information about the run to the filesystem
                    // so it can be picked up by the main process.
                    $child_output = ['totalFiles' => $this->reporter->total_files, 'totalErrors' => $this->reporter->total_errors, 'totalWarnings' => $this->reporter->total_warnings, 'totalFixable' => $this->reporter->total_fixable, 'totalFixed' => $this->reporter->total_fixed];
                    $output = '<' . '?php' . "\n" . ' $childOutput = ';
                    $output .= var_export($child_output, true);
                    $output .= ";\n\$debugOutput = ";
                    $output .= var_export($debug_output, true);
                    if ($this->config->cache === true) {
                        $child_cache = [];
                        foreach ($paths_processed as $path) {
                            $child_cache[$path] = Cache::get($path);
                        }
                        $output .= ";\n\$childCache = ";
                        $output .= var_export($child_cache, true);
                    }
                    $output .= ";\n?" . '>';
                    file_put_contents($child_out_filename, $output);
                    exit;
                }
                //end if
            }
            //end for
            $success = $this->process_child_procs($child_procs);
            if ($success === false) {
                throw new RuntimeException('One or more child processes failed to run');
            }
        }
        //end if
        restore_error_handler();
        if (PHP_CODESNIFFER_VERBOSITY === 0 && $this->config->interactive === false && $this->config->show_progress === true) {
            echo PHP_EOL . PHP_EOL;
        }
        if ($this->config->cache === true) {
            Cache::save();
        }
        $ignore_warnings = Config::get_config_data('ignore_warnings_on_exit');
        $ignore_errors = Config::get_config_data('ignore_errors_on_exit');
        $return = $this->reporter->total_errors + $this->reporter->total_warnings;
        if ($ignore_errors !== null) {
            $ignore_errors = (bool) $ignore_errors;
            if ($ignore_errors === true) {
                $return -= $this->reporter->total_errors;
            }
        }
        if ($ignore_warnings !== null) {
            $ignore_warnings = (bool) $ignore_warnings;
            if ($ignore_warnings === true) {
                $return -= $this->reporter->total_warnings;
            }
        }
        return $return;
    }
    //end run()
    /**
     * Converts all PHP errors into exceptions.
     *
     * This method forces a sniff to stop processing if it is not
     * able to handle a specific piece of code, instead of continuing
     * and potentially getting into a loop.
     *
     * @param int    $code    The level of error raised.
     * @param string $message The error message.
     * @param string $file    The path of the file that raised the error.
     * @param int    $line    The line number the error was raised at.
     *
     * @return void
     * @throws \PHP_CodeSniffer\Exceptions\RuntimeException
     */
    public function handle_errors($code, $message, $file, $line)
    {
        if ((error_reporting() & $code) === 0) {
            // This type of error is being muted.
            return true;
        }
        throw new RuntimeException("{$message} in {$file} on line {$line}");
    }
    //end handleErrors()
    /**
     * Processes a single file, including checking and fixing.
     *
     * @param \PHP_CodeSniffer\Files\File $file The file to be processed.
     *
     * @return void
     * @throws \PHP_CodeSniffer\Exceptions\DeepExitException
     */
    public function process_file(\Php_code_Sniffer\Files\File $file)
    {
        if (PHP_CODESNIFFER_VERBOSITY > 0) {
            $start_time = microtime(true);
            echo 'Processing ' . basename($file->path) . ' ';
            if (PHP_CODESNIFFER_VERBOSITY > 1) {
                echo PHP_EOL;
            }
        }
        try {
            $file->process();
            if (PHP_CODESNIFFER_VERBOSITY > 0) {
                $time_taken = (microtime(true) - $start_time) * 1000;
                if ($time_taken < 1000) {
                    $time_taken = round($time_taken);
                    echo "DONE in {$time_taken}ms";
                } else {
                    $time_taken = round($time_taken / 1000, 2);
                    echo "DONE in {$time_taken} secs";
                }
                if (PHP_CODESNIFFER_CBF === true) {
                    $errors = $file->get_fixable_count();
                    echo " ({$errors} fixable violations)" . PHP_EOL;
                } else {
                    $errors = $file->get_error_count();
                    $warnings = $file->get_warning_count();
                    echo " ({$errors} errors, {$warnings} warnings)" . PHP_EOL;
                }
            }
        } catch (\Exception $e) {
            $error = 'An error occurred during processing; checking has been aborted. The error message was: ' . $e->get_message();
            // Determine which sniff caused the error.
            $sniff_stack = null;
            $next_stack = null;
            foreach ($e->get_trace() as $step) {
                if (isset($step['file']) === false) {
                    continue;
                }
                if (empty($sniff_stack) === false) {
                    $next_stack = $step;
                    break;
                }
                if (substr($step['file'], -9) === 'Sniff.php') {
                    $sniff_stack = $step;
                    continue;
                }
            }
            if (empty($sniff_stack) === false) {
                if (empty($next_stack) === false && isset($next_stack['class']) === true && substr($next_stack['class'], -5) === 'Sniff') {
                    $sniff_code = Common::get_sniff_code($next_stack['class']);
                } else {
                    $sniff_code = substr(strrchr(str_replace('\\', '/', $sniff_stack['file']), '/'), 1);
                }
                $error .= sprintf(PHP_EOL . 'The error originated in the %s sniff on line %s.', $sniff_code, $sniff_stack['line']);
            }
            $file->add_error_on_line($error, 1, 'Internal.Exception');
        }
        //end try
        $this->reporter->cache_file_report($file, $this->config);
        if ($this->config->interactive === true) {
            /*
                Running interactively.
                Print the error report for the current file and then wait for user input.
            */
            // Get current violations and then clear the list to make sure
            // we only print violations for a single file each time.
            $num_errors = null;
            while ($num_errors !== 0) {
                $num_errors = $file->get_error_count() + $file->get_warning_count();
                if ($num_errors === 0) {
                    continue;
                }
                $this->reporter->print_report('full');
                echo '<ENTER> to recheck, [s] to skip or [q] to quit : ';
                $input = fgets(STDIN);
                $input = trim($input);
                switch ($input) {
                    case 's':
                        break 2;
                    case 'q':
                        throw new Deep_Exit_Exception('', 0);
                    default:
                        // Repopulate the sniffs because some of them save their state
                        // and only clear it when the file changes, but we are rechecking
                        // the same file.
                        $file->ruleset->populate_token_listeners();
                        $file->reload_content();
                        $file->process();
                        $this->reporter->cache_file_report($file, $this->config);
                        break;
                }
            }
            //end while
        }
        //end if
        // Clean up the file to save (a lot of) memory.
        $file->clean_up();
    }
    //end processFile()
    /**
     * Waits for child processes to complete and cleans up after them.
     *
     * The reporting information returned by each child process is merged
     * into the main reporter class.
     *
     * @param array $childProcs An array of child processes to wait for.
     *
     * @return bool
     */
    private function process_child_procs(array $child_procs)
    {
        $num_processed = 0;
        $total_batches = count($child_procs);
        $success = true;
        while (count($child_procs) > 0) {
            $pid = pcntl_waitpid(0, $status);
            if ($pid <= 0) {
                continue;
            }
            $child_process_status = pcntl_wexitstatus($status);
            if ($child_process_status !== 0) {
                $success = false;
            }
            $out = $child_procs[$pid];
            unset($child_procs[$pid]);
            if (file_exists($out) === false) {
                continue;
            }
            include $out;
            unlink($out);
            $num_processed++;
            if (isset($child_output) === false) {
                // The child process died, so the run has failed.
                $file = new Dummy_File('', $this->ruleset, $this->config);
                $file->set_error_counts(1, 0, 0, 0);
                $this->print_progress($file, $total_batches, $num_processed);
                $success = false;
                continue;
            }
            $this->reporter->total_files += $child_output['totalFiles'];
            $this->reporter->total_errors += $child_output['totalErrors'];
            $this->reporter->total_warnings += $child_output['totalWarnings'];
            $this->reporter->total_fixable += $child_output['totalFixable'];
            $this->reporter->total_fixed += $child_output['totalFixed'];
            if (isset($debug_output) === true) {
                echo $debug_output;
            }
            if (isset($child_cache) === true) {
                foreach ($child_cache as $path => $cache) {
                    Cache::set($path, $cache);
                }
            }
            // Fake a processed file so we can print progress output for the batch.
            $file = new Dummy_File('', $this->ruleset, $this->config);
            $file->set_error_counts($child_output['totalErrors'], $child_output['totalWarnings'], $child_output['totalFixable'], $child_output['totalFixed']);
            $this->print_progress($file, $total_batches, $num_processed);
        }
        //end while
        return $success;
    }
    //end processChildProcs()
    /**
     * Print progress information for a single processed file.
     *
     * @param \PHP_CodeSniffer\Files\File $file         The file that was processed.
     * @param int                         $numFiles     The total number of files to process.
     * @param int                         $numProcessed The number of files that have been processed,
     *                                                  including this one.
     *
     * @return void
     */
    public function print_progress(File $file, $num_files, $num_processed)
    {
        if (PHP_CODESNIFFER_VERBOSITY > 0 || $this->config->show_progress === false) {
            return;
        }
        // Show progress information.
        if ($file->ignored === true) {
            echo 'S';
        } else {
            $errors = $file->get_error_count();
            $warnings = $file->get_warning_count();
            $fixable = $file->get_fixable_count();
            $fixed = $file->get_fixed_count();
            if (PHP_CODESNIFFER_CBF === true) {
                // Files with fixed errors or warnings are F (green).
                // Files with unfixable errors or warnings are E (red).
                // Files with no errors or warnings are . (black).
                if ($fixable > 0) {
                    if ($this->config->colors === true) {
                        echo "\x1b[31m";
                    }
                    echo 'E';
                    if ($this->config->colors === true) {
                        echo "\x1b[0m";
                    }
                } elseif ($fixed > 0) {
                    if ($this->config->colors === true) {
                        echo "\x1b[32m";
                    }
                    echo 'F';
                    if ($this->config->colors === true) {
                        echo "\x1b[0m";
                    }
                } else {
                    echo '.';
                }
                //end if
            } else {
                // Files with errors are E (red).
                // Files with fixable errors are E (green).
                // Files with warnings are W (yellow).
                // Files with fixable warnings are W (green).
                // Files with no errors or warnings are . (black).
                if ($errors > 0) {
                    if ($this->config->colors === true) {
                        if ($fixable > 0) {
                            echo "\x1b[32m";
                        } else {
                            echo "\x1b[31m";
                        }
                    }
                    echo 'E';
                    if ($this->config->colors === true) {
                        echo "\x1b[0m";
                    }
                } elseif ($warnings > 0) {
                    if ($this->config->colors === true) {
                        if ($fixable > 0) {
                            echo "\x1b[32m";
                        } else {
                            echo "\x1b[33m";
                        }
                    }
                    echo 'W';
                    if ($this->config->colors === true) {
                        echo "\x1b[0m";
                    }
                } else {
                    echo '.';
                }
                //end if
            }
            //end if
        }
        //end if
        $num_per_line = 60;
        if ($num_processed !== $num_files && $num_processed % $num_per_line !== 0) {
            return;
        }
        $percent = round($num_processed / $num_files * 100);
        $padding = strlen($num_files) - strlen($num_processed);
        if ($num_processed === $num_files && $num_files > $num_per_line && $num_processed % $num_per_line !== 0) {
            $padding += $num_per_line - ($num_files - floor($num_files / $num_per_line) * $num_per_line);
        }
        echo str_repeat(' ', $padding) . " {$num_processed} / {$num_files} ({$percent}%)" . PHP_EOL;
    }
    //end printProgress()
    /**
     * Registers a PHP shutdown function to provide a more informative out of memory error.
     *
     * @param string $command The command which was used to initiate the PHPCS run.
     *
     * @return void
     */
    private function register_out_of_memory_shutdown_message($command)
    {
        // Allocate all needed memory beforehand as much as possible.
        $error_msg = PHP_EOL . 'The PHP_CodeSniffer "%1$s" command ran out of memory.' . PHP_EOL;
        $error_msg .= 'Either raise the "memory_limit" of PHP in the php.ini file or raise the memory limit at runtime' . PHP_EOL;
        $error_msg .= 'using `%1$s -d memory_limit=512M` (replace 512M with the desired memory limit).' . PHP_EOL;
        $error_msg = sprintf($error_msg, $command);
        $memory_error = 'Allowed memory size of';
        $error_array = ['type' => 42, 'message' => 'Some random dummy string to take up memory and take up some more memory and some more', 'file' => 'Another random string, which would be a filename this time. Should be relatively long to allow for deeply nested files', 'line' => 31427];
        register_shutdown_function(static function () use ($error_msg, $memory_error, $error_array) {
            $error_array = error_get_last();
            if (is_array($error_array) === true && strpos($error_array['message'], $memory_error) !== false) {
                echo $error_msg;
            }
        });
    }
    //end registerOutOfMemoryShutdownMessage()
}
//end class