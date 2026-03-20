<?php

declare (strict_types=1);
/**
 * Stores the configuration used to run PHPCS and PHPCBF.
 *
 * Parses the command line to determine user supplied values
 * and provides functions to access data stored in config files.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer;

use Php_code_Sniffer\Exceptions\Deep_Exit_Exception;
use Php_code_Sniffer\Exceptions\RuntimeException;
use Php_code_Sniffer\Util\Common;
/**
 * Stores the configuration used to run PHPCS and PHPCBF.
 *
 * @property string[] $files           The files and directories to check.
 * @property string[] $standards       The standards being used for checking.
 * @property int      $verbosity       How verbose the output should be.
 *                                     0: no unnecessary output
 *                                     1: basic output for files being checked
 *                                     2: ruleset and file parsing output
 *                                     3: sniff execution output
 * @property bool     $interactive     Enable interactive checking mode.
 * @property bool     $parallel        Check files in parallel.
 * @property bool     $cache           Enable the use of the file cache.
 * @property bool     $cacheFile       A file where the cache data should be written
 * @property bool     $colors          Display colours in output.
 * @property bool     $explain         Explain the coding standards.
 * @property bool     $local           Process local files in directories only (no recursion).
 * @property bool     $showSources     Show sniff source codes in report output.
 * @property bool     $showProgress    Show basic progress information while running.
 * @property bool     $quiet           Quiet mode; disables progress and verbose output.
 * @property bool     $annotations     Process phpcs: annotations.
 * @property int      $tabWidth        How many spaces each tab is worth.
 * @property string   $encoding        The encoding of the files being checked.
 * @property string[] $sniffs          The sniffs that should be used for checking.
 *                                     If empty, all sniffs in the supplied standards will be used.
 * @property string[] $exclude         The sniffs that should be excluded from checking.
 *                                     If empty, all sniffs in the supplied standards will be used.
 * @property string[] $ignored         Regular expressions used to ignore files and folders during checking.
 * @property string   $reportFile      A file where the report output should be written.
 * @property string   $generator       The documentation generator to use.
 * @property string   $filter          The filter to use for the run.
 * @property string[] $bootstrap       One of more files to include before the run begins.
 * @property int      $reportWidth     The maximum number of columns that reports should use for output.
 *                                     Set to "auto" for have this value changed to the width of the terminal.
 * @property int      $errorSeverity   The minimum severity an error must have to be displayed.
 * @property int      $warningSeverity The minimum severity a warning must have to be displayed.
 * @property bool     $recordErrors    Record the content of error messages as well as error counts.
 * @property string   $suffix          A suffix to add to fixed files.
 * @property string   $basepath        A file system location to strip from the paths of files shown in reports.
 * @property bool     $stdin           Read content from STDIN instead of supplied files.
 * @property string   $stdinContent    Content passed directly to PHPCS on STDIN.
 * @property string   $stdinPath       The path to use for content passed on STDIN.
 *
 * @property array<string, string>      $extensions File extensions that should be checked, and what tokenizer to use.
 *                                                  E.g., array('inc' => 'PHP');
 * @property array<string, string|null> $reports    The reports to use for printing output after the run.
 *                                                  The format of the array is:
 *                                                      array(
 *                                                          'reportName1' => 'outputFile',
 *                                                          'reportName2' => null,
 *                                                      );
 *                                                  If the array value is NULL, the report will be written to the screen.
 *
 * @property string[] $unknown Any arguments gathered on the command line that are unknown to us.
 *                             E.g., using `phpcs -c` will give array('c');
 */
