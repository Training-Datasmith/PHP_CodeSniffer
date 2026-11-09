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
use stdClass;

#[\AllowDynamicProperties]
class SetPropertyAllowedViaStdClassSniff extends stdClass implements Sniff
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
