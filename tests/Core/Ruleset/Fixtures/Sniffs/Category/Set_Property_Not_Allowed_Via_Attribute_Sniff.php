<?php

declare(strict_types=1);
/**
 * Test fixture.
 *
 * @see \Php_code_Sniffer\Tests\Core\Ruleset\SetSniffPropertyTest
 */

namespace Fixtures\Sniffs\Category;

use AllowDynamicProperties;
use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;

#[AllowDynamicProperties]
class SetPropertyNotAllowedViaAttributeSniff implements Sniff
{
    public function register()
    {
        return [T_WHITESPACE];
    }

    public function process(File $phpcsFile, $stackPtr)
    {
        // Do something.
    }
}
