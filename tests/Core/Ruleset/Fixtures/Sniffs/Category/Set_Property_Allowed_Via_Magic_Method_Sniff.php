<?php

declare(strict_types=1);
/**
 * Test fixture.
 *
 * @see \Php_code_Sniffer\Tests\Core\Ruleset\SetSniffPropertyTest
 */

namespace Fixtures\Sniffs\Category;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;

class SetPropertyAllowedViaMagicMethodSniff implements Sniff
{
    private $magic = [];

    public function __set($name, $value)
    {
        $this->magic[$name] = $value;
    }

    public function __get($name)
    {
        if (isset($this->magic[$name])) {
            return $this->magic[$name];
        }

        return null;
    }

    public function register()
    {
        return [T_WHITESPACE];
    }

    public function process(File $phpcsFile, $stackPtr)
    {
        // Do something.
    }
}
