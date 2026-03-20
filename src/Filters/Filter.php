<?php

declare (strict_types=1);
/**
 * A base filter class for filtering out files and folders during a run.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Filters;

use Php_code_Sniffer\Config;
use Php_code_Sniffer\Ruleset;
use Php_code_Sniffer\Util;
use Return_Type_Will_Change;
class Filter extends \Recursive_Filter_Iterator
{
    /**
     * The top-level path we are filtering.
     *
     * @var string
     */
    protected $basedir;
    /**
     * The config data for the run.
     *
     * @var \PHP_CodeSniffer\Config
     */
    protected $config;
    /**
     * The ruleset used for the run.
     *
     * @var \PHP_CodeSniffer\Ruleset
     */
    protected $ruleset;
    /**
     * A list of ignore patterns that apply to directories only.
     *
     * @var array
     */
    protected $ignore_dir_patterns;
    /**
     * A list of ignore patterns that apply to files only.
     *
     * @var array
     */
    protected $ignore_file_patterns;
    /**
     * A list of file paths we've already accepted.
     *
     * Used to ensure we aren't following circular symlinks.
     *
     * @var array
     */
    protected $accepted_paths = [];
    /**
     * Constructs a filter.
     *
     * @param \RecursiveIterator       $iterator The iterator we are using to get file paths.
     * @param string                   $basedir  The top-level path we are filtering.
     * @param \PHP_CodeSniffer\Config  $config   The config data for the run.
     * @param \PHP_CodeSniffer\Ruleset $ruleset  The ruleset used for the run.
     */
    public function __construct(\Recursive_Iterator $iterator, $basedir, Config $config, Ruleset $ruleset)
    {
        parent::__construct($iterator);
        $this->basedir = $basedir;
        $this->config = $config;
        $this->ruleset = $ruleset;
    }
    //end __construct()
    /**
     * Check whether the current element of the iterator is acceptable.
     *
     * Files are checked for allowed extensions and ignore patterns.
     * Directories are checked for ignore patterns only.
     *
     * @return bool
     */
    #[Return_Type_Will_Change]
    public function accept()
    {
        $file_path = $this->current();
        $real_path = Util\Common::realpath($file_path);
        if ($real_path !== false) {
            // It's a real path somewhere, so record it
            // to check for circular symlinks.
            if (isset($this->accepted_paths[$real_path]) === true) {
                // We've been here before.
                return false;
            }
        }
        $file_path = $this->current();
        if (is_dir($file_path) === true) {
            if ($this->config->local === true) {
                return false;
            }
        } elseif ($this->should_process_file($file_path) === false) {
            return false;
        }
        if ($this->should_ignore_path($file_path) === true) {
            return false;
        }
        $this->accepted_paths[$real_path] = true;
        return true;
    }
    //end accept()
    /**
     * Returns an iterator for the current entry.
     *
     * Ensures that the ignore patterns are preserved so they don't have
     * to be generated each time.
     *
     * @return \RecursiveIterator
     */
    #[Return_Type_Will_Change]
    public function get_children()
    {
        $filter_class = get_called_class();
        $children = new $filter_class(new \Recursive_Directory_Iterator($this->current(), \Recursive_Directory_Iterator::SKIP_DOTS | \Filesystem_Iterator::FOLLOW_SYMLINKS), $this->basedir, $this->config, $this->ruleset);
        // Set the ignore patterns so we don't have to generate them again.
        $children->ignore_dir_patterns = $this->ignore_dir_patterns;
        $children->ignore_file_patterns = $this->ignore_file_patterns;
        $children->accepted_paths = $this->accepted_paths;
        return $children;
    }
    //end getChildren()
    /**
     * Checks filtering rules to see if a file should be checked.
     *
     * Checks both file extension filters and path ignore filters.
     *
     * @param string $path The path to the file being checked.
     *
     * @return bool
     */
    protected function should_process_file($path)
    {
        // Check that the file's extension is one we are checking.
        // We are strict about checking the extension and we don't
        // let files through with no extension or that start with a dot.
        $file_name = basename($path);
        $file_parts = explode('.', $file_name);
        if ($file_parts[0] === $file_name || $file_parts[0] === '') {
            return false;
        }
        // Checking multi-part file extensions, so need to create a
        // complete extension list and make sure one is allowed.
        $extensions = [];
        array_shift($file_parts);
        foreach ($file_parts as $part) {
            $extensions[implode('.', $file_parts)] = 1;
            array_shift($file_parts);
        }
        $matches = array_intersect_key($extensions, $this->config->extensions);
        if (empty($matches) === true) {
            return false;
        }
        return true;
    }
    //end shouldProcessFile()
    /**
     * Checks filtering rules to see if a path should be ignored.
     *
     * @param string $path The path to the file or directory being checked.
     *
     * @return bool
     */
    protected function should_ignore_path($path)
    {
        if ($this->ignore_file_patterns === null) {
            $this->ignore_dir_patterns = [];
            $this->ignore_file_patterns = [];
            $ignore_patterns = $this->config->ignored;
            $ruleset_ignore_patterns = $this->ruleset->get_ignore_patterns();
            foreach ($ruleset_ignore_patterns as $pattern => $type) {
                // Ignore standard/sniff specific exclude rules.
                if (is_array($type) === true) {
                    continue;
                }
                $ignore_patterns[$pattern] = $type;
            }
            foreach ($ignore_patterns as $pattern => $type) {
                // If the ignore pattern ends with /* then it is ignoring an entire directory.
                if (substr($pattern, -2) === '/*') {
                    // Need to check this pattern for dirs as well as individual file paths.
                    $this->ignore_file_patterns[$pattern] = $type;
                    $pattern = substr($pattern, 0, -2) . '(?=/|$)';
                    $this->ignore_dir_patterns[$pattern] = $type;
                } else {
                    // This is a file-specific pattern, so only need to check this
                    // for individual file paths.
                    $this->ignore_file_patterns[$pattern] = $type;
                }
            }
        }
        //end if
        $relative_path = $path;
        if (strpos($path, $this->basedir) === 0) {
            // The +1 cuts off the directory separator as well.
            $relative_path = substr($path, strlen($this->basedir) + 1);
        }
        if (is_dir($path) === true) {
            $ignore_patterns = $this->ignore_dir_patterns;
        } else {
            $ignore_patterns = $this->ignore_file_patterns;
        }
        foreach ($ignore_patterns as $pattern => $type) {
            // Maintains backwards compatibility in case the ignore pattern does
            // not have a relative/absolute value.
            if (is_int($pattern) === true) {
                $pattern = $type;
                $type = 'absolute';
            }
            $replacements = ['\,' => ',', '*' => '.*'];
            // We assume a / directory separator, as do the exclude rules
            // most developers write, so we need a special case for any system
            // that is different.
            if (DIRECTORY_SEPARATOR === '\\') {
                $replacements['/'] = '\\\\';
            }
            $pattern = strtr($pattern, $replacements);
            if ($type === 'relative') {
                $test_path = $relative_path;
            } else {
                $test_path = $path;
            }
            $pattern = '`' . $pattern . '`i';
            if (preg_match($pattern, $test_path) === 1) {
                return true;
            }
        }
        //end foreach
        return false;
    }
    //end shouldIgnorePath()
}
//end class