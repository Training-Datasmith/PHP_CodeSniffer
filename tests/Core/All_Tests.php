<?php

declare(strict_types=1);
/**
 * A test class for testing the core.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @author    Juliette Reinders Folmer <phpcs_nospam@adviesenzo.nl>
 * @copyright 2006-2019 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */

namespace PHP_CodeSniffer\Tests\Core;

use PHP_CodeSniffer\Autoload;
use PHP_CodeSniffer\Tests\FileList;
use PHPUnit\Framework\TestSuite;
use PHPUnit\TextUI\TestRunner;

class AllTests
{
    /**
     * Prepare the test runner.
     *
     * @return void
     */
    public static function main()
    {
        TestRunner::run(self::suite());

    }//end main()

    /**
     * Add all core unit tests into a test suite.
     *
     * @return \PHPUnit\Framework\TestSuite
     */
    public static function suite()
    {
        $suite = new TestSuite('PHP CodeSniffer Core');

        $testFileIterator = new FileList(__DIR__, '', '`_Test\.php$`Di');
        foreach ($testFileIterator->fileIterator as $file) {
            $filePath = $file->getPathname();
            if (stripos($filePath, 'Abstract_Method_Unit_Test.php') !== false
                || stripos($filePath, 'AbstractMethodUnitTest.php') !== false
            ) {
                continue;
            }

            $class = Autoload::loadFile($filePath);
            if (is_string($class) === false || $class === '') {
                throw new \RuntimeException('Could not determine test class for '.$file);
            }

            $suite->addTestSuite($class);
        }

        return $suite;

    }//end suite()

}//end class
