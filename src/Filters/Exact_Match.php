<?php

declare (strict_types=1);
/**
 * An abstract filter class for checking files and folders against exact matches.
 *
 * Supports both whitelists and blacklists.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Filters;

use Php_code_Sniffer\Util;
abstract class Exact_Match extends Filter
{
    /**
     * A list of files to exclude.
     *
     * @var array
     */
    private $blacklist;
    /**
     * A list of files to include.
     *
     * If the whitelist is empty, only files in the blacklist will be excluded.
     *
     * @var array
     */
    private $whitelist;
    /**
     * Check whether the current element of the iterator is acceptable.
     *
     * If a file is both blacklisted and whitelisted, it will be deemed unacceptable.
     *
     * @return bool
     */
    public function accept()
    {
        if (parent::accept() === false) {
            return false;
        }
        if ($this->blacklist === null) {
            $this->blacklist = $this->getblacklist();
        }
        if ($this->whitelist === null) {
            $this->whitelist = $this->getwhitelist();
        }
        $file_path = Util\Common::realpath($this->current());
        // If file is both blacklisted and whitelisted, the blacklist takes precedence.
        if (isset($this->blacklist[$file_path]) === true) {
            return false;
        }
        if (empty($this->whitelist) === true && empty($this->blacklist) === false) {
            // We are only checking a blacklist, so everything else should be whitelisted.
            return true;
        }
        return isset($this->whitelist[$file_path]);
    }
    //end accept()
    /**
     * Returns an iterator for the current entry.
     *
     * Ensures that the blacklist and whitelist are preserved so they don't have
     * to be generated each time.
     *
     * @return \RecursiveIterator
     */
    public function get_children()
    {
        $children = parent::get_children();
        $children->blacklist = $this->blacklist;
        $children->whitelist = $this->whitelist;
        return $children;
    }
    //end getChildren()
    /**
     * Get a list of blacklisted file paths.
     *
     * @return array
     */
    abstract protected function get_blacklist();
    /**
     * Get a list of whitelisted file paths.
     *
     * @return array
     */
    abstract protected function get_whitelist();
}
//end class