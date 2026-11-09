<?php

declare(strict_types=1);
/**
 * Unit test class for the ClosureLinter sniff.
 *
 * @author    Juliette Reinders Folmer <phpcs_nospam@adviesenzo.nl>
 * @copyright 2019 Juliette Reinders Folmer. All rights reserved.
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */

namespace Php_code_Sniffer\Standards\Generic\Tests\Debug;

use Php_code_Sniffer\Config;
use Php_code_Sniffer\Tests\Standards\AbstractSniffUnitTest;

class ClosureLinterUnitTest extends AbstractSniffUnitTest
{
    /**
     * Should this test be skipped for some reason.
     *
     * @return void
     */
    protected function shouldSkipTest()
    {
        $lintPath = Config::get_executable_path('gjslint');
        if ($lintPath === null) {
            return true;
        }

        return false;

    }//end shouldSkipTest()

    /**
     * Returns the lines where errors should occur.
     *
     * The key of the array should represent the line number and the value
     * should represent the number of errors that should occur on that line.
     *
     * @return array<int, int>
     */
    public function getErrorList()
    {
        return [];

    }//end getErrorList()

    /**
     * Returns the lines where warnings should occur.
     *
     * The key of the array should represent the line number and the value
     * should represent the number of warnings that should occur on that line.
     *
     * @return array<int, int>
     */
    public function getWarningList()
    {
        return [
            3 => 1,
            5 => 1,
        ];

    }//end getWarningList()

}//end class
