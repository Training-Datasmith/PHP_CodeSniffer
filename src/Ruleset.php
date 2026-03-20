<?php

declare (strict_types=1);
/**
 * Stores the rules used to check and fix files.
 *
 * A ruleset object directly maps to a ruleset XML file.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer;

use Php_code_Sniffer\Exceptions\RuntimeException;
use stdClass;
class Ruleset
{
    /**
     * The name of the coding standard being used.
     *
     * If a top-level standard includes other standards, or sniffs
     * from other standards, only the name of the top-level standard
     * will be stored in here.
     *
     * If multiple top-level standards are being loaded into
     * a single ruleset object, this will store a comma separated list
     * of the top-level standard names.
     *
     * @var string
     */
    public $name = '';
    /**
     * A list of file paths for the ruleset files being used.
     *
     * @var string[]
     */
    public $paths = [];
    /**
     * A list of regular expressions used to ignore specific sniffs for files and folders.
     *
     * Is also used to set global exclude patterns.
     * The key is the regular expression and the value is the type
     * of ignore pattern (absolute or relative).
     *
     * @var array<string, string>
     */
    public $ignore_patterns = [];
    /**
     * A list of regular expressions used to include specific sniffs for files and folders.
     *
     * The key is the sniff code and the value is an array with
     * the key being a regular expression and the value is the type
     * of ignore pattern (absolute or relative).
     *
     * @var array<string, array<string, string>>
     */
    public $include_patterns = [];
    /**
     * An array of sniff objects that are being used to check files.
     *
     * The key is the fully qualified name of the sniff class
     * and the value is the sniff object.
     *
     * @var array<string, \PHP_CodeSniffer\Sniffs\Sniff>
     */
    public $sniffs = [];
    /**
     * A mapping of sniff codes to fully qualified class names.
     *
     * The key is the sniff code and the value
     * is the fully qualified name of the sniff class.
     *
     * @var array<string, string>
     */
    public $sniff_codes = [];
    /**
     * An array of token types and the sniffs that are listening for them.
     *
     * The key is the token name being listened for and the value
     * is the sniff object.
     *
     * @var array<int, \PHP_CodeSniffer\Sniffs\Sniff>
     */
    public $token_listeners = [];
    /**
     * An array of rules from the ruleset.xml file.
     *
     * It may be empty, indicating that the ruleset does not override
     * any of the default sniff settings.
     *
     * @var array<string, mixed>
     */
    public $ruleset = [];
    /**
     * The directories that the processed rulesets are in.
     *
     * @var string[]
     */
    protected $ruleset_dirs = [];
    /**
     * The config data for the run.
     *
     * @var \PHP_CodeSniffer\Config
     */
    private $config;
    /**
     * Initialise the ruleset that the run will use.
     *
     * @param \PHP_CodeSniffer\Config $config The config data for the run.
     *
     * @throws \PHP_CodeSniffer\Exceptions\RuntimeException If no sniffs were registered.
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
        $restrictions = $config->sniffs;
        $exclusions = $config->exclude;
        $sniffs = [];
        $standard_paths = [];
        foreach ($config->standards as $standard) {
            $installed = Util\Standards::get_installed_standard_path($standard);
            if ($installed === null) {
                $standard = Util\Common::realpath($standard);
                if (is_dir($standard) === true && is_file(Util\Common::realpath($standard . DIRECTORY_SEPARATOR . 'ruleset.xml')) === true) {
                    $standard = Util\Common::realpath($standard . DIRECTORY_SEPARATOR . 'ruleset.xml');
                }
            } else {
                $standard = $installed;
            }
            $standard_paths[] = $standard;
        }
        foreach ($standard_paths as $standard) {
            $ruleset = @simplexml_load_string(file_get_contents($standard));
            if ($ruleset !== false) {
                $standard_name = (string) $ruleset['name'];
                if ($this->name !== '') {
                    $this->name .= ', ';
                }
                $this->name .= $standard_name;
                // Allow autoloading of custom files inside this standard.
                if (isset($ruleset['namespace']) === true) {
                    $namespace = (string) $ruleset['namespace'];
                } else {
                    $namespace = basename(dirname($standard));
                }
                Autoload::add_search_path(dirname($standard), $namespace);
            }
            if (defined('PHP_CODESNIFFER_IN_TESTS') === true && empty($restrictions) === false) {
                // In unit tests, only register the sniffs that the test wants and not the entire standard.
                try {
                    foreach ($restrictions as $restriction) {
                        $sniffs = array_merge($sniffs, $this->expand_ruleset_reference($restriction, dirname($standard)));
                    }
                } catch (RuntimeException $e) {
                    // Sniff reference could not be expanded, which probably means this
                    // is an installed standard. Let the unit test system take care of
                    // setting the correct sniff for testing.
                    return;
                }
                break;
            }
            if (PHP_CODESNIFFER_VERBOSITY === 1) {
                echo "Registering sniffs in the {$standard_name} standard... ";
                if (count($config->standards) > 1 || PHP_CODESNIFFER_VERBOSITY > 2) {
                    echo PHP_EOL;
                }
            }
            $sniffs = array_merge($sniffs, $this->process_ruleset($standard));
        }
        //end foreach
        // Ignore sniff restrictions if caching is on.
        if ($config->cache === true) {
            $restrictions = [];
            $exclusions = [];
        }
        $sniff_restrictions = [];
        foreach ($restrictions as $sniff_code) {
            $parts = explode('.', strtolower($sniff_code));
            $sniff_name = $parts[0] . '\sniffs\\' . $parts[1] . '\\' . $parts[2] . 'sniff';
            $sniff_restrictions[$sniff_name] = true;
        }
        $sniff_exclusions = [];
        foreach ($exclusions as $sniff_code) {
            $parts = explode('.', strtolower($sniff_code));
            $sniff_name = $parts[0] . '\sniffs\\' . $parts[1] . '\\' . $parts[2] . 'sniff';
            $sniff_exclusions[$sniff_name] = true;
        }
        $this->register_sniffs($sniffs, $sniff_restrictions, $sniff_exclusions);
        $this->populate_token_listeners();
        $num_sniffs = count($this->sniffs);
        if (PHP_CODESNIFFER_VERBOSITY === 1) {
            echo "DONE ({$num_sniffs} sniffs registered)" . PHP_EOL;
        }
        if ($num_sniffs === 0) {
            throw new RuntimeException('No sniffs were registered');
        }
    }
    //end __construct()
    /**
     * Prints a report showing the sniffs contained in a standard.
     *
     * @return void
     */
    public function explain()
    {
        $sniffs = array_keys($this->sniff_codes);
        sort($sniffs);
        ob_start();
        $last_standard = null;
        $last_count = '';
        $sniff_count = count($sniffs);
        // Add a dummy entry to the end so we loop
        // one last time and clear the output buffer.
        $sniffs[] = '';
        $summary_line = PHP_EOL . "The {$this->name} standard contains 1 sniff" . PHP_EOL;
        if ($sniff_count !== 1) {
            $summary_line = str_replace('1 sniff', "{$sniff_count} sniffs", $summary_line);
        }
        echo $summary_line;
        ob_start();
        foreach ($sniffs as $i => $sniff) {
            if ($i === $sniff_count) {
                $current_standard = null;
            } else {
                $current_standard = substr($sniff, 0, strpos($sniff, '.'));
                if ($last_standard === null) {
                    $last_standard = $current_standard;
                }
            }
            if ($current_standard !== $last_standard) {
                $sniff_list = ob_get_contents();
                ob_end_clean();
                echo PHP_EOL . $last_standard . ' (' . $last_count . ' sniff';
                if ($last_count > 1) {
                    echo 's';
                }
                echo ')' . PHP_EOL;
                echo str_repeat('-', strlen($last_standard . $last_count) + 10);
                echo PHP_EOL;
                echo $sniff_list;
                $last_standard = $current_standard;
                $last_count = 0;
                if ($current_standard === null) {
                    break;
                }
                ob_start();
            }
            //end if
            echo '  ' . $sniff . PHP_EOL;
            $last_count++;
        }
        //end foreach
    }
    //end explain()
    /**
     * Processes a single ruleset and returns a list of the sniffs it represents.
     *
     * Rules founds within the ruleset are processed immediately, but sniff classes
     * are not registered by this method.
     *
     * @param string $rulesetPath The path to a ruleset XML file.
     * @param int    $depth       How many nested processing steps we are in. This
     *                            is only used for debug output.
     *
     * @return string[]
     * @throws \PHP_CodeSniffer\Exceptions\RuntimeException - If the ruleset path is invalid.
     *                                                      - If a specified autoload file could not be found.
     */
    public function process_ruleset($ruleset_path, $depth = 0)
    {
        $ruleset_path = Util\Common::realpath($ruleset_path);
        if (PHP_CODESNIFFER_VERBOSITY > 1) {
            echo str_repeat("\t", $depth);
            echo 'Processing ruleset ' . Util\Common::strip_basepath($ruleset_path, $this->config->basepath) . PHP_EOL;
        }
        libxml_use_internal_errors(true);
        $ruleset = simplexml_load_string(file_get_contents($ruleset_path));
        if ($ruleset === false) {
            $error_msg = "Ruleset {$ruleset_path} is not valid" . PHP_EOL;
            $errors = libxml_get_errors();
            foreach ($errors as $error) {
                $error_msg .= '- On line ' . $error->line . ', column ' . $error->column . ': ' . $error->message;
            }
            libxml_clear_errors();
            throw new RuntimeException($error_msg);
        }
        libxml_use_internal_errors(false);
        $own_sniffs = [];
        $included_sniffs = [];
        $excluded_sniffs = [];
        $this->paths[] = $ruleset_path;
        $ruleset_dir = dirname($ruleset_path);
        $this->ruleset_dirs[] = $ruleset_dir;
        $sniff_dir = $ruleset_dir . DIRECTORY_SEPARATOR . 'Sniffs';
        if (is_dir($sniff_dir) === true) {
            if (PHP_CODESNIFFER_VERBOSITY > 1) {
                echo str_repeat("\t", $depth);
                echo "\tAdding sniff files from " . Util\Common::strip_basepath($sniff_dir, $this->config->basepath) . ' directory' . PHP_EOL;
            }
            $own_sniffs = $this->expand_sniff_directory($sniff_dir, $depth);
        }
        // Include custom autoloaders.
        foreach ($ruleset->{'autoload'} as $autoload) {
            if ($this->should_process_element($autoload) === false) {
                continue;
            }
            $autoload_path = (string) $autoload;
            // Try relative autoload paths first.
            $relative_path = Util\Common::real_path(dirname($ruleset_path) . DIRECTORY_SEPARATOR . $autoload_path);
            if ($relative_path !== false && is_file($relative_path) === true) {
                $autoload_path = $relative_path;
            } elseif (is_file($autoload_path) === false) {
                throw new RuntimeException('The specified autoload file "' . $autoload . '" does not exist');
            }
            include_once $autoload_path;
            if (PHP_CODESNIFFER_VERBOSITY > 1) {
                echo str_repeat("\t", $depth);
                echo "\t=> included autoloader {$autoload_path}" . PHP_EOL;
            }
        }
        //end foreach
        // Process custom sniff config settings.
        foreach ($ruleset->{'config'} as $config) {
            if ($this->should_process_element($config) === false) {
                continue;
            }
            Config::set_config_data((string) $config['name'], (string) $config['value'], true);
            if (PHP_CODESNIFFER_VERBOSITY > 1) {
                echo str_repeat("\t", $depth);
                echo "\t=> set config value " . $config['name'] . ': ' . $config['value'] . PHP_EOL;
            }
        }
        foreach ($ruleset->rule as $rule) {
            if (isset($rule['ref']) === false) {
                continue;
            }
            if ($this->should_process_element($rule) === false) {
                continue;
            }
            if (PHP_CODESNIFFER_VERBOSITY > 1) {
                echo str_repeat("\t", $depth);
                echo "\tProcessing rule \"" . $rule['ref'] . '"' . PHP_EOL;
            }
            $expanded_sniffs = $this->expand_ruleset_reference((string) $rule['ref'], $ruleset_dir, $depth);
            $new_sniffs = array_diff($expanded_sniffs, $included_sniffs);
            $included_sniffs = array_merge($included_sniffs, $expanded_sniffs);
            $parts = explode('.', $rule['ref']);
            if (count($parts) === 4 && $parts[0] !== '' && $parts[1] !== '' && $parts[2] !== '') {
                $sniff_code = $parts[0] . '.' . $parts[1] . '.' . $parts[2];
                if (isset($this->ruleset[$sniff_code]['severity']) === true && $this->ruleset[$sniff_code]['severity'] === 0) {
                    // This sniff code has already been turned off, but now
                    // it is being explicitly included again, so turn it back on.
                    $this->ruleset[(string) $rule['ref']]['severity'] = 5;
                    if (PHP_CODESNIFFER_VERBOSITY > 1) {
                        echo str_repeat("\t", $depth);
                        echo "\t\t* disabling sniff exclusion for specific message code *" . PHP_EOL;
                        echo str_repeat("\t", $depth);
                        echo "\t\t=> severity set to 5" . PHP_EOL;
                    }
                } elseif (empty($new_sniffs) === false) {
                    $new_sniff = $new_sniffs[0];
                    if (in_array($new_sniff, $own_sniffs, true) === false) {
                        // Including a sniff that hasn't been included higher up, but
                        // only including a single message from it. So turn off all messages in
                        // the sniff, except this one.
                        $this->ruleset[$sniff_code]['severity'] = 0;
                        $this->ruleset[(string) $rule['ref']]['severity'] = 5;
                        if (PHP_CODESNIFFER_VERBOSITY > 1) {
                            echo str_repeat("\t", $depth);
                            echo "\t\tExcluding sniff \"" . $sniff_code . '" except for "' . $parts[3] . '"' . PHP_EOL;
                        }
                    }
                }
                //end if
            }
            //end if
            if (isset($rule->exclude) === true) {
                foreach ($rule->exclude as $exclude) {
                    if (isset($exclude['name']) === false) {
                        if (PHP_CODESNIFFER_VERBOSITY > 1) {
                            echo str_repeat("\t", $depth);
                            echo "\t\t* ignoring empty exclude rule *" . PHP_EOL;
                            echo "\t\t\t=> " . $exclude->as_xml() . PHP_EOL;
                        }
                        continue;
                    }
                    if ($this->should_process_element($exclude) === false) {
                        continue;
                    }
                    if (PHP_CODESNIFFER_VERBOSITY > 1) {
                        echo str_repeat("\t", $depth);
                        echo "\t\tExcluding rule \"" . $exclude['name'] . '"' . PHP_EOL;
                    }
                    // Check if a single code is being excluded, which is a shortcut
                    // for setting the severity of the message to 0.
                    $parts = explode('.', $exclude['name']);
                    if (count($parts) === 4) {
                        $this->ruleset[(string) $exclude['name']]['severity'] = 0;
                        if (PHP_CODESNIFFER_VERBOSITY > 1) {
                            echo str_repeat("\t", $depth);
                            echo "\t\t=> severity set to 0" . PHP_EOL;
                        }
                    } else {
                        $excluded_sniffs = array_merge($excluded_sniffs, $this->expand_ruleset_reference((string) $exclude['name'], $ruleset_dir, $depth + 1));
                    }
                }
                //end foreach
            }
            //end if
            $this->process_rule($rule, $new_sniffs, $depth);
        }
        //end foreach
        // Process custom command line arguments.
        $cli_args = [];
        foreach ($ruleset->{'arg'} as $arg) {
            if ($this->should_process_element($arg) === false) {
                continue;
            }
            if (isset($arg['name']) === true) {
                $arg_string = '--' . $arg['name'];
                if (isset($arg['value']) === true) {
                    $arg_string .= '=' . $arg['value'];
                }
            } else {
                $arg_string = '-' . $arg['value'];
            }
            $cli_args[] = $arg_string;
            if (PHP_CODESNIFFER_VERBOSITY > 1) {
                echo str_repeat("\t", $depth);
                echo "\t=> set command line value {$arg_string}" . PHP_EOL;
            }
        }
        //end foreach
        // Set custom php ini values as CLI args.
        foreach ($ruleset->{'ini'} as $arg) {
            if ($this->should_process_element($arg) === false) {
                continue;
            }
            if (isset($arg['name']) === false) {
                continue;
            }
            $name = (string) $arg['name'];
            $arg_string = $name;
            if (isset($arg['value']) === true) {
                $value = (string) $arg['value'];
                $arg_string .= "={$value}";
            } else {
                $value = 'true';
            }
            $cli_args[] = '-d';
            $cli_args[] = $arg_string;
            if (PHP_CODESNIFFER_VERBOSITY > 1) {
                echo str_repeat("\t", $depth);
                echo "\t=> set PHP ini value {$name} to {$value}" . PHP_EOL;
            }
        }
        //end foreach
        if (empty($this->config->files) === true) {
            // Process hard-coded file paths.
            foreach ($ruleset->{'file'} as $file) {
                $file = (string) $file;
                $cli_args[] = $file;
                if (PHP_CODESNIFFER_VERBOSITY > 1) {
                    echo str_repeat("\t", $depth);
                    echo "\t=> added \"{$file}\" to the file list" . PHP_EOL;
                }
            }
        }
        if (empty($cli_args) === false) {
            // Change the directory so all relative paths are worked
            // out based on the location of the ruleset instead of
            // the location of the user.
            $in_phar = Util\Common::is_phar_file($ruleset_dir);
            if ($in_phar === false) {
                $current_dir = getcwd();
                chdir($ruleset_dir);
            }
            $this->config->set_command_line_values($cli_args);
            if ($in_phar === false) {
                chdir($current_dir);
            }
        }
        // Process custom ignore pattern rules.
        foreach ($ruleset->{'exclude-pattern'} as $pattern) {
            if ($this->should_process_element($pattern) === false) {
                continue;
            }
            if (isset($pattern['type']) === false) {
                $pattern['type'] = 'absolute';
            }
            $this->ignore_patterns[(string) $pattern] = (string) $pattern['type'];
            if (PHP_CODESNIFFER_VERBOSITY > 1) {
                echo str_repeat("\t", $depth);
                echo "\t=> added global " . $pattern['type'] . ' ignore pattern: ' . $pattern . PHP_EOL;
            }
        }
        $included_sniffs = array_unique(array_merge($own_sniffs, $included_sniffs));
        $excluded_sniffs = array_unique($excluded_sniffs);
        if (PHP_CODESNIFFER_VERBOSITY > 1) {
            $included = count($included_sniffs);
            $excluded = count($excluded_sniffs);
            echo str_repeat("\t", $depth);
            echo "=> Ruleset processing complete; included {$included} sniffs and excluded {$excluded}" . PHP_EOL;
        }
        // Merge our own sniff list with our externally included
        // sniff list, but filter out any excluded sniffs.
        $files = [];
        foreach ($included_sniffs as $sniff) {
            if (in_array($sniff, $excluded_sniffs, true) === true) {
                continue;
            }
            $files[] = Util\Common::realpath($sniff);
        }
        return $files;
    }
    //end processRuleset()
    /**
     * Expands a directory into a list of sniff files within.
     *
     * @param string $directory The path to a directory.
     * @param int    $depth     How many nested processing steps we are in. This
     *                          is only used for debug output.
     *
     * @return array
     */
    private function expand_sniff_directory($directory, $depth = 0)
    {
        $sniffs = [];
        $rdi = new \Recursive_Directory_Iterator($directory, \Recursive_Directory_Iterator::FOLLOW_SYMLINKS);
        $di = new \Recursive_Iterator_Iterator($rdi, 0, \Recursive_Iterator_Iterator::CATCH_GET_CHILD);
        $dir_len = strlen($directory);
        foreach ($di as $file) {
            $filename = $file->get_filename();
            // Skip hidden files.
            if (substr($filename, 0, 1) === '.') {
                continue;
            }
            // We are only interested in PHP and sniff files.
            $file_parts = explode('.', $filename);
            if (array_pop($file_parts) !== 'php') {
                continue;
            }
            $basename = basename($filename, '.php');
            if (substr($basename, -5) !== 'Sniff') {
                continue;
            }
            $path = $file->get_pathname();
            // Skip files in hidden directories within the Sniffs directory of this
            // standard. We use the offset with strpos() to allow hidden directories
            // before, valid example:
            // /home/foo/.composer/vendor/squiz/custom_tool/MyStandard/Sniffs/...
            if (strpos($path, DIRECTORY_SEPARATOR . '.', $dir_len) !== false) {
                continue;
            }
            if (PHP_CODESNIFFER_VERBOSITY > 1) {
                echo str_repeat("\t", $depth);
                echo "\t\t=> " . Util\Common::strip_basepath($path, $this->config->basepath) . PHP_EOL;
            }
            $sniffs[] = $path;
        }
        //end foreach
        return $sniffs;
    }
    //end expandSniffDirectory()
    /**
     * Expands a ruleset reference into a list of sniff files.
     *
     * @param string $ref        The reference from the ruleset XML file.
     * @param string $rulesetDir The directory of the ruleset XML file, used to
     *                           evaluate relative paths.
     * @param int    $depth      How many nested processing steps we are in. This
     *                           is only used for debug output.
     *
     * @return array
     * @throws \PHP_CodeSniffer\Exceptions\RuntimeException If the reference is invalid.
     */
    private function expand_ruleset_reference($ref, $ruleset_dir, $depth = 0)
    {
        // Ignore internal sniffs codes as they are used to only
        // hide and change internal messages.
        if (substr($ref, 0, 9) === 'Internal.') {
            if (PHP_CODESNIFFER_VERBOSITY > 1) {
                echo str_repeat("\t", $depth);
                echo "\t\t* ignoring internal sniff code *" . PHP_EOL;
            }
            return [];
        }
        // As sniffs can't begin with a full stop, assume references in
        // this format are relative paths and attempt to convert them
        // to absolute paths. If this fails, let the reference run through
        // the normal checks and have it fail as normal.
        if (substr($ref, 0, 1) === '.') {
            $realpath = Util\Common::realpath($ruleset_dir . '/' . $ref);
            if ($realpath !== false) {
                $ref = $realpath;
                if (PHP_CODESNIFFER_VERBOSITY > 1) {
                    echo str_repeat("\t", $depth);
                    echo "\t\t=> " . Util\Common::strip_basepath($ref, $this->config->basepath) . PHP_EOL;
                }
            }
        }
        // As sniffs can't begin with a tilde, assume references in
        // this format are relative to the user's home directory.
        if (substr($ref, 0, 2) === '~/') {
            $realpath = Util\Common::realpath($ref);
            if ($realpath !== false) {
                $ref = $realpath;
                if (PHP_CODESNIFFER_VERBOSITY > 1) {
                    echo str_repeat("\t", $depth);
                    echo "\t\t=> " . Util\Common::strip_basepath($ref, $this->config->basepath) . PHP_EOL;
                }
            }
        }
        if (is_file($ref) === true) {
            if (substr($ref, -9) === 'Sniff.php') {
                // A single external sniff.
                $this->ruleset_dirs[] = dirname(dirname(dirname($ref)));
                return [$ref];
            }
        } else {
            // See if this is a whole standard being referenced.
            $path = Util\Standards::get_installed_standard_path($ref);
            if ($path !== null && Util\Common::is_phar_file($path) === true && strpos($path, 'ruleset.xml') === false) {
                // If the ruleset exists inside the phar file, use it.
                if (file_exists($path . DIRECTORY_SEPARATOR . 'ruleset.xml') === true) {
                    $path .= DIRECTORY_SEPARATOR . 'ruleset.xml';
                } else {
                    $path = null;
                }
            }
            if ($path !== null) {
                $ref = $path;
                if (PHP_CODESNIFFER_VERBOSITY > 1) {
                    echo str_repeat("\t", $depth);
                    echo "\t\t=> " . Util\Common::strip_basepath($ref, $this->config->basepath) . PHP_EOL;
                }
            } elseif (is_dir($ref) === false) {
                // Work out the sniff path.
                $sep_pos = strpos($ref, DIRECTORY_SEPARATOR);
                if ($sep_pos !== false) {
                    $std_name = substr($ref, 0, $sep_pos);
                    $path = substr($ref, $sep_pos);
                } else {
                    $parts = explode('.', $ref);
                    $std_name = $parts[0];
                    if (count($parts) === 1) {
                        // A whole standard?
                        $path = '';
                    } elseif (count($parts) === 2) {
                        // A directory of sniffs?
                        $path = DIRECTORY_SEPARATOR . 'Sniffs' . DIRECTORY_SEPARATOR . $parts[1];
                    } else {
                        // A single sniff?
                        $path = DIRECTORY_SEPARATOR . 'Sniffs' . DIRECTORY_SEPARATOR . $parts[1] . DIRECTORY_SEPARATOR . $parts[2] . 'Sniff.php';
                    }
                }
                $new_ref = false;
                $std_path = Util\Standards::get_installed_standard_path($std_name);
                if ($std_path !== null && $path !== '') {
                    if (Util\Common::is_phar_file($std_path) === true && strpos($std_path, 'ruleset.xml') === false) {
                        // Phar files can only return the directory,
                        // since ruleset can be omitted if building one standard.
                        $new_ref = Util\Common::realpath($std_path . $path);
                    } else {
                        $new_ref = Util\Common::realpath(dirname($std_path) . $path);
                    }
                }
                if ($new_ref === false) {
                    // The sniff is not locally installed, so check if it is being
                    // referenced as a remote sniff outside the install. We do this
                    // by looking through all directories where we have found ruleset
                    // files before, looking for ones for this particular standard,
                    // and seeing if it is in there.
                    foreach ($this->ruleset_dirs as $dir) {
                        if (strtolower(basename($dir)) !== strtolower($std_name)) {
                            continue;
                        }
                        $new_ref = Util\Common::realpath($dir . $path);
                        if ($new_ref !== false) {
                            $ref = $new_ref;
                        }
                    }
                } else {
                    $ref = $new_ref;
                }
                if (PHP_CODESNIFFER_VERBOSITY > 1) {
                    echo str_repeat("\t", $depth);
                    echo "\t\t=> " . Util\Common::strip_basepath($ref, $this->config->basepath) . PHP_EOL;
                }
            }
            //end if
        }
        //end if
        if (is_dir($ref) === true) {
            if (is_file($ref . DIRECTORY_SEPARATOR . 'ruleset.xml') === true) {
                // We are referencing an external coding standard.
                if (PHP_CODESNIFFER_VERBOSITY > 1) {
                    echo str_repeat("\t", $depth);
                    echo "\t\t* rule is referencing a standard using directory name; processing *" . PHP_EOL;
                }
                return $this->process_ruleset($ref . DIRECTORY_SEPARATOR . 'ruleset.xml', $depth + 2);
            }
            // We are referencing a whole directory of sniffs.
            if (PHP_CODESNIFFER_VERBOSITY > 1) {
                echo str_repeat("\t", $depth);
                echo "\t\t* rule is referencing a directory of sniffs *" . PHP_EOL;
                echo str_repeat("\t", $depth);
                echo "\t\tAdding sniff files from directory" . PHP_EOL;
            }
            return $this->expand_sniff_directory($ref, $depth + 1);
        }
        if (is_file($ref) === false) {
            $error = "Referenced sniff \"{$ref}\" does not exist";
            throw new RuntimeException($error);
        }
        if (substr($ref, -9) === 'Sniff.php') {
            // A single sniff.
            return [$ref];
        }
        // Assume an external ruleset.xml file.
        if (PHP_CODESNIFFER_VERBOSITY > 1) {
            echo str_repeat("\t", $depth);
            echo "\t\t* rule is referencing a standard using ruleset path; processing *" . PHP_EOL;
        }
        return $this->process_ruleset($ref, $depth + 2);
        //end if
    }
    //end expandRulesetReference()
    /**
     * Processes a rule from a ruleset XML file, overriding built-in defaults.
     *
     * @param \SimpleXMLElement $rule      The rule object from a ruleset XML file.
     * @param string[]          $newSniffs An array of sniffs that got included by this rule.
     * @param int               $depth     How many nested processing steps we are in.
     *                                     This is only used for debug output.
     *
     * @return void
     * @throws \PHP_CodeSniffer\Exceptions\RuntimeException If rule settings are invalid.
     */
    private function process_rule(array $rule, array $new_sniffs, $depth = 0)
    {
        $ref = (string) $rule['ref'];
        $todo = [$ref];
        $parts = explode('.', $ref);
        $parts_count = count($parts);
        if ($parts_count <= 2 || $parts_count > count(array_filter($parts)) || in_array($ref, $new_sniffs) === true) {
            // We are processing a standard, a category of sniffs or a relative path inclusion.
            foreach ($new_sniffs as $sniff_file) {
                $parts = explode(DIRECTORY_SEPARATOR, $sniff_file);
                if (count($parts) === 1 && DIRECTORY_SEPARATOR === '\\') {
                    // Path using forward slashes while running on Windows.
                    $parts = explode('/', $sniff_file);
                }
                $sniff_name = array_pop($parts);
                $sniff_category = array_pop($parts);
                array_pop($parts);
                $sniff_standard = array_pop($parts);
                $todo[] = $sniff_standard . '.' . $sniff_category . '.' . substr($sniff_name, 0, -9);
            }
        }
        foreach ($todo as $code) {
            // Custom severity.
            if (isset($rule->severity) === true && $this->should_process_element($rule->severity) === true) {
                if (isset($this->ruleset[$code]) === false) {
                    $this->ruleset[$code] = [];
                }
                $this->ruleset[$code]['severity'] = (int) $rule->severity;
                if (PHP_CODESNIFFER_VERBOSITY > 1) {
                    echo str_repeat("\t", $depth);
                    echo "\t\t=> severity set to " . (int) $rule->severity;
                    if ($code !== $ref) {
                        echo " for {$code}";
                    }
                    echo PHP_EOL;
                }
            }
            // Custom message type.
            if (isset($rule->type) === true && $this->should_process_element($rule->type) === true) {
                if (isset($this->ruleset[$code]) === false) {
                    $this->ruleset[$code] = [];
                }
                $type = strtolower((string) $rule->type);
                if ($type !== 'error' && $type !== 'warning') {
                    throw new RuntimeException("Message type \"{$type}\" is invalid; must be \"error\" or \"warning\"");
                }
                $this->ruleset[$code]['type'] = $type;
                if (PHP_CODESNIFFER_VERBOSITY > 1) {
                    echo str_repeat("\t", $depth);
                    echo "\t\t=> message type set to " . $rule->type;
                    if ($code !== $ref) {
                        echo " for {$code}";
                    }
                    echo PHP_EOL;
                }
            }
            //end if
            // Custom message.
            if (isset($rule->message) === true && $this->should_process_element($rule->message) === true) {
                if (isset($this->ruleset[$code]) === false) {
                    $this->ruleset[$code] = [];
                }
                $this->ruleset[$code]['message'] = (string) $rule->message;
                if (PHP_CODESNIFFER_VERBOSITY > 1) {
                    echo str_repeat("\t", $depth);
                    echo "\t\t=> message set to " . $rule->message;
                    if ($code !== $ref) {
                        echo " for {$code}";
                    }
                    echo PHP_EOL;
                }
            }
            // Custom properties.
            if (isset($rule->properties) === true && $this->should_process_element($rule->properties) === true) {
                $property_scope = 'standard';
                if ($code === $ref || substr($ref, -9) === 'Sniff.php') {
                    $property_scope = 'sniff';
                }
                foreach ($rule->properties->property as $prop) {
                    if ($this->should_process_element($prop) === false) {
                        continue;
                    }
                    if (isset($this->ruleset[$code]) === false) {
                        $this->ruleset[$code] = ['properties' => []];
                    } elseif (isset($this->ruleset[$code]['properties']) === false) {
                        $this->ruleset[$code]['properties'] = [];
                    }
                    $name = (string) $prop['name'];
                    if (isset($prop['type']) === true && (string) $prop['type'] === 'array') {
                        $values = [];
                        if (isset($prop['extend']) === true && (string) $prop['extend'] === 'true' && isset($this->ruleset[$code]['properties'][$name]['value']) === true) {
                            $values = $this->ruleset[$code]['properties'][$name]['value'];
                        }
                        if (isset($prop->element) === true) {
                            $print_value = '';
                            foreach ($prop->element as $element) {
                                if ($this->should_process_element($element) === false) {
                                    continue;
                                }
                                $value = (string) $element['value'];
                                if (isset($element['key']) === true) {
                                    $key = (string) $element['key'];
                                    $values[$key] = $value;
                                    $print_value .= $key . '=>' . $value . ',';
                                } else {
                                    $values[] = $value;
                                    $print_value .= $value . ',';
                                }
                            }
                            $print_value = rtrim($print_value, ',');
                        } else {
                            $value = (string) $prop['value'];
                            $print_value = $value;
                            foreach (explode(',', $value) as $val) {
                                list($k, $v) = explode('=>', $val . '=>');
                                if ($v !== '') {
                                    $values[trim($k)] = trim($v);
                                } else {
                                    $values[] = trim($k);
                                }
                            }
                        }
                        //end if
                        $this->ruleset[$code]['properties'][$name] = ['value' => $values, 'scope' => $property_scope];
                        if (PHP_CODESNIFFER_VERBOSITY > 1) {
                            echo str_repeat("\t", $depth);
                            echo "\t\t=> array property \"{$name}\" set to \"{$print_value}\"";
                            if ($code !== $ref) {
                                echo " for {$code}";
                            }
                            echo PHP_EOL;
                        }
                    } else {
                        $this->ruleset[$code]['properties'][$name] = ['value' => (string) $prop['value'], 'scope' => $property_scope];
                        if (PHP_CODESNIFFER_VERBOSITY > 1) {
                            echo str_repeat("\t", $depth);
                            echo "\t\t=> property \"{$name}\" set to \"" . $prop['value'] . '"';
                            if ($code !== $ref) {
                                echo " for {$code}";
                            }
                            echo PHP_EOL;
                        }
                    }
                    //end if
                }
                //end foreach
            }
            //end if
            // Ignore patterns.
            foreach ($rule->{'exclude-pattern'} as $pattern) {
                if ($this->should_process_element($pattern) === false) {
                    continue;
                }
                if (isset($this->ignore_patterns[$code]) === false) {
                    $this->ignore_patterns[$code] = [];
                }
                if (isset($pattern['type']) === false) {
                    $pattern['type'] = 'absolute';
                }
                $this->ignore_patterns[$code][(string) $pattern] = (string) $pattern['type'];
                if (PHP_CODESNIFFER_VERBOSITY > 1) {
                    echo str_repeat("\t", $depth);
                    echo "\t\t=> added rule-specific " . $pattern['type'] . ' ignore pattern';
                    if ($code !== $ref) {
                        echo " for {$code}";
                    }
                    echo ': ' . $pattern . PHP_EOL;
                }
            }
            //end foreach
            // Include patterns.
            foreach ($rule->{'include-pattern'} as $pattern) {
                if ($this->should_process_element($pattern) === false) {
                    continue;
                }
                if (isset($this->include_patterns[$code]) === false) {
                    $this->include_patterns[$code] = [];
                }
                if (isset($pattern['type']) === false) {
                    $pattern['type'] = 'absolute';
                }
                $this->include_patterns[$code][(string) $pattern] = (string) $pattern['type'];
                if (PHP_CODESNIFFER_VERBOSITY > 1) {
                    echo str_repeat("\t", $depth);
                    echo "\t\t=> added rule-specific " . $pattern['type'] . ' include pattern';
                    if ($code !== $ref) {
                        echo " for {$code}";
                    }
                    echo ': ' . $pattern . PHP_EOL;
                }
            }
            //end foreach
        }
        //end foreach
    }
    //end processRule()
    /**
     * Determine if an element should be processed or ignored.
     *
     * @param \SimpleXMLElement $element An object from a ruleset XML file.
     *
     * @return bool
     */
    private function should_process_element($element)
    {
        if (isset($element['phpcbf-only']) === false && isset($element['phpcs-only']) === false) {
            // No exceptions are being made.
            return true;
        }
        if (PHP_CODESNIFFER_CBF === true && isset($element['phpcbf-only']) === true && (string) $element['phpcbf-only'] === 'true') {
            return true;
        }
        if (PHP_CODESNIFFER_CBF === false && isset($element['phpcs-only']) === true && (string) $element['phpcs-only'] === 'true') {
            return true;
        }
        return false;
    }
    //end shouldProcessElement()
    /**
     * Loads and stores sniffs objects used for sniffing files.
     *
     * @param array $files        Paths to the sniff files to register.
     * @param array $restrictions The sniff class names to restrict the allowed
     *                            listeners to.
     * @param array $exclusions   The sniff class names to exclude from the
     *                            listeners list.
     *
     * @return void
     */
    public function register_sniffs($files, $restrictions, $exclusions)
    {
        $listeners = [];
        foreach ($files as $file) {
            // Work out where the position of /StandardName/Sniffs/... is
            // so we can determine what the class will be called.
            $sniff_pos = strrpos($file, DIRECTORY_SEPARATOR . 'Sniffs' . DIRECTORY_SEPARATOR);
            if ($sniff_pos === false) {
                continue;
            }
            $slash_pos = strrpos(substr($file, 0, $sniff_pos), DIRECTORY_SEPARATOR);
            if ($slash_pos === false) {
                continue;
            }
            $class_name = Autoload::load_file($file);
            $compare_name = Util\Common::clean_sniff_class($class_name);
            // If they have specified a list of sniffs to restrict to, check
            // to see if this sniff is allowed.
            if (empty($restrictions) === false && isset($restrictions[$compare_name]) === false) {
                continue;
            }
            // If they have specified a list of sniffs to exclude, check
            // to see if this sniff is allowed.
            if (empty($exclusions) === false && isset($exclusions[$compare_name]) === true) {
                continue;
            }
            // Skip abstract classes.
            $reflection = new \ReflectionClass($class_name);
            if ($reflection->is_abstract() === true) {
                continue;
            }
            $listeners[$class_name] = $class_name;
            if (PHP_CODESNIFFER_VERBOSITY > 2) {
                echo "Registered {$class_name}" . PHP_EOL;
            }
        }
        //end foreach
        $this->sniffs = $listeners;
    }
    //end registerSniffs()
    /**
     * Populates the array of PHP_CodeSniffer_Sniff objects for this file.
     *
     * @return void
     * @throws \PHP_CodeSniffer\Exceptions\RuntimeException If sniff registration fails.
     */
    public function populate_token_listeners()
    {
        // Construct a list of listeners indexed by token being listened for.
        $this->token_listeners = [];
        foreach ($this->sniffs as $sniff_class => $sniff_object) {
            $this->sniffs[$sniff_class] = null;
            $this->sniffs[$sniff_class] = new $sniff_class();
            $sniff_code = Util\Common::get_sniff_code($sniff_class);
            $this->sniff_codes[$sniff_code] = $sniff_class;
            // Set custom properties.
            if (isset($this->ruleset[$sniff_code]['properties']) === true) {
                foreach ($this->ruleset[$sniff_code]['properties'] as $name => $settings) {
                    $this->set_sniff_property($sniff_class, $name, $settings);
                }
            }
            $tokenizers = [];
            $vars = get_class_vars($sniff_class);
            if (isset($vars['supportedTokenizers']) === true) {
                foreach ($vars['supportedTokenizers'] as $tokenizer) {
                    $tokenizers[$tokenizer] = $tokenizer;
                }
            } else {
                $tokenizers = ['PHP' => 'PHP'];
            }
            $tokens = $this->sniffs[$sniff_class]->register();
            if (is_array($tokens) === false) {
                $msg = "Sniff {$sniff_class} register() method must return an array";
                throw new RuntimeException($msg);
            }
            $ignore_patterns = [];
            $patterns = $this->get_ignore_patterns($sniff_code);
            foreach ($patterns as $pattern => $type) {
                $replacements = ['\,' => ',', '*' => '.*'];
                $ignore_patterns[] = strtr($pattern, $replacements);
            }
            $include_patterns = [];
            $patterns = $this->get_include_patterns($sniff_code);
            foreach ($patterns as $pattern => $type) {
                $replacements = ['\,' => ',', '*' => '.*'];
                $include_patterns[] = strtr($pattern, $replacements);
            }
            foreach ($tokens as $token) {
                if (isset($this->token_listeners[$token]) === false) {
                    $this->token_listeners[$token] = [];
                }
                if (isset($this->token_listeners[$token][$sniff_class]) === false) {
                    $this->token_listeners[$token][$sniff_class] = ['class' => $sniff_class, 'source' => $sniff_code, 'tokenizers' => $tokenizers, 'ignore' => $ignore_patterns, 'include' => $include_patterns];
                }
            }
        }
        //end foreach
    }
    //end populateTokenListeners()
    /**
     * Set a single property for a sniff.
     *
     * @param string $sniffClass The class name of the sniff.
     * @param string $name       The name of the property to change.
     * @param array  $settings   Array with the new value of the property and the scope of the property being set.
     *
     * @return void
     *
     * @throws \PHP_CodeSniffer\Exceptions\RuntimeException When attempting to set a non-existent property on a sniff
     *                                                      which doesn't declare the property or explicitly supports
     *                                                      dynamic properties.
     */
    public function set_sniff_property($sniff_class, $name, $settings)
    {
        // Setting a property for a sniff we are not using.
        if (isset($this->sniffs[$sniff_class]) === false) {
            return;
        }
        $name = trim($name);
        $property_name = $name;
        if (substr($property_name, -2) === '[]') {
            $property_name = substr($property_name, 0, -2);
        }
        /*
         * BC-compatibility layer for $settings using the pre-PHPCS 3.8.0 format.
         *
         * Prior to PHPCS 3.8.0, `$settings` was expected to only contain the new _value_
         * for the property (which could be an array).
         * Since PHPCS 3.8.0, `$settings` is expected to be an array with two keys: 'scope'
         * and 'value', where 'scope' indicates whether the property should be set to the given 'value'
         * for one individual sniff or for all sniffs in a standard.
         *
         * This BC-layer is only for integrations with PHPCS which may call this method directly
         * and will be removed in PHPCS 4.0.0.
         */
        if (is_array($settings) === false || isset($settings['scope'], $settings['value']) === false) {
            // This will be an "old" format value.
            $settings = ['value' => $settings, 'scope' => 'standard'];
            trigger_error(__FUNCTION__ . ': the format of the $settings parameter has changed from (mixed) $value to array(\'scope\' => \'sniff|standard\', \'value\' => $value). Please update your integration code. See PR #3629 for more information.', E_USER_DEPRECATED);
        }
        $is_settable = false;
        $sniff_object = $this->sniffs[$sniff_class];
        if (property_exists($sniff_object, $property_name) === true || $sniff_object instanceof stdClass === true || method_exists($sniff_object, '__set') === true) {
            $is_settable = true;
        }
        if ($is_settable === false) {
            if ($settings['scope'] === 'sniff') {
                $notice = "Ruleset invalid. Property \"{$property_name}\" does not exist on sniff ";
                $notice .= array_search($sniff_class, $this->sniff_codes, true);
                throw new RuntimeException($notice);
            }
            return;
        }
        $value = $settings['value'];
        if (is_string($value) === true) {
            $value = trim($value);
        }
        if ($value === '') {
            $value = null;
        }
        // Special case for booleans.
        if ($value === 'true') {
            $value = true;
        } elseif ($value === 'false') {
            $value = false;
        } elseif (substr($name, -2) === '[]') {
            $name = $property_name;
            $values = [];
            if ($value !== null) {
                foreach (explode(',', $value) as $val) {
                    list($k, $v) = explode('=>', $val . '=>');
                    if ($v !== '') {
                        $values[trim($k)] = trim($v);
                    } else {
                        $values[] = trim($k);
                    }
                }
            }
            $value = $values;
        }
        $sniff_object->{$name} = $value;
    }
    //end setSniffProperty()
    /**
     * Gets the array of ignore patterns.
     *
     * Optionally takes a listener to get ignore patterns specified
     * for that sniff only.
     *
     * @param string $listener The listener to get patterns for. If NULL, all
     *                         patterns are returned.
     *
     * @return array
     */
    public function get_ignore_patterns($listener = null)
    {
        if ($listener === null) {
            return $this->ignore_patterns;
        }
        if (isset($this->ignore_patterns[$listener]) === true) {
            return $this->ignore_patterns[$listener];
        }
        return [];
    }
    //end getIgnorePatterns()
    /**
     * Gets the array of include patterns.
     *
     * Optionally takes a listener to get include patterns specified
     * for that sniff only.
     *
     * @param string $listener The listener to get patterns for. If NULL, all
     *                         patterns are returned.
     *
     * @return array
     */
    public function get_include_patterns($listener = null)
    {
        if ($listener === null) {
            return $this->include_patterns;
        }
        if (isset($this->include_patterns[$listener]) === true) {
            return $this->include_patterns[$listener];
        }
        return [];
    }
    //end getIncludePatterns()
}
//end class