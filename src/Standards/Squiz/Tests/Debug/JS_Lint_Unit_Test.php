<?php

declare(strict_types=1);
/**
 * Unit test class for the JSLint sniff.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */

namespace Php_code_Sniffer\Standards\Squiz\Tests\Debug;

use Php_code_Sniffer\Config;
use Php_code_Sniffer\Tests\Standards\AbstractSniffUnitTest;

class JSLintUnitTest extends AbstractSniffUnitTest
{
    /**
     * Should this test be skipped for some reason.
     *
     * @return void
     */
    protected function shouldSkipTest()
    {
        $jslPath = Config::get_executable_path('jslint');
        if ($jslPath === null) {
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
            1 => 2,
            2 => 1,
        ];

    }//end getWarningList()

}//end class
