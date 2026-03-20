<?php

declare (strict_types=1);
/**
 * Represents a list of files on the file system that are to be checked during the run.
 *
 * File objects are created as needed rather than all at once.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Files;

use Php_code_Sniffer\Autoload;
use Php_code_Sniffer\Config;
use Php_code_Sniffer\Exceptions\Deep_Exit_Exception;
use Php_code_Sniffer\Ruleset;
use Php_code_Sniffer\Util;
use Return_Type_Will_Change;
class File_List implements \Iterator, \Countable
{
    /**
     * A list of file paths that are included in the list.
     *
     * @var array
     */
    private $files = [];
    /**
     * The number of files in the list.
     *
     * @var integer
     */
    private $num_files = 0;
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
     * An array of patterns to use for skipping files.
     *
     * @var array
     */
    protected $ignore_patterns = [];
    /**
     * Constructs a file list and loads in an array of file paths to process.
     *
     * @param \PHP_CodeSniffer\Config  $config  The config data for the run.
     * @param \PHP_CodeSniffer\Ruleset $ruleset The ruleset used for the run.
     */
    public function __construct(Config $config, Ruleset $ruleset)
    {
        $this->ruleset = $ruleset;
        $this->config = $config;
        $paths = $config->files;
        foreach ($paths as $path) {
            $is_phar_file = Util\Common::is_phar_file($path);
            if (is_dir($path) === true || $is_phar_file === true) {
                if ($is_phar_file === true) {
                    $path = 'phar://' . $path;
                }
                $filter_class = $this->get_filter_class();
                $di = new \Recursive_Directory_Iterator($path, \Recursive_Directory_Iterator::SKIP_DOTS | \Filesystem_Iterator::FOLLOW_SYMLINKS);
                $filter = new $filter_class($di, $path, $config, $ruleset);
                $iterator = new \Recursive_Iterator_Iterator($filter);
                foreach ($iterator as $file) {
                    $this->files[$file->get_pathname()] = null;
                    $this->num_files++;
                }
            } else {
                $this->add_file($path);
            }
            //end if
        }
        //end foreach
        reset($this->files);
    }
    //end __construct()
    /**
     * Add a file to the list.
     *
     * If a file object has already been created, it can be passed here.
     * If it is left NULL, it will be created when accessed.
     *
     * @param string                      $path The path to the file being added.
     * @param \PHP_CodeSniffer\Files\File $file The file being added.
     *
     * @return void
     */
    public function add_file($path, $file = null)
    {
        // No filtering is done for STDIN when the filename
        // has not been specified.
        if ($path === 'STDIN') {
            $this->files[$path] = $file;
            $this->num_files++;
            return;
        }
        $filter_class = $this->get_filter_class();
        $di = new \Recursive_Array_Iterator([$path]);
        $filter = new $filter_class($di, $path, $this->config, $this->ruleset);
        $iterator = new \Recursive_Iterator_Iterator($filter);
        foreach ($iterator as $path) {
            $this->files[$path] = $file;
            $this->num_files++;
        }
    }
    //end addFile()
    /**
     * Get the class name of the filter being used for the run.
     *
     * @return string
     * @throws \PHP_CodeSniffer\Exceptions\DeepExitException If the specified filter could not be found.
     */
    private function get_filter_class()
    {
        $filter_type = $this->config->filter;
        if ($filter_type === null) {
            $filter_class = '\PHP_CodeSniffer\Filters\Filter';
        } else if (strpos($filter_type, '.') !== false) {
            // This is a path to a custom filter class.
            $filename = realpath($filter_type);
            if ($filename === false) {
                $error = "ERROR: Custom filter \"{$filter_type}\" not found" . PHP_EOL;
                throw new Deep_Exit_Exception($error, 3);
            }
            $filter_class = Autoload::load_file($filename);
        } else {
            $filter_class = '\PHP_CodeSniffer\Filters\\' . $filter_type;
        }
        return $filter_class;
    }
    //end getFilterClass()
    /**
     * Rewind the iterator to the first file.
     *
     * @return void
     */
    #[Return_Type_Will_Change]
    public function rewind()
    {
        reset($this->files);
    }
    //end rewind()
    /**
     * Get the file that is currently being processed.
     *
     * @return \PHP_CodeSniffer\Files\File
     */
    #[Return_Type_Will_Change]
    public function current()
    {
        $path = key($this->files);
        if (isset($this->files[$path]) === false) {
            $this->files[$path] = new Local_File($path, $this->ruleset, $this->config);
        }
        return $this->files[$path];
    }
    //end current()
    /**
     * Return the file path of the current file being processed.
     *
     * @return void
     */
    #[Return_Type_Will_Change]
    public function key()
    {
        return key($this->files);
    }
    //end key()
    /**
     * Move forward to the next file.
     *
     * @return void
     */
    #[Return_Type_Will_Change]
    public function next()
    {
        next($this->files);
    }
    //end next()
    /**
     * Checks if current position is valid.
     *
     * @return boolean
     */
    #[Return_Type_Will_Change]
    public function valid()
    {
        if (current($this->files) === false) {
            return false;
        }
        return true;
    }
    //end valid()
    /**
     * Return the number of files in the list.
     *
     * @return integer
     */
    #[Return_Type_Will_Change]
    public function count()
    {
        return $this->num_files;
    }
    //end count()
}
//end class