class Config
{
    /**
     * The current version.
     *
     * @var string
     */
    public const VERSION = '3.8.0';
    /**
     * Package stability; either stable, beta or alpha.
     *
     * @var string
     */
    public const STABILITY = 'stable';
    /**
     * Default report width when no report width is provided and 'auto' does not yield a valid width.
     *
     * @var int
     */
    public const DEFAULT_REPORT_WIDTH = 80;
    /**
     * An array of settings that PHPCS and PHPCBF accept.
     *
     * This array is not meant to be accessed directly. Instead, use the settings
     * as if they are class member vars so the __get() and __set() magic methods
     * can be used to validate the values. For example, to set the verbosity level to
     * level 2, use $this->verbosity = 2; instead of accessing this property directly.
     *
     * Each of these settings is described in the class comment property list.
     *
     * @var array<string, mixed>
     */
    private $settings = ['files' => null, 'standards' => null, 'verbosity' => null, 'interactive' => null, 'parallel' => null, 'cache' => null, 'cacheFile' => null, 'colors' => null, 'explain' => null, 'local' => null, 'showSources' => null, 'showProgress' => null, 'quiet' => null, 'annotations' => null, 'tabWidth' => null, 'encoding' => null, 'extensions' => null, 'sniffs' => null, 'exclude' => null, 'ignored' => null, 'reportFile' => null, 'generator' => null, 'filter' => null, 'bootstrap' => null, 'reports' => null, 'basepath' => null, 'reportWidth' => null, 'errorSeverity' => null, 'warningSeverity' => null, 'recordErrors' => null, 'suffix' => null, 'stdin' => null, 'stdinContent' => null, 'stdinPath' => null, 'unknown' => null];
    /**
     * Whether or not to kill the process when an unknown command line arg is found.
     *
     * If FALSE, arguments that are not command line options or file/directory paths
     * will be ignored and execution will continue. These values will be stored in
     * $this->unknown.
     *
     * @var boolean
     */
    public $die_on_unknown_arg;
    /**
     * The current command line arguments we are processing.
     *
     * @var string[]
     */
    private $cli_args = [];
    /**
     * Command line values that the user has supplied directly.
     *
     * @var array<string, TRUE>
     */
    private static $overridden_defaults = [];
    /**
     * Config file data that has been loaded for the run.
     *
     * @var array<string, string>
     */
    private static $config_data;
    /**
     * The full path to the config data file that has been loaded.
     *
     * @var string
     */
    private static $config_data_file;
    /**
     * Automatically discovered executable utility paths.
     *
     * @var array<string, string>
     */
    private static $executable_paths = [];
    /**
     * Get the value of an inaccessible property.
     *
     * @param string $name The name of the property.
     *
     * @return mixed
     * @throws \PHP_CodeSniffer\Exceptions\RuntimeException If the setting name is invalid.
     */
    public function __get($name)
    {
        if (array_key_exists($name, $this->settings) === false) {
            throw new RuntimeException("ERROR: unable to get value of property \"{$name}\"");
        }
        return $this->settings[$name];
    }
    //end __get()
    /**
     * Set the value of an inaccessible property.
     *
     * @param string $name  The name of the property.
     * @param mixed  $value The value of the property.
     *
     * @return void
     * @throws \PHP_CodeSniffer\Exceptions\RuntimeException If the setting name is invalid.
     */
    public function __set($name, $value)
    {
        if (array_key_exists($name, $this->settings) === false) {
            throw new RuntimeException("Can't __set() {$name}; setting doesn't exist");
        }
        switch ($name) {
            case 'reportWidth':
                // Support auto terminal width.
                if ($value === 'auto' && function_exists('shell_exec') === true) {
                    $dimensions = shell_exec('stty size 2>&1');
                    if (is_string($dimensions) === true && preg_match('|\d+ (\d+)|', $dimensions, $matches) === 1) {
                        $value = (int) $matches[1];
                        break;
                    }
                }
                if (is_int($value) === true) {
                    $value = abs($value);
                } elseif (is_string($value) === true && preg_match('`^\d+$`', $value) === 1) {
                    $value = (int) $value;
                } else {
                    $value = self::DEFAULT_REPORT_WIDTH;
                }
                break;
            case 'standards':
                $cleaned = [];
                // Check if the standard name is valid, or if the case is invalid.
                $installed_standards = Util\Standards::get_installed_standards();
                foreach ($value as $standard) {
                    foreach ($installed_standards as $valid_standard) {
                        if (strtolower($standard) === strtolower($valid_standard)) {
                            $standard = $valid_standard;
                            break;
                        }
                    }
                    $cleaned[] = $standard;
                }
                $value = $cleaned;
                break;
            default:
                // No validation required.
                break;
        }
        //end switch
        $this->settings[$name] = $value;
    }
    //end __set()
    /**
     * Check if the value of an inaccessible property is set.
     *
     * @param string $name The name of the property.
     *
     * @return bool
     */
    public function __isset($name)
    {
        return isset($this->settings[$name]);
    }
    //end __isset()
    /**
     * Unset the value of an inaccessible property.
     *
     * @param string $name The name of the property.
     *
     * @return void
     */
    public function __unset($name)
    {
        $this->settings[$name] = null;
    }
    //end __unset()
    /**
     * Get the array of all config settings.
     *
     * @return array<string, mixed>
     */
    public function get_settings()
    {
        return $this->settings;
    }
    //end getSettings()
    /**
     * Set the array of all config settings.
     *
     * @param array<string, mixed> $settings The array of config settings.
     *
     * @return void
     */
    public function set_settings($settings)
    {
        return $this->settings = $settings;
    }
    //end setSettings()
    /**
     * Creates a Config object and populates it with command line values.
     *
     * @param array $cliArgs         An array of values gathered from CLI args.
     * @param bool  $dieOnUnknownArg Whether or not to kill the process when an
     *                               unknown command line arg is found.
     */
    public function __construct(array $cli_args = [], $die_on_unknown_arg = true)
    {
        if (defined('PHP_CODESNIFFER_IN_TESTS') === true) {
            // Let everything through during testing so that we can
            // make use of PHPUnit command line arguments as well.
            $this->die_on_unknown_arg = false;
        } else {
            $this->die_on_unknown_arg = $die_on_unknown_arg;
        }
        if (empty($cli_args) === true) {
            $cli_args = $_SERVER['argv'];
            array_shift($cli_args);
        }
        $this->restore_defaults();
        $this->set_command_line_values($cli_args);
        if (isset(self::$overridden_defaults['standards']) === false) {
            // They did not supply a standard to use.
            // Look for a default ruleset in the current directory or higher.
            $current_dir = getcwd();
            $default_files = ['.phpcs.xml', 'phpcs.xml', '.phpcs.xml.dist', 'phpcs.xml.dist'];
            do {
                foreach ($default_files as $default_filename) {
                    $default = $current_dir . DIRECTORY_SEPARATOR . $default_filename;
                    if (is_file($default) === true) {
                        $this->standards = [$default];
                        break 2;
                    }
                }
                $last_dir = $current_dir;
                $current_dir = dirname($current_dir);
            } while ($current_dir !== '.' && $current_dir !== $last_dir && Common::is_readable($current_dir) === true);
        }
        //end if
        if (defined('STDIN') === false || stripos(PHP_OS, 'WIN') === 0) {
            return;
        }
        $handle = fopen('php://stdin', 'r');
        // Check for content on STDIN.
        if ($this->stdin === true || Util\Common::is_stdin_atty() === false && feof($handle) === false) {
            $read_streams = [$handle];
            $write_steams = null;
            $file_contents = '';
            while (is_resource($handle) === true && feof($handle) === false) {
                // Set a timeout of 200ms.
                if (stream_select($read_streams, $write_steams, $write_steams, 0, 200000) === 0) {
                    break;
                }
                $file_contents .= fgets($handle);
            }
            if (trim($file_contents) !== '') {
                $this->stdin = true;
                $this->stdin_content = $file_contents;
                self::$overridden_defaults['stdin'] = true;
                self::$overridden_defaults['stdinContent'] = true;
            }
        }
        //end if
        fclose($handle);
    }
    //end __construct()
    /**
     * Set the command line values.
     *
     * @param array $args An array of command line arguments to set.
     *
     * @return void
     */
    public function set_command_line_values($args)
    {
        $this->cli_args = $args;
        $num_args = count($args);
        for ($i = 0; $i < $num_args; $i++) {
            $arg = $this->cli_args[$i];
            if ($arg === '') {
                continue;
            }
            if ($arg[0] === '-') {
                if ($arg === '-') {
                    // Asking to read from STDIN.
                    $this->stdin = true;
                    self::$overridden_defaults['stdin'] = true;
                    continue;
                }
                if ($arg === '--') {
                    // Empty argument, ignore it.
                    continue;
                }
                if ($arg[1] === '-') {
                    $this->process_long_argument(substr($arg, 2), $i);
                } else {
                    $switches = str_split($arg);
                    foreach ($switches as $switch) {
                        if ($switch === '-') {
                            continue;
                        }
                        $this->process_short_argument($switch, $i);
                    }
                }
            } else {
                $this->process_unknown_argument($arg, $i);
            }
            //end if
        }
        //end for
    }
    //end setCommandLineValues()
    /**
     * Restore default values for all possible command line arguments.
     *
     * @return void
     */
    public function restore_defaults()
    {
        $this->files = [];
        $this->standards = ['PEAR'];
        $this->verbosity = 0;
        $this->interactive = false;
        $this->cache = false;
        $this->cache_file = null;
        $this->colors = false;
        $this->explain = false;
        $this->local = false;
        $this->show_sources = false;
        $this->show_progress = false;
        $this->quiet = false;
        $this->annotations = true;
        $this->parallel = 1;
        $this->tab_width = 0;
        $this->encoding = 'utf-8';
        $this->extensions = ['php' => 'PHP', 'inc' => 'PHP', 'js' => 'JS', 'css' => 'CSS'];
        $this->sniffs = [];
        $this->exclude = [];
        $this->ignored = [];
        $this->report_file = null;
        $this->generator = null;
        $this->filter = null;
        $this->bootstrap = [];
        $this->basepath = null;
        $this->reports = ['full' => null];
        $this->report_width = 'auto';
        $this->error_severity = 5;
        $this->warning_severity = 5;
        $this->record_errors = true;
        $this->suffix = '';
        $this->stdin = false;
        $this->stdin_content = null;
        $this->stdin_path = null;
        $this->unknown = [];
        $standard = self::get_config_data('default_standard');
        if ($standard !== null) {
            $this->standards = explode(',', $standard);
        }
        $report_format = self::get_config_data('report_format');
        if ($report_format !== null) {
            $this->reports = [$report_format => null];
        }
        $tab_width = self::get_config_data('tab_width');
        if ($tab_width !== null) {
            $this->tab_width = (int) $tab_width;
        }
        $encoding = self::get_config_data('encoding');
        if ($encoding !== null) {
            $this->encoding = strtolower($encoding);
        }
        $severity = self::get_config_data('severity');
        if ($severity !== null) {
            $this->error_severity = (int) $severity;
            $this->warning_severity = (int) $severity;
        }
        $severity = self::get_config_data('error_severity');
        if ($severity !== null) {
            $this->error_severity = (int) $severity;
        }
        $severity = self::get_config_data('warning_severity');
        if ($severity !== null) {
            $this->warning_severity = (int) $severity;
        }
        $show_warnings = self::get_config_data('show_warnings');
        if ($show_warnings !== null) {
            $show_warnings = (bool) $show_warnings;
            if ($show_warnings === false) {
                $this->warning_severity = 0;
            }
        }
        $report_width = self::get_config_data('report_width');
        if ($report_width !== null) {
            $this->report_width = $report_width;
        }
        $show_progress = self::get_config_data('show_progress');
        if ($show_progress !== null) {
            $this->show_progress = (bool) $show_progress;
        }
        $quiet = self::get_config_data('quiet');
        if ($quiet !== null) {
            $this->quiet = (bool) $quiet;
        }
        $colors = self::get_config_data('colors');
        if ($colors !== null) {
            $this->colors = (bool) $colors;
        }
        if (defined('PHP_CODESNIFFER_IN_TESTS') === false) {
            $cache = self::get_config_data('cache');
            if ($cache !== null) {
                $this->cache = (bool) $cache;
            }
            $parallel = self::get_config_data('parallel');
            if ($parallel !== null) {
                $this->parallel = max((int) $parallel, 1);
            }
        }
    }
    //end restoreDefaults()
    /**
     * Processes a short (-e) command line argument.
     *
     * @param string $arg The command line argument.
     * @param int    $pos The position of the argument on the command line.
     *
     * @return void
     * @throws \PHP_CodeSniffer\Exceptions\DeepExitException
     */
    public function process_short_argument($arg, $pos)
    {
        switch ($arg) {
            case 'h':
            case '?':
                ob_start();
                $this->print_usage();
                $output = ob_get_contents();
                ob_end_clean();
                throw new Deep_Exit_Exception($output, 0);
            case 'i':
                ob_start();
                Util\Standards::print_installed_standards();
                $output = ob_get_contents();
                ob_end_clean();
                throw new Deep_Exit_Exception($output, 0);
            case 'v':
                if ($this->quiet === true) {
                    // Ignore when quiet mode is enabled.
                    break;
                }
                $this->verbosity++;
                self::$overridden_defaults['verbosity'] = true;
                break;
            case 'l':
                $this->local = true;
                self::$overridden_defaults['local'] = true;
                break;
            case 's':
                $this->show_sources = true;
                self::$overridden_defaults['showSources'] = true;
                break;
            case 'a':
                $this->interactive = true;
                self::$overridden_defaults['interactive'] = true;
                break;
            case 'e':
                $this->explain = true;
                self::$overridden_defaults['explain'] = true;
                break;
            case 'p':
                if ($this->quiet === true) {
                    // Ignore when quiet mode is enabled.
                    break;
                }
                $this->show_progress = true;
                self::$overridden_defaults['showProgress'] = true;
                break;
            case 'q':
                // Quiet mode disables a few other settings as well.
                $this->quiet = true;
                $this->show_progress = false;
                $this->verbosity = 0;
                self::$overridden_defaults['quiet'] = true;
                break;
            case 'm':
                $this->record_errors = false;
                self::$overridden_defaults['recordErrors'] = true;
                break;
            case 'd':
                $ini = explode('=', $this->cli_args[$pos + 1]);
                $this->cli_args[$pos + 1] = '';
                if (isset($ini[1]) === true) {
                    ini_set($ini[0], $ini[1]);
                } else {
                    ini_set($ini[0], true);
                }
                break;
            case 'n':
                if (isset(self::$overridden_defaults['warningSeverity']) === false) {
                    $this->warning_severity = 0;
                    self::$overridden_defaults['warningSeverity'] = true;
                }
                break;
            case 'w':
                if (isset(self::$overridden_defaults['warningSeverity']) === false) {
                    $this->warning_severity = $this->error_severity;
                    self::$overridden_defaults['warningSeverity'] = true;
                }
                break;
            default:
                if ($this->die_on_unknown_arg === false) {
                    $unknown = $this->unknown;
                    $unknown[] = $arg;
                    $this->unknown = $unknown;
                } else {
                    $this->process_unknown_argument('-' . $arg, $pos);
                }
        }
        //end switch
    }
    //end processShortArgument()
    /**
     * Processes a long (--example) command-line argument.
     *
     * @param string $arg The command line argument.
     * @param int    $pos The position of the argument on the command line.
     *
     * @return void
     * @throws \PHP_CodeSniffer\Exceptions\DeepExitException
     */
    public function process_long_argument($arg, $pos)
    {
        switch ($arg) {
            case 'help':
                ob_start();
                $this->print_usage();
                $output = ob_get_contents();
                ob_end_clean();
                throw new Deep_Exit_Exception($output, 0);
            case 'version':
                $output = 'PHP_CodeSniffer version ' . self::VERSION . ' (' . self::STABILITY . ') ';
                $output .= 'by Squiz (https://www.squiz.net)' . PHP_EOL;
                throw new Deep_Exit_Exception($output, 0);
            case 'colors':
                if (isset(self::$overridden_defaults['colors']) === true) {
                    break;
                }
                $this->colors = true;
                self::$overridden_defaults['colors'] = true;
                break;
            case 'no-colors':
                if (isset(self::$overridden_defaults['colors']) === true) {
                    break;
                }
                $this->colors = false;
                self::$overridden_defaults['colors'] = true;
                break;
            case 'cache':
                if (isset(self::$overridden_defaults['cache']) === true) {
                    break;
                }
                if (defined('PHP_CODESNIFFER_IN_TESTS') === false) {
                    $this->cache = true;
                    self::$overridden_defaults['cache'] = true;
                }
                break;
            case 'no-cache':
                if (isset(self::$overridden_defaults['cache']) === true) {
                    break;
                }
                $this->cache = false;
                self::$overridden_defaults['cache'] = true;
                break;
            case 'ignore-annotations':
                if (isset(self::$overridden_defaults['annotations']) === true) {
                    break;
                }
                $this->annotations = false;
                self::$overridden_defaults['annotations'] = true;
                break;
            case 'config-set':
                if (isset($this->cli_args[$pos + 1]) === false || isset($this->cli_args[$pos + 2]) === false) {
                    $error = 'ERROR: Setting a config option requires a name and value' . PHP_EOL . PHP_EOL;
                    $error .= $this->print_short_usage(true);
                    throw new Deep_Exit_Exception($error, 3);
                }
                $key = $this->cli_args[$pos + 1];
                $value = $this->cli_args[$pos + 2];
                $current = self::get_config_data($key);
                try {
                    $this->set_config_data($key, $value);
                } catch (\Exception $e) {
                    throw new Deep_Exit_Exception($e->get_message() . PHP_EOL, 3);
                }
                $output = 'Using config file: ' . self::$config_data_file . PHP_EOL . PHP_EOL;
                if ($current === null) {
                    $output .= "Config value \"{$key}\" added successfully" . PHP_EOL;
                } else {
                    $output .= "Config value \"{$key}\" updated successfully; old value was \"{$current}\"" . PHP_EOL;
                }
                throw new Deep_Exit_Exception($output, 0);
            case 'config-delete':
                if (isset($this->cli_args[$pos + 1]) === false) {
                    $error = 'ERROR: Deleting a config option requires the name of the option' . PHP_EOL . PHP_EOL;
                    $error .= $this->print_short_usage(true);
                    throw new Deep_Exit_Exception($error, 3);
                }
                $output = 'Using config file: ' . self::$config_data_file . PHP_EOL . PHP_EOL;
                $key = $this->cli_args[$pos + 1];
                $current = self::get_config_data($key);
                if ($current === null) {
                    $output .= "Config value \"{$key}\" has not been set" . PHP_EOL;
                } else {
                    try {
                        $this->set_config_data($key, null);
                    } catch (\Exception $e) {
                        throw new Deep_Exit_Exception($e->get_message() . PHP_EOL, 3);
                    }
                    $output .= "Config value \"{$key}\" removed successfully; old value was \"{$current}\"" . PHP_EOL;
                }
                throw new Deep_Exit_Exception($output, 0);
            case 'config-show':
                ob_start();
                $data = self::get_all_config_data();
                echo 'Using config file: ' . self::$config_data_file . PHP_EOL . PHP_EOL;
                $this->print_config_data($data);
                $output = ob_get_contents();
                ob_end_clean();
                throw new Deep_Exit_Exception($output, 0);
            case 'runtime-set':
                if (isset($this->cli_args[$pos + 1]) === false || isset($this->cli_args[$pos + 2]) === false) {
                    $error = 'ERROR: Setting a runtime config option requires a name and value' . PHP_EOL . PHP_EOL;
                    $error .= $this->print_short_usage(true);
                    throw new Deep_Exit_Exception($error, 3);
                }
                $key = $this->cli_args[$pos + 1];
                $value = $this->cli_args[$pos + 2];
                $this->cli_args[$pos + 1] = '';
                $this->cli_args[$pos + 2] = '';
                self::set_config_data($key, $value, true);
                if (isset(self::$overridden_defaults['runtime-set']) === false) {
                    self::$overridden_defaults['runtime-set'] = [];
                }
                self::$overridden_defaults['runtime-set'][$key] = true;
                break;
            default:
                if (substr($arg, 0, 7) === 'sniffs=') {
                    if (isset(self::$overridden_defaults['sniffs']) === true) {
                        break;
                    }
                    $sniffs = explode(',', substr($arg, 7));
                    foreach ($sniffs as $sniff) {
                        if (substr_count($sniff, '.') !== 2) {
                            $error = 'ERROR: The specified sniff code "' . $sniff . '" is invalid' . PHP_EOL . PHP_EOL;
                            $error .= $this->print_short_usage(true);
                            throw new Deep_Exit_Exception($error, 3);
                        }
                    }
                    $this->sniffs = $sniffs;
                    self::$overridden_defaults['sniffs'] = true;
                } elseif (substr($arg, 0, 8) === 'exclude=') {
                    if (isset(self::$overridden_defaults['exclude']) === true) {
                        break;
                    }
                    $sniffs = explode(',', substr($arg, 8));
                    foreach ($sniffs as $sniff) {
                        if (substr_count($sniff, '.') !== 2) {
                            $error = 'ERROR: The specified sniff code "' . $sniff . '" is invalid' . PHP_EOL . PHP_EOL;
                            $error .= $this->print_short_usage(true);
                            throw new Deep_Exit_Exception($error, 3);
                        }
                    }
                    $this->exclude = $sniffs;
                    self::$overridden_defaults['exclude'] = true;
                } elseif (defined('PHP_CODESNIFFER_IN_TESTS') === false && substr($arg, 0, 6) === 'cache=') {
                    if (isset(self::$overridden_defaults['cache']) === true && $this->cache === false || isset(self::$overridden_defaults['cacheFile']) === true) {
                        break;
                    }
                    // Turn caching on.
                    $this->cache = true;
                    self::$overridden_defaults['cache'] = true;
                    $this->cache_file = Util\Common::realpath(substr($arg, 6));
                    // It may not exist and return false instead.
                    if ($this->cache_file === false) {
                        $this->cache_file = substr($arg, 6);
                        $dir = dirname($this->cache_file);
                        if (is_dir($dir) === false) {
                            $error = 'ERROR: The specified cache file path "' . $this->cache_file . '" points to a non-existent directory' . PHP_EOL . PHP_EOL;
                            $error .= $this->print_short_usage(true);
                            throw new Deep_Exit_Exception($error, 3);
                        }
                        if ($dir === '.') {
                            // Passed cache file is a file in the current directory.
                            $this->cache_file = getcwd() . '/' . basename($this->cache_file);
                        } else {
                            if ($dir[0] === '/') {
                                // An absolute path.
                                $dir = Util\Common::realpath($dir);
                            } else {
                                $dir = Util\Common::realpath(getcwd() . '/' . $dir);
                            }
                            if ($dir !== false) {
                                // Cache file path is relative.
                                $this->cache_file = $dir . '/' . basename($this->cache_file);
                            }
                        }
                    }
                    //end if
                    self::$overridden_defaults['cacheFile'] = true;
                    if (is_dir($this->cache_file) === true) {
                        $error = 'ERROR: The specified cache file path "' . $this->cache_file . '" is a directory' . PHP_EOL . PHP_EOL;
                        $error .= $this->print_short_usage(true);
                        throw new Deep_Exit_Exception($error, 3);
                    }
                } elseif (substr($arg, 0, 10) === 'bootstrap=') {
                    $files = explode(',', substr($arg, 10));
                    $bootstrap = [];
                    foreach ($files as $file) {
                        $path = Util\Common::realpath($file);
                        if ($path === false) {
                            $error = 'ERROR: The specified bootstrap file "' . $file . '" does not exist' . PHP_EOL . PHP_EOL;
                            $error .= $this->print_short_usage(true);
                            throw new Deep_Exit_Exception($error, 3);
                        }
                        $bootstrap[] = $path;
                    }
                    $this->bootstrap = array_merge($this->bootstrap, $bootstrap);
                    self::$overridden_defaults['bootstrap'] = true;
                } elseif (substr($arg, 0, 10) === 'file-list=') {
                    $file_list = substr($arg, 10);
                    $path = Util\Common::realpath($file_list);
                    if ($path === false) {
                        $error = 'ERROR: The specified file list "' . $file_list . '" does not exist' . PHP_EOL . PHP_EOL;
                        $error .= $this->print_short_usage(true);
                        throw new Deep_Exit_Exception($error, 3);
                    }
                    $files = file($path);
                    foreach ($files as $input_file) {
                        $input_file = trim($input_file);
                        // Skip empty lines.
                        if ($input_file === '') {
                            continue;
                        }
                        $this->process_file_path($input_file);
                    }
                } elseif (substr($arg, 0, 11) === 'stdin-path=') {
                    if (isset(self::$overridden_defaults['stdinPath']) === true) {
                        break;
                    }
                    $this->stdin_path = Util\Common::realpath(substr($arg, 11));
                    // It may not exist and return false instead, so use whatever they gave us.
                    if ($this->stdin_path === false) {
                        $this->stdin_path = trim(substr($arg, 11));
                    }
                    self::$overridden_defaults['stdinPath'] = true;
                } elseif (PHP_CODESNIFFER_CBF === false && substr($arg, 0, 12) === 'report-file=') {
                    if (isset(self::$overridden_defaults['reportFile']) === true) {
                        break;
                    }
                    $this->report_file = Util\Common::realpath(substr($arg, 12));
                    // It may not exist and return false instead.
                    if ($this->report_file === false) {
                        $this->report_file = substr($arg, 12);
                        $dir = Util\Common::realpath(dirname($this->report_file));
                        if (is_dir($dir) === false) {
                            $error = 'ERROR: The specified report file path "' . $this->report_file . '" points to a non-existent directory' . PHP_EOL . PHP_EOL;
                            $error .= $this->print_short_usage(true);
                            throw new Deep_Exit_Exception($error, 3);
                        }
                        $this->report_file = $dir . '/' . basename($this->report_file);
                    }
                    //end if
                    self::$overridden_defaults['reportFile'] = true;
                    if (is_dir($this->report_file) === true) {
                        $error = 'ERROR: The specified report file path "' . $this->report_file . '" is a directory' . PHP_EOL . PHP_EOL;
                        $error .= $this->print_short_usage(true);
                        throw new Deep_Exit_Exception($error, 3);
                    }
                } elseif (substr($arg, 0, 13) === 'report-width=') {
                    if (isset(self::$overridden_defaults['reportWidth']) === true) {
                        break;
                    }
                    $this->report_width = substr($arg, 13);
                    self::$overridden_defaults['reportWidth'] = true;
                } elseif (substr($arg, 0, 9) === 'basepath=') {
                    if (isset(self::$overridden_defaults['basepath']) === true) {
                        break;
                    }
                    self::$overridden_defaults['basepath'] = true;
                    if (substr($arg, 9) === '') {
                        $this->basepath = null;
                        break;
                    }
                    $this->basepath = Util\Common::realpath(substr($arg, 9));
                    // It may not exist and return false instead.
                    if ($this->basepath === false) {
                        $this->basepath = substr($arg, 9);
                    }
                    if (is_dir($this->basepath) === false) {
                        $error = 'ERROR: The specified basepath "' . $this->basepath . '" points to a non-existent directory' . PHP_EOL . PHP_EOL;
                        $error .= $this->print_short_usage(true);
                        throw new Deep_Exit_Exception($error, 3);
                    }
                } elseif (substr($arg, 0, 7) === 'report=' || substr($arg, 0, 7) === 'report-') {
                    $reports = [];
                    if ($arg[6] === '-') {
                        // This is a report with file output.
                        $split = strpos($arg, '=');
                        if ($split === false) {
                            $report = substr($arg, 7);
                            $output = null;
                        } else {
                            $report = substr($arg, 7, $split - 7);
                            $output = substr($arg, $split + 1);
                            if ($output === false) {
                                $output = null;
                            } else {
                                $dir = Util\Common::realpath(dirname($output));
                                if (is_dir($dir) === false) {
                                    $error = 'ERROR: The specified ' . $report . ' report file path "' . $output . '" points to a non-existent directory' . PHP_EOL . PHP_EOL;
                                    $error .= $this->print_short_usage(true);
                                    throw new Deep_Exit_Exception($error, 3);
                                }
                                $output = $dir . '/' . basename($output);
                                if (is_dir($output) === true) {
                                    $error = 'ERROR: The specified ' . $report . ' report file path "' . $output . '" is a directory' . PHP_EOL . PHP_EOL;
                                    $error .= $this->print_short_usage(true);
                                    throw new Deep_Exit_Exception($error, 3);
                                }
                            }
                            //end if
                        }
                        //end if
                        $reports[$report] = $output;
                    } else {
                        // This is a single report.
                        if (isset(self::$overridden_defaults['reports']) === true) {
                            break;
                        }
                        $report_names = explode(',', substr($arg, 7));
                        foreach ($report_names as $report) {
                            $reports[$report] = null;
                        }
                    }
                    //end if
                    // Remove the default value so the CLI value overrides it.
                    if (isset(self::$overridden_defaults['reports']) === false) {
                        $this->reports = $reports;
                    } else {
                        $this->reports = array_merge($this->reports, $reports);
                    }
                    self::$overridden_defaults['reports'] = true;
                } elseif (substr($arg, 0, 7) === 'filter=') {
                    if (isset(self::$overridden_defaults['filter']) === true) {
                        break;
                    }
                    $this->filter = substr($arg, 7);
                    self::$overridden_defaults['filter'] = true;
                } elseif (substr($arg, 0, 9) === 'standard=') {
                    $standards = trim(substr($arg, 9));
                    if ($standards !== '') {
                        $this->standards = explode(',', $standards);
                    }
                    self::$overridden_defaults['standards'] = true;
                } elseif (substr($arg, 0, 11) === 'extensions=') {
                    if (isset(self::$overridden_defaults['extensions']) === true) {
                        break;
                    }
                    $extensions = explode(',', substr($arg, 11));
                    $new_extensions = [];
                    foreach ($extensions as $ext) {
                        $slash = strpos($ext, '/');
                        if ($slash !== false) {
                            // They specified the tokenizer too.
                            list($ext, $tokenizer) = explode('/', $ext);
                            $new_extensions[$ext] = strtoupper($tokenizer);
                            continue;
                        }
                        if (isset($this->extensions[$ext]) === true) {
                            $new_extensions[$ext] = $this->extensions[$ext];
                        } else {
                            $new_extensions[$ext] = 'PHP';
                        }
                    }
                    $this->extensions = $new_extensions;
                    self::$overridden_defaults['extensions'] = true;
                } elseif (substr($arg, 0, 7) === 'suffix=') {
                    if (isset(self::$overridden_defaults['suffix']) === true) {
                        break;
                    }
                    $this->suffix = substr($arg, 7);
                    self::$overridden_defaults['suffix'] = true;
                } elseif (substr($arg, 0, 9) === 'parallel=') {
                    if (isset(self::$overridden_defaults['parallel']) === true) {
                        break;
                    }
                    $this->parallel = max((int) substr($arg, 9), 1);
                    self::$overridden_defaults['parallel'] = true;
                } elseif (substr($arg, 0, 9) === 'severity=') {
                    $this->error_severity = (int) substr($arg, 9);
                    $this->warning_severity = $this->error_severity;
                    if (isset(self::$overridden_defaults['errorSeverity']) === false) {
                        self::$overridden_defaults['errorSeverity'] = true;
                    }
                    if (isset(self::$overridden_defaults['warningSeverity']) === false) {
                        self::$overridden_defaults['warningSeverity'] = true;
                    }
                } elseif (substr($arg, 0, 15) === 'error-severity=') {
                    if (isset(self::$overridden_defaults['errorSeverity']) === true) {
                        break;
                    }
                    $this->error_severity = (int) substr($arg, 15);
                    self::$overridden_defaults['errorSeverity'] = true;
                } elseif (substr($arg, 0, 17) === 'warning-severity=') {
                    if (isset(self::$overridden_defaults['warningSeverity']) === true) {
                        break;
                    }
                    $this->warning_severity = (int) substr($arg, 17);
                    self::$overridden_defaults['warningSeverity'] = true;
                } elseif (substr($arg, 0, 7) === 'ignore=') {
                    if (isset(self::$overridden_defaults['ignored']) === true) {
                        break;
                    }
                    // Split the ignore string on commas, unless the comma is escaped
                    // using 1 or 3 slashes (\, or \\\,).
                    $patterns = preg_split('/(?<=(?<!\\\\)\\\\\\\\),|(?<!\\\\),/', substr($arg, 7));
                    $ignored = [];
                    foreach ($patterns as $pattern) {
                        $pattern = trim($pattern);
                        if ($pattern === '') {
                            continue;
                        }
                        $ignored[$pattern] = 'absolute';
                    }
                    $this->ignored = $ignored;
                    self::$overridden_defaults['ignored'] = true;
                } elseif (substr($arg, 0, 10) === 'generator=' && PHP_CODESNIFFER_CBF === false) {
                    if (isset(self::$overridden_defaults['generator']) === true) {
                        break;
                    }
                    $this->generator = substr($arg, 10);
                    self::$overridden_defaults['generator'] = true;
                } elseif (substr($arg, 0, 9) === 'encoding=') {
                    if (isset(self::$overridden_defaults['encoding']) === true) {
                        break;
                    }
                    $this->encoding = strtolower(substr($arg, 9));
                    self::$overridden_defaults['encoding'] = true;
                } elseif (substr($arg, 0, 10) === 'tab-width=') {
                    if (isset(self::$overridden_defaults['tabWidth']) === true) {
                        break;
                    }
                    $this->tab_width = (int) substr($arg, 10);
                    self::$overridden_defaults['tabWidth'] = true;
                } else if ($this->die_on_unknown_arg === false) {
                    $eq_pos = strpos($arg, '=');
                    try {
                        if ($eq_pos === false) {
                            $this->values[$arg] = $arg;
                        } else {
                            $value = substr($arg, $eq_pos + 1);
                            $arg = substr($arg, 0, $eq_pos);
                            $this->values[$arg] = $value;
                        }
                    } catch (RuntimeException $e) {
                        // Value is not valid, so just ignore it.
                    }
                } else {
                    $this->process_unknown_argument('--' . $arg, $pos);
                }
                //end if
                break;
        }
        //end switch
    }
    //end processLongArgument()
    /**
     * Processes an unknown command line argument.
     *
     * Assumes all unknown arguments are files and folders to check.
     *
     * @param string $arg The command line argument.
     * @param int    $pos The position of the argument on the command line.
     *
     * @return void
     * @throws \PHP_CodeSniffer\Exceptions\DeepExitException
     */
    public function process_unknown_argument($arg, $pos)
    {
        // We don't know about any additional switches; just files.
        if ($arg[0] === '-') {
            if ($this->die_on_unknown_arg === false) {
                return;
            }
            $error = "ERROR: option \"{$arg}\" not known" . PHP_EOL . PHP_EOL;
            $error .= $this->print_short_usage(true);
            throw new Deep_Exit_Exception($error, 3);
        }
        $this->process_file_path($arg);
    }
    //end processUnknownArgument()
    /**
     * Processes a file path and add it to the file list.
     *
     * @param string $path The path to the file to add.
     *
     * @return void
     * @throws \PHP_CodeSniffer\Exceptions\DeepExitException
     */
    public function process_file_path($path)
    {
        // If we are processing STDIN, don't record any files to check.
        if ($this->stdin === true) {
            return;
        }
        $file = Util\Common::realpath($path);
        if (file_exists($file) === false) {
            if ($this->die_on_unknown_arg === false) {
                return;
            }
            $error = 'ERROR: The file "' . $path . '" does not exist.' . PHP_EOL . PHP_EOL;
            $error .= $this->print_short_usage(true);
            throw new Deep_Exit_Exception($error, 3);
        }
        // Can't modify the files array directly because it's not a real
        // class member, so need to use this little get/modify/set trick.
        $files = $this->files;
        $files[] = $file;
        $this->files = $files;
        self::$overridden_defaults['files'] = true;
    }
    //end processFilePath()
    /**
     * Prints out the usage information for this script.
     *
     * @return void
     */
    public function print_usage()
    {
        echo PHP_EOL;
        if (PHP_CODESNIFFER_CBF === true) {
            $this->print_phpcbf_usage();
        } else {
            $this->print_phpcs_usage();
        }
        echo PHP_EOL;
    }
    //end printUsage()
    /**
     * Prints out the short usage information for this script.
     *
     * @param bool $return If TRUE, the usage string is returned
     *                     instead of output to screen.
     *
     * @return string|void
     */
    public function print_short_usage($return = false)
    {
        if (PHP_CODESNIFFER_CBF === true) {
            $usage = 'Run "phpcbf --help" for usage information';
        } else {
            $usage = 'Run "phpcs --help" for usage information';
        }
        $usage .= PHP_EOL . PHP_EOL;
        if ($return === true) {
            return $usage;
        }
        echo $usage;
    }
    //end printShortUsage()
    /**
     * Prints out the usage information for PHPCS.
     *
     * @return void
     */
    public function print_phpcs_usage()
    {
        echo 'Usage: phpcs [-nwlsaepqvi] [-d key[=value]] [--colors] [--no-colors]' . PHP_EOL;
        echo '  [--cache[=<cacheFile>]] [--no-cache] [--tab-width=<tabWidth>]' . PHP_EOL;
        echo '  [--report=<report>] [--report-file=<reportFile>] [--report-<report>=<reportFile>]' . PHP_EOL;
        echo '  [--report-width=<reportWidth>] [--basepath=<basepath>] [--bootstrap=<bootstrap>]' . PHP_EOL;
        echo '  [--severity=<severity>] [--error-severity=<severity>] [--warning-severity=<severity>]' . PHP_EOL;
        echo '  [--runtime-set key value] [--config-set key value] [--config-delete key] [--config-show]' . PHP_EOL;
        echo '  [--standard=<standard>] [--sniffs=<sniffs>] [--exclude=<sniffs>]' . PHP_EOL;
        echo '  [--encoding=<encoding>] [--parallel=<processes>] [--generator=<generator>]' . PHP_EOL;
        echo '  [--extensions=<extensions>] [--ignore=<patterns>] [--ignore-annotations]' . PHP_EOL;
        echo '  [--stdin-path=<stdinPath>] [--file-list=<fileList>] [--filter=<filter>] <file> - ...' . PHP_EOL;
        echo PHP_EOL;
        echo ' -     Check STDIN instead of local files and directories' . PHP_EOL;
        echo ' -n    Do not print warnings (shortcut for --warning-severity=0)' . PHP_EOL;
        echo ' -w    Print both warnings and errors (this is the default)' . PHP_EOL;
        echo ' -l    Local directory only, no recursion' . PHP_EOL;
        echo ' -s    Show sniff codes in all reports' . PHP_EOL;
        echo ' -a    Run interactively' . PHP_EOL;
        echo ' -e    Explain a standard by showing the sniffs it includes' . PHP_EOL;
        echo ' -p    Show progress of the run' . PHP_EOL;
        echo ' -q    Quiet mode; disables progress and verbose output' . PHP_EOL;
        echo ' -m    Stop error messages from being recorded' . PHP_EOL;
        echo '       (saves a lot of memory, but stops many reports from being used)' . PHP_EOL;
        echo ' -v    Print processed files' . PHP_EOL;
        echo ' -vv   Print ruleset and token output' . PHP_EOL;
        echo ' -vvv  Print sniff processing information' . PHP_EOL;
        echo ' -i    Show a list of installed coding standards' . PHP_EOL;
        echo ' -d    Set the [key] php.ini value to [value] or [true] if value is omitted' . PHP_EOL;
        echo PHP_EOL;
        echo ' --help                Print this help message' . PHP_EOL;
        echo ' --version             Print version information' . PHP_EOL;
        echo ' --colors              Use colors in output' . PHP_EOL;
        echo ' --no-colors           Do not use colors in output (this is the default)' . PHP_EOL;
        echo ' --cache               Cache results between runs' . PHP_EOL;
        echo ' --no-cache            Do not cache results between runs (this is the default)' . PHP_EOL;
        echo ' --ignore-annotations  Ignore all phpcs: annotations in code comments' . PHP_EOL;
        echo PHP_EOL;
        echo ' <cacheFile>    Use a specific file for caching (uses a temporary file by default)' . PHP_EOL;
        echo ' <basepath>     A path to strip from the front of file paths inside reports' . PHP_EOL;
        echo ' <bootstrap>    A comma separated list of files to run before processing begins' . PHP_EOL;
        echo ' <encoding>     The encoding of the files being checked (default is utf-8)' . PHP_EOL;
        echo ' <extensions>   A comma separated list of file extensions to check' . PHP_EOL;
        echo '                The type of the file can be specified using: ext/type' . PHP_EOL;
        echo '                e.g., module/php,es/js' . PHP_EOL;
        echo ' <file>         One or more files and/or directories to check' . PHP_EOL;
        echo ' <fileList>     A file containing a list of files and/or directories to check (one per line)' . PHP_EOL;
        echo ' <filter>       Use either the "GitModified" or "GitStaged" filter,' . PHP_EOL;
        echo '                or specify the path to a custom filter class' . PHP_EOL;
        echo ' <generator>    Use either the "HTML", "Markdown" or "Text" generator' . PHP_EOL;
        echo '                (forces documentation generation instead of checking)' . PHP_EOL;
        echo ' <patterns>     A comma separated list of patterns to ignore files and directories' . PHP_EOL;
        echo ' <processes>    How many files should be checked simultaneously (default is 1)' . PHP_EOL;
        echo ' <report>       Print either the "full", "xml", "checkstyle", "csv"' . PHP_EOL;
        echo '                "json", "junit", "emacs", "source", "summary", "diff"' . PHP_EOL;
        echo '                "svnblame", "gitblame", "hgblame" or "notifysend" report,' . PHP_EOL;
        echo '                or specify the path to a custom report class' . PHP_EOL;
        echo '                (the "full" report is printed by default)' . PHP_EOL;
        echo ' <reportFile>   Write the report to the specified file path' . PHP_EOL;
        echo ' <reportWidth>  How many columns wide screen reports should be printed' . PHP_EOL;
        echo '                or set to "auto" to use current screen width, where supported' . PHP_EOL;
        echo ' <severity>     The minimum severity required to display an error or warning' . PHP_EOL;
        echo ' <sniffs>       A comma separated list of sniff codes to include or exclude from checking' . PHP_EOL;
        echo '                (all sniffs must be part of the specified standard)' . PHP_EOL;
        echo ' <standard>     The name or path of the coding standard to use' . PHP_EOL;
        echo ' <stdinPath>    If processing STDIN, the file path that STDIN will be processed as' . PHP_EOL;
        echo ' <tabWidth>     The number of spaces each tab represents' . PHP_EOL;
    }
    //end printPHPCSUsage()
    /**
     * Prints out the usage information for PHPCBF.
     *
     * @return void
     */
    public function print_phpcbf_usage()
    {
        echo 'Usage: phpcbf [-nwli] [-d key[=value]] [--ignore-annotations] [--bootstrap=<bootstrap>]' . PHP_EOL;
        echo '  [--standard=<standard>] [--sniffs=<sniffs>] [--exclude=<sniffs>] [--suffix=<suffix>]' . PHP_EOL;
        echo '  [--severity=<severity>] [--error-severity=<severity>] [--warning-severity=<severity>]' . PHP_EOL;
        echo '  [--tab-width=<tabWidth>] [--encoding=<encoding>] [--parallel=<processes>]' . PHP_EOL;
        echo '  [--basepath=<basepath>] [--extensions=<extensions>] [--ignore=<patterns>]' . PHP_EOL;
        echo '  [--stdin-path=<stdinPath>] [--file-list=<fileList>] [--filter=<filter>] <file> - ...' . PHP_EOL;
        echo PHP_EOL;
        echo ' -     Fix STDIN instead of local files and directories' . PHP_EOL;
        echo ' -n    Do not fix warnings (shortcut for --warning-severity=0)' . PHP_EOL;
        echo ' -w    Fix both warnings and errors (on by default)' . PHP_EOL;
        echo ' -l    Local directory only, no recursion' . PHP_EOL;
        echo ' -p    Show progress of the run' . PHP_EOL;
        echo ' -q    Quiet mode; disables progress and verbose output' . PHP_EOL;
        echo ' -v    Print processed files' . PHP_EOL;
        echo ' -vv   Print ruleset and token output' . PHP_EOL;
        echo ' -vvv  Print sniff processing information' . PHP_EOL;
        echo ' -i    Show a list of installed coding standards' . PHP_EOL;
        echo ' -d    Set the [key] php.ini value to [value] or [true] if value is omitted' . PHP_EOL;
        echo PHP_EOL;
        echo ' --help                Print this help message' . PHP_EOL;
        echo ' --version             Print version information' . PHP_EOL;
        echo ' --ignore-annotations  Ignore all phpcs: annotations in code comments' . PHP_EOL;
        echo PHP_EOL;
        echo ' <basepath>    A path to strip from the front of file paths inside reports' . PHP_EOL;
        echo ' <bootstrap>   A comma separated list of files to run before processing begins' . PHP_EOL;
        echo ' <encoding>    The encoding of the files being fixed (default is utf-8)' . PHP_EOL;
        echo ' <extensions>  A comma separated list of file extensions to fix' . PHP_EOL;
        echo '               The type of the file can be specified using: ext/type' . PHP_EOL;
        echo '               e.g., module/php,es/js' . PHP_EOL;
        echo ' <file>        One or more files and/or directories to fix' . PHP_EOL;
        echo ' <fileList>    A file containing a list of files and/or directories to fix (one per line)' . PHP_EOL;
        echo ' <filter>      Use either the "GitModified" or "GitStaged" filter,' . PHP_EOL;
        echo '               or specify the path to a custom filter class' . PHP_EOL;
        echo ' <patterns>    A comma separated list of patterns to ignore files and directories' . PHP_EOL;
        echo ' <processes>   How many files should be fixed simultaneously (default is 1)' . PHP_EOL;
        echo ' <severity>    The minimum severity required to fix an error or warning' . PHP_EOL;
        echo ' <sniffs>      A comma separated list of sniff codes to include or exclude from fixing' . PHP_EOL;
        echo '               (all sniffs must be part of the specified standard)' . PHP_EOL;
        echo ' <standard>    The name or path of the coding standard to use' . PHP_EOL;
        echo ' <stdinPath>   If processing STDIN, the file path that STDIN will be processed as' . PHP_EOL;
        echo ' <suffix>      Write modified files to a filename using this suffix' . PHP_EOL;
        echo '               ("diff" and "patch" are not used in this mode)' . PHP_EOL;
        echo ' <tabWidth>    The number of spaces each tab represents' . PHP_EOL;
    }
    //end printPHPCBFUsage()
    /**
     * Get a single config value.
     *
     * @param string $key The name of the config value.
     *
     * @return string|null
     * @see    setConfigData()
     * @see    getAllConfigData()
     */
    public static function get_config_data($key)
    {
        $php_code_sniffer_config = self::get_all_config_data();
        if ($php_code_sniffer_config === null) {
            return null;
        }
        if (isset($php_code_sniffer_config[$key]) === false) {
            return null;
        }
        return $php_code_sniffer_config[$key];
    }
    //end getConfigData()
    /**
     * Get the path to an executable utility.
     *
     * @param string $name The name of the executable utility.
     *
     * @return string|null
     * @see    getConfigData()
     */
    public static function get_executable_path($name)
    {
        $data = self::get_config_data($name . '_path');
        if ($data !== null) {
            return $data;
        }
        if ($name === 'php') {
            // For php, we know the executable path. There's no need to look it up.
            return PHP_BINARY;
        }
        if (array_key_exists($name, self::$executable_paths) === true) {
            return self::$executable_paths[$name];
        }
        if (stripos(PHP_OS, 'WIN') === 0) {
            $cmd = 'where ' . escapeshellarg($name) . ' 2> nul';
        } else {
            $cmd = 'which ' . escapeshellarg($name) . ' 2> /dev/null';
        }
        $result = exec($cmd, $output, $ret_val);
        if ($ret_val !== 0) {
            $result = null;
        }
        self::$executable_paths[$name] = $result;
        return $result;
    }
    //end getExecutablePath()
    /**
     * Set a single config value.
     *
     * @param string      $key   The name of the config value.
     * @param string|null $value The value to set. If null, the config
     *                           entry is deleted, reverting it to the
     *                           default value.
     * @param boolean     $temp  Set this config data temporarily for this
     *                           script run. This will not write the config
     *                           data to the config file.
     *
     * @return bool
     * @see    getConfigData()
     * @throws \PHP_CodeSniffer\Exceptions\DeepExitException If the config file can not be written.
     */
    public static function set_config_data($key, $value, $temp = false)
    {
        if (isset(self::$overridden_defaults['runtime-set']) === true && isset(self::$overridden_defaults['runtime-set'][$key]) === true) {
            return false;
        }
        if ($temp === false) {
            $path = '';
            if (is_callable('\Phar::running') === true) {
                $path = \Phar::running(false);
            }
            if ($path !== '') {
                $config_file = dirname($path) . DIRECTORY_SEPARATOR . 'CodeSniffer.conf';
            } else {
                $config_file = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'CodeSniffer.conf';
                if (is_file($config_file) === false && strpos('@data_dir@', '@data_dir') === false) {
                    // If data_dir was replaced, this is a PEAR install and we can
                    // use the PEAR data dir to store the conf file.
                    $config_file = '@data_dir@/PHP_CodeSniffer/CodeSniffer.conf';
                }
            }
            if (is_file($config_file) === true && is_writable($config_file) === false) {
                $error = 'ERROR: Config file ' . $config_file . ' is not writable' . PHP_EOL . PHP_EOL;
                throw new Deep_Exit_Exception($error, 3);
            }
        }
        //end if
        $php_code_sniffer_config = self::get_all_config_data();
        if ($value === null) {
            if (isset($php_code_sniffer_config[$key]) === true) {
                unset($php_code_sniffer_config[$key]);
            }
        } else {
            $php_code_sniffer_config[$key] = $value;
        }
        if ($temp === false) {
            $output = '<' . '?php' . "\n" . ' $phpCodeSnifferConfig = ';
            $output .= var_export($php_code_sniffer_config, true);
            $output .= ";\n?" . '>';
            if (file_put_contents($config_file, $output) === false) {
                $error = 'ERROR: Config file ' . $config_file . ' could not be written' . PHP_EOL . PHP_EOL;
                throw new Deep_Exit_Exception($error, 3);
            }
            self::$config_data_file = $config_file;
        }
        self::$config_data = $php_code_sniffer_config;
        // If the installed paths are being set, make sure all known
        // standards paths are added to the autoloader.
        if ($key === 'installed_paths') {
            $installed_standards = Util\Standards::get_installed_standard_details();
            foreach ($installed_standards as $details) {
                Autoload::add_search_path($details['path'], $details['namespace']);
            }
        }
        return true;
    }
    //end setConfigData()
    /**
     * Get all config data.
     *
     * @return array<string, string>
     * @see    getConfigData()
     * @throws \PHP_CodeSniffer\Exceptions\DeepExitException If the config file could not be read.
     */
    public static function get_all_config_data()
    {
        if (self::$config_data !== null) {
            return self::$config_data;
        }
        $path = '';
        if (is_callable('\Phar::running') === true) {
            $path = \Phar::running(false);
        }
        if ($path !== '') {
            $config_file = dirname($path) . DIRECTORY_SEPARATOR . 'CodeSniffer.conf';
        } else {
            $config_file = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'CodeSniffer.conf';
            if (is_file($config_file) === false && strpos('@data_dir@', '@data_dir') === false) {
                $config_file = '@data_dir@/PHP_CodeSniffer/CodeSniffer.conf';
            }
        }
        if (is_file($config_file) === false) {
            self::$config_data = [];
            return [];
        }
        if (Common::is_readable($config_file) === false) {
            $error = 'ERROR: Config file ' . $config_file . ' is not readable' . PHP_EOL . PHP_EOL;
            throw new Deep_Exit_Exception($error, 3);
        }
        include $config_file;
        self::$config_data_file = $config_file;
        self::$config_data = $php_code_sniffer_config;
        return self::$config_data;
    }
    //end getAllConfigData()
    /**
     * Prints out the gathered config data.
     *
     * @param array $data The config data to print.
     *
     * @return void
     */
    public function print_config_data($data)
    {
        $max = 0;
        $keys = array_keys($data);
        foreach ($keys as $key) {
            $len = strlen($key);
            if (strlen($key) > $max) {
                $max = $len;
            }
        }
        if ($max === 0) {
            return;
        }
        $max += 2;
        ksort($data);
        foreach ($data as $name => $value) {
            echo str_pad($name . ': ', $max) . $value . PHP_EOL;
        }
    }
    //end printConfigData()
}
//end class