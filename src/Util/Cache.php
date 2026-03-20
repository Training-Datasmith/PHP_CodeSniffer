<?php

declare (strict_types=1);
/**
 * Function for caching between runs.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Util;

use Php_code_Sniffer\Autoload;
use Php_code_Sniffer\Config;
use Php_code_Sniffer\Ruleset;
class Cache
{
    /**
     * The filesystem location of the cache file.
     *
     * @var void
     */
    private static $path = '';
    /**
     * The cached data.
     *
     * @var array<string, mixed>
     */
    private static $cache = [];
    /**
     * Loads existing cache data for the run, if any.
     *
     * @param \PHP_CodeSniffer\Ruleset $ruleset The ruleset used for the run.
     * @param \PHP_CodeSniffer\Config  $config  The config data for the run.
     *
     * @return void
     */
    public static function load(Ruleset $ruleset, Config $config)
    {
        // Look at every loaded sniff class so far and use their file contents
        // to generate a hash for the code used during the run.
        // At this point, the loaded class list contains the core PHPCS code
        // and all sniffs that have been loaded as part of the run.
        if (PHP_CODESNIFFER_VERBOSITY > 1) {
            echo PHP_EOL . "\tGenerating loaded file list for code hash" . PHP_EOL;
        }
        $code_hash_files = [];
        $classes = array_keys(Autoload::get_loaded_classes());
        sort($classes);
        $install_dir = dirname(__DIR__);
        $install_dir_len = strlen($install_dir);
        $standard_dir = $install_dir . DIRECTORY_SEPARATOR . 'Standards';
        $standard_dir_len = strlen($standard_dir);
        foreach ($classes as $file) {
            if (substr($file, 0, $standard_dir_len) !== $standard_dir) {
                if (substr($file, 0, $install_dir_len) === $install_dir) {
                    // We are only interested in sniffs here.
                    continue;
                }
                if (PHP_CODESNIFFER_VERBOSITY > 1) {
                    echo "\t\t=> external file: {$file}" . PHP_EOL;
                }
            } elseif (PHP_CODESNIFFER_VERBOSITY > 1) {
                echo "\t\t=> internal sniff: {$file}" . PHP_EOL;
            }
            $code_hash_files[] = $file;
        }
        // Add the content of the used rulesets to the hash so that sniff setting
        // changes in the ruleset invalidate the cache.
        $rulesets = $ruleset->paths;
        sort($rulesets);
        foreach ($rulesets as $file) {
            if (substr($file, 0, $standard_dir_len) !== $standard_dir) {
                if (PHP_CODESNIFFER_VERBOSITY > 1) {
                    echo "\t\t=> external ruleset: {$file}" . PHP_EOL;
                }
            } elseif (PHP_CODESNIFFER_VERBOSITY > 1) {
                echo "\t\t=> internal ruleset: {$file}" . PHP_EOL;
            }
            $code_hash_files[] = $file;
        }
        // Go through the core PHPCS code and add those files to the file
        // hash. This ensures that core PHPCS changes will also invalidate the cache.
        // Note that we ignore sniffs here, and any files that don't affect
        // the outcome of the run.
        $di = new \Recursive_Directory_Iterator($install_dir, \Filesystem_Iterator::KEY_AS_PATHNAME | \Filesystem_Iterator::CURRENT_AS_FILEINFO | \Filesystem_Iterator::SKIP_DOTS);
        $filter = new \Recursive_Callback_Filter_Iterator($di, function ($file, $key, $iterator) {
            // Skip non-php files.
            $filename = $file->get_filename();
            if ($file->is_file() === true && substr($filename, -4) !== '.php') {
                return false;
            }
            $file_path = Common::realpath($key);
            if ($file_path === false) {
                return false;
            }
            if ($iterator->has_children() === true && ($filename === 'Standards' || $filename === 'Exceptions' || $filename === 'Reports' || $filename === 'Generators')) {
                return false;
            }
            return true;
        });
        $iterator = new \Recursive_Iterator_Iterator($filter);
        foreach ($iterator as $file) {
            if (PHP_CODESNIFFER_VERBOSITY > 1) {
                echo "\t\t=> core file: {$file}" . PHP_EOL;
            }
            $code_hash_files[] = $file->get_pathname();
        }
        $code_hash = '';
        sort($code_hash_files);
        foreach ($code_hash_files as $file) {
            $code_hash .= md5_file($file);
        }
        $code_hash = md5($code_hash);
        // Along with the code hash, use various settings that can affect
        // the results of a run to create a new hash. This hash will be used
        // in the cache file name.
        $ruleset_hash = md5(var_export($ruleset->ignore_patterns, true) . var_export($ruleset->include_patterns, true));
        $php_extensions_hash = md5(var_export(get_loaded_extensions(), true));
        $config_data = ['phpVersion' => PHP_VERSION_ID, 'phpExtensions' => $php_extensions_hash, 'tabWidth' => $config->tab_width, 'encoding' => $config->encoding, 'recordErrors' => $config->record_errors, 'annotations' => $config->annotations, 'configData' => Config::get_all_config_data(), 'codeHash' => $code_hash, 'rulesetHash' => $ruleset_hash];
        $config_string = var_export($config_data, true);
        $cache_hash = substr(sha1($config_string), 0, 12);
        if (PHP_CODESNIFFER_VERBOSITY > 1) {
            echo "\tGenerating cache key data" . PHP_EOL;
            foreach ($config_data as $key => $value) {
                if (is_array($value) === true) {
                    echo "\t\t=> {$key}:" . PHP_EOL;
                    foreach ($value as $sub_key => $sub_value) {
                        echo "\t\t\t=> {$sub_key}: {$sub_value}" . PHP_EOL;
                    }
                    continue;
                }
                if ($value === true || $value === false) {
                    $value = (int) $value;
                }
                echo "\t\t=> {$key}: {$value}" . PHP_EOL;
            }
            echo "\t\t=> cacheHash: {$cache_hash}" . PHP_EOL;
        }
        //end if
        if ($config->cache_file !== null) {
            $cache_file = $config->cache_file;
        } else {
            // Determine the common paths for all files being checked.
            // We can use this to locate an existing cache file, or to
            // determine where to create a new one.
            if (PHP_CODESNIFFER_VERBOSITY > 1) {
                echo "\tChecking possible cache file paths" . PHP_EOL;
            }
            $paths = [];
            foreach ($config->files as $file) {
                $file = Common::realpath($file);
                while ($file !== DIRECTORY_SEPARATOR) {
                    if (isset($paths[$file]) === false) {
                        $paths[$file] = 1;
                    } else {
                        $paths[$file]++;
                    }
                    $last_file = $file;
                    $file = dirname($file);
                    if ($file === $last_file) {
                        // Just in case something went wrong,
                        // we don't want to end up in an infinite loop.
                        break;
                    }
                }
            }
            ksort($paths);
            $paths = array_reverse($paths);
            $num_files = count($config->files);
            $cache_file = null;
            $cache_dir = getenv('XDG_CACHE_HOME');
            if ($cache_dir === false || is_dir($cache_dir) === false) {
                $cache_dir = sys_get_temp_dir();
            }
            foreach ($paths as $file => $count) {
                if ($count !== $num_files) {
                    unset($paths[$file]);
                    continue;
                }
                $file_hash = substr(sha1($file), 0, 12);
                $test_file = $cache_dir . DIRECTORY_SEPARATOR . "phpcs.{$file_hash}.{$cache_hash}.cache";
                if ($cache_file === null) {
                    // This will be our default location if we can't find
                    // an existing file.
                    $cache_file = $test_file;
                }
                if (PHP_CODESNIFFER_VERBOSITY > 1) {
                    echo "\t\t=> {$test_file}" . PHP_EOL;
                    echo "\t\t\t * based on shared location: {$file} *" . PHP_EOL;
                }
                if (file_exists($test_file) === true) {
                    $cache_file = $test_file;
                    break;
                }
            }
            //end foreach
            if ($cache_file === null) {
                // Unlikely, but just in case $paths is empty for some reason.
                $cache_file = $cache_dir . DIRECTORY_SEPARATOR . "phpcs.{$cache_hash}.cache";
            }
        }
        //end if
        self::$path = $cache_file;
        if (PHP_CODESNIFFER_VERBOSITY > 1) {
            echo "\t=> Using cache file: " . self::$path . PHP_EOL;
        }
        if (file_exists(self::$path) === true) {
            self::$cache = json_decode(file_get_contents(self::$path), true);
            // Verify the contents of the cache file.
            if (self::$cache['config'] !== $config_data) {
                self::$cache = [];
                if (PHP_CODESNIFFER_VERBOSITY > 1) {
                    echo "\t* cache was invalid and has been cleared *" . PHP_EOL;
                }
            }
        } elseif (PHP_CODESNIFFER_VERBOSITY > 1) {
            echo "\t* cache file does not exist *" . PHP_EOL;
        }
        self::$cache['config'] = $config_data;
    }
    //end load()
    /**
     * Saves the current cache to the filesystem.
     *
     * @return void
     */
    public static function save()
    {
        file_put_contents(self::$path, json_encode(self::$cache));
    }
    //end save()
    /**
     * Retrieves a single entry from the cache.
     *
     * @param string $key The key of the data to get. If NULL,
     *                    everything in the cache is returned.
     *
     * @return mixed
     */
    public static function get($key = null)
    {
        if ($key === null) {
            return self::$cache;
        }
        if (isset(self::$cache[$key]) === true) {
            return self::$cache[$key];
        }
        return false;
    }
    //end get()
    /**
     * Retrieves a single entry from the cache.
     *
     * @param string $key   The key of the data to set. If NULL,
     *                      sets the entire cache.
     * @param mixed  $value The value to set.
     *
     * @return void
     */
    public static function set($key, $value)
    {
        if ($key === null) {
            self::$cache = $value;
        } else {
            self::$cache[$key] = $value;
        }
    }
    //end set()
    /**
     * Retrieves the number of cache entries.
     *
     * @return int
     */
    public static function get_size()
    {
        return count(self::$cache) - 1;
    }
    //end getSize()
}
//end class