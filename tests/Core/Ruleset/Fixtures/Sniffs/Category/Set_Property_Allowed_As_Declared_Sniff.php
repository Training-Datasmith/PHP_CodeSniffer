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

class SetPropertyAllowedAsDeclaredSniff implements Sniff
{
    public $arbitrarystring;
    public $arbitraryarray;

    public function register()
    {
        return [T_WHITESPACE];
    }

    public function process(File $phpcsFile, $stackPtr)
    {
        // Do something.
    }
}
