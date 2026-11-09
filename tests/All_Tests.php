<?php

declare(strict_types=1);
/**
 * A test class for running all PHP_CodeSniffer unit tests.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */

namespace PHP_CodeSniffer\Tests;

if ($GLOBALS['PHP_CODESNIFFER_PEAR'] === false) {
    include_once 'Core/All_Tests.php';
    include_once 'Standards/All_Sniffs.php';
} else {
    include_once 'CodeSniffer/Core/All_Tests.php';
    include_once 'CodeSniffer/Standards/All_Sniffs.php';
    include_once 'File_List.php';
}

// PHPUnit 7 made the TestSuite run() method incompatible with
// older PHPUnit versions due to return type hints, so maintain
// two different suite objects.
$phpunit7 = false;
if (class_exists('\PHPUnit\Runner\Version') === true) {
    $version = \PHPUnit\Runner\Version::id();
    if (version_compare($version, '7.0.0', '>=')) {
        $phpunit7 = true;
    }
}

if ($phpunit7 === true) {
    include_once 'Test_Suite7.php';
} else {
    include_once 'Test_Suite.php';
}

class PHP_CodeSniffer_AllTests
{
    /**
     * Add all PHP_CodeSniffer test suites into a single test suite.
     *
     * @return \PHPUnit\Framework\TestSuite
     */
    public static function suite()
    {
        $GLOBALS['PHP_CODESNIFFER_STANDARD_DIRS'] = [];
        $GLOBALS['PHP_CODESNIFFER_TEST_DIRS']     = [];

        // Use a special PHP_CodeSniffer test suite so that we can
        // unset our autoload function after the run.
        $suite = new TestSuite('PHP CodeSniffer');

        $suite->addTest(Core\AllTests::suite());
        $suite->addTest(Standards\AllSniffs::suite());

        return $suite;

    }//end suite()

}//end class

/**
 * PHPUnit 8+ derives the expected suite class name from the test file name.
 */
class All_Tests
{
    /**
     * @return \PHPUnit\Framework\TestSuite
     */
    public static function suite()
    {
        return PHP_CodeSniffer_AllTests::suite();
    }

}//end class
