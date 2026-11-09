<?php

declare(strict_types=1);
/**
 * An abstract class that all sniff unit tests must extend.
 *
 * A sniff unit test checks a .inc file for expected violations of a single
 * coding standard. Expected errors and warnings that are not found, or
 * warnings and errors that are not expected, are considered test failures.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */

namespace Php_code_Sniffer\Tests\Standards;

use Php_code_Sniffer\Autoload;
use Php_code_Sniffer\Config;
use Php_code_Sniffer\Exceptions\RuntimeException;
use Php_code_Sniffer\Files\Local_File;
use Php_code_Sniffer\Ruleset;
use Php_code_Sniffer\Util\Common;
use PHPUnit\Framework\TestCase;

abstract class AbstractSniffUnitTest extends TestCase
{
    /**
     * Enable or disable the backup and restoration of the $GLOBALS array.
     * Overwrite this attribute in a child class of TestCase.
     * Setting this attribute in setUp() has no effect!
     *
     * @var boolean
     */
    protected $backupGlobals = false;

    /**
     * The path to the standard's main directory.
     *
     * @var string
     */
    public $standardsDir = null;

    /**
     * The path to the standard's test directory.
     *
     * @var string
     */
    public $testsDir = null;

    /**
     * Sets up this unit test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $class = get_class($this);
        $this->standardsDir = $GLOBALS['PHP_CODESNIFFER_STANDARD_DIRS'][$class];
        $this->testsDir     = $GLOBALS['PHP_CODESNIFFER_TEST_DIRS'][$class];

    }//end setUp()

    /**
     * Get a list of all test files to check.
     *
     * These will have the same base as the sniff name but different extensions.
     * We ignore the .php file as it is the class.
     *
     * @param string $testFileBase The base path that the unit tests files will have.
     *
     * @return string[]
     */
    protected function getTestFiles($testFileBase)
    {
        $testFiles = [];

        $dir = substr($testFileBase, 0, strrpos($testFileBase, DIRECTORY_SEPARATOR));
        $di  = new \DirectoryIterator($dir);

        foreach ($di as $file) {
            $path = $file->getPathname();
            if (substr($path, 0, strlen($testFileBase)) === $testFileBase) {
                if ($path !== $testFileBase.'php' && substr($path, -5) !== 'fixed' && substr($path, -4) !== '.bak') {
                    $testFiles[] = $path;
                }
            }
        }

        // Put them in order.
        sort($testFiles);

        return $testFiles;

    }//end getTestFiles()

    /**
     * Should this test be skipped for some reason.
     *
     * @return boolean
     */
    protected function shouldSkipTest()
    {
        return false;

    }//end shouldSkipTest()

    /**
     * Tests the extending classes Sniff class.
     *
     * @return void
     * @throws \PHPUnit\Framework\Exception
     */
    final public function testSniff()
    {
        // Skip this test if we can't run in this environment.
        if ($this->shouldSkipTest() === true) {
            $this->markTestSkipped();
        }

        $sniffCode = Common::get_sniff_code(get_class($this));
        list($standardName, $categoryName, $sniffName) = explode('.', $sniffCode);

        $testFileBase = $this->testsDir.$categoryName.DIRECTORY_SEPARATOR.$sniffName.'UnitTest.';

        // Get a list of all test files to check.
        $testFiles = $this->getTestFiles($testFileBase);
        $GLOBALS['PHP_CODESNIFFER_SNIFF_CASE_FILES'][] = $testFiles;

        if (isset($GLOBALS['PHP_CODESNIFFER_CONFIG']) === true) {
            $config = $GLOBALS['PHP_CODESNIFFER_CONFIG'];
        } else {
            $config        = new Config([]);
            $config->cache = false;
            $GLOBALS['PHP_CODESNIFFER_CONFIG'] = $config;
        }

        $config->standards = [$standardName];
        $config->sniffs    = [$sniffCode];
        $config->ignored   = [];

        if (isset($GLOBALS['PHP_CODESNIFFER_RULESETS']) === false) {
            $GLOBALS['PHP_CODESNIFFER_RULESETS'] = [];
        }

        if (isset($GLOBALS['PHP_CODESNIFFER_RULESETS'][$standardName]) === false) {
            $ruleset = new Ruleset($config);
            $GLOBALS['PHP_CODESNIFFER_RULESETS'][$standardName] = $ruleset;
        }

        $ruleset = $GLOBALS['PHP_CODESNIFFER_RULESETS'][$standardName];

        $reflection = new \ReflectionClass($this);
        $testPath   = $reflection->getFileName();
        if ($testPath === false) {
            $this->fail('Cannot determine test class file path.');
        }

        $sniffFile = str_replace(
            [
                DIRECTORY_SEPARATOR.'Tests'.DIRECTORY_SEPARATOR,
                '_Unit_Test.php',
            ],
            [
                DIRECTORY_SEPARATOR.'Sniffs'.DIRECTORY_SEPARATOR,
                '_Sniff.php',
            ],
            $testPath
        );

        if (is_file($sniffFile) === false) {
            $this->fail('Cannot find sniff file for test: '.$sniffFile);
        }

        $sniffFqcn = Autoload::loadFile($sniffFile);
        if (is_string($sniffFqcn) === false || $sniffFqcn === '') {
            $this->fail('Cannot determine sniff class for file: '.$sniffFile);
        }

        $sniffClassName = Common::clean_sniff_class($sniffFqcn);

        $restrictions = [strtolower($sniffClassName) => true];
        $ruleset->register_sniffs([$sniffFile], $restrictions, []);
        $ruleset->populate_token_listeners();

        $failureMessages = [];
        foreach ($testFiles as $testFile) {
            $filename  = basename($testFile);
            $oldConfig = $config->get_settings();

            try {
                $this->setCliValues($filename, $config);
                $phpcsFile = new Local_File($testFile, $ruleset, $config);
                $phpcsFile->process();
            } catch (RuntimeException $e) {
                $this->fail('An unexpected exception has been caught: '.$e->getMessage());
            }

            $failures        = $this->generateFailureMessages($phpcsFile);
            $failureMessages = array_merge($failureMessages, $failures);

            if ($phpcsFile->get_fixable_count() > 0) {
                // Attempt to fix the errors.
                $phpcsFile->fixer->fix_file();
                $fixable = $phpcsFile->get_fixable_count();
                if ($fixable > 0) {
                    $failureMessages[] = "Failed to fix $fixable fixable violations in $filename";
                }

                // Check for a .fixed file to check for accuracy of fixes.
                $fixedFile = $testFile.'.fixed';
                if (file_exists($fixedFile) === true) {
                    $diff = $phpcsFile->fixer->generate_diff($fixedFile);
                    if (trim($diff) !== '') {
                        $filename          = basename($testFile);
                        $fixedFilename     = basename($fixedFile);
                        $failureMessages[] = "Fixed version of $filename does not match expected version in $fixedFilename; the diff is\n$diff";
                    }
                }
            }

            // Restore the config.
            $config->set_settings($oldConfig);
        }//end foreach

        if (empty($failureMessages) === false) {
            $this->fail(implode(PHP_EOL, $failureMessages));
        }

    }//end testSniff()

    /**
     * Generate a list of test failures for a given sniffed file.
     *
     * @param \Php_code_Sniffer\Files\Local_File $file The file being tested.
     *
     * @return array
     * @throws \Php_code_Sniffer\Exceptions\RuntimeException
     */
    public function generateFailureMessages(Local_File $file)
    {
        $testFile = $file->get_filename();

        $foundErrors      = $file->get_errors();
        $foundWarnings    = $file->get_warnings();
        $expectedErrors   = $this->getErrorList(basename($testFile));
        $expectedWarnings = $this->getWarningList(basename($testFile));

        if (is_array($expectedErrors) === false) {
            throw new RuntimeException('getErrorList() must return an array');
        }

        if (is_array($expectedWarnings) === false) {
            throw new RuntimeException('getWarningList() must return an array');
        }

        /*
            We merge errors and warnings together to make it easier
            to iterate over them and produce the errors string. In this way,
            we can report on errors and warnings in the same line even though
            it's not really structured to allow that.
        */

        $allProblems     = [];
        $failureMessages = [];

        foreach ($foundErrors as $line => $lineErrors) {
            foreach ($lineErrors as $column => $errors) {
                if (isset($allProblems[$line]) === false) {
                    $allProblems[$line] = [
                        'expected_errors'   => 0,
                        'expected_warnings' => 0,
                        'found_errors'      => [],
                        'found_warnings'    => [],
                    ];
                }

                $foundErrorsTemp = [];
                foreach ($allProblems[$line]['found_errors'] as $foundError) {
                    $foundErrorsTemp[] = $foundError;
                }

                $errorsTemp = [];
                foreach ($errors as $foundError) {
                    $errorsTemp[] = $foundError['message'].' ('.$foundError['source'].')';

                    $source = $foundError['source'];
                    if (in_array($source, $GLOBALS['PHP_CODESNIFFER_SNIFF_CODES'], true) === false) {
                        $GLOBALS['PHP_CODESNIFFER_SNIFF_CODES'][] = $source;
                    }

                    if ($foundError['fixable'] === true
                        && in_array($source, $GLOBALS['PHP_CODESNIFFER_FIXABLE_CODES'], true) === false
                    ) {
                        $GLOBALS['PHP_CODESNIFFER_FIXABLE_CODES'][] = $source;
                    }
                }

                $allProblems[$line]['found_errors'] = array_merge($foundErrorsTemp, $errorsTemp);
            }//end foreach

            if (isset($expectedErrors[$line]) === true) {
                $allProblems[$line]['expected_errors'] = $expectedErrors[$line];
            } else {
                $allProblems[$line]['expected_errors'] = 0;
            }

            unset($expectedErrors[$line]);
        }//end foreach

        foreach ($expectedErrors as $line => $numErrors) {
            if (isset($allProblems[$line]) === false) {
                $allProblems[$line] = [
                    'expected_errors'   => 0,
                    'expected_warnings' => 0,
                    'found_errors'      => [],
                    'found_warnings'    => [],
                ];
            }

            $allProblems[$line]['expected_errors'] = $numErrors;
        }

        foreach ($foundWarnings as $line => $lineWarnings) {
            foreach ($lineWarnings as $column => $warnings) {
                if (isset($allProblems[$line]) === false) {
                    $allProblems[$line] = [
                        'expected_errors'   => 0,
                        'expected_warnings' => 0,
                        'found_errors'      => [],
                        'found_warnings'    => [],
                    ];
                }

                $foundWarningsTemp = [];
                foreach ($allProblems[$line]['found_warnings'] as $foundWarning) {
                    $foundWarningsTemp[] = $foundWarning;
                }

                $warningsTemp = [];
                foreach ($warnings as $warning) {
                    $warningsTemp[] = $warning['message'].' ('.$warning['source'].')';

                    $source = $warning['source'];
                    if (in_array($source, $GLOBALS['PHP_CODESNIFFER_SNIFF_CODES'], true) === false) {
                        $GLOBALS['PHP_CODESNIFFER_SNIFF_CODES'][] = $source;
                    }

                    if ($warning['fixable'] === true
                        && in_array($source, $GLOBALS['PHP_CODESNIFFER_FIXABLE_CODES'], true) === false
                    ) {
                        $GLOBALS['PHP_CODESNIFFER_FIXABLE_CODES'][] = $source;
                    }
                }

                $allProblems[$line]['found_warnings'] = array_merge($foundWarningsTemp, $warningsTemp);
            }//end foreach

            if (isset($expectedWarnings[$line]) === true) {
                $allProblems[$line]['expected_warnings'] = $expectedWarnings[$line];
            } else {
                $allProblems[$line]['expected_warnings'] = 0;
            }

            unset($expectedWarnings[$line]);
        }//end foreach

        foreach ($expectedWarnings as $line => $numWarnings) {
            if (isset($allProblems[$line]) === false) {
                $allProblems[$line] = [
                    'expected_errors'   => 0,
                    'expected_warnings' => 0,
                    'found_errors'      => [],
                    'found_warnings'    => [],
                ];
            }

            $allProblems[$line]['expected_warnings'] = $numWarnings;
        }

        // Order the messages by line number.
        ksort($allProblems);

        foreach ($allProblems as $line => $problems) {
            $numErrors        = count($problems['found_errors']);
            $numWarnings      = count($problems['found_warnings']);
            $expectedErrors   = $problems['expected_errors'];
            $expectedWarnings = $problems['expected_warnings'];

            $errors      = '';
            $foundString = '';

            if ($expectedErrors !== $numErrors || $expectedWarnings !== $numWarnings) {
                $lineMessage     = "[LINE $line]";
                $expectedMessage = 'Expected ';
                $foundMessage    = 'in '.basename($testFile).' but found ';

                if ($expectedErrors !== $numErrors) {
                    $expectedMessage .= "$expectedErrors error(s)";
                    $foundMessage    .= "$numErrors error(s)";
                    if ($numErrors !== 0) {
                        $foundString .= 'error(s)';
                        $errors      .= implode(PHP_EOL.' -> ', $problems['found_errors']);
                    }

                    if ($expectedWarnings !== $numWarnings) {
                        $expectedMessage .= ' and ';
                        $foundMessage    .= ' and ';
                        if ($numWarnings !== 0) {
                            if ($foundString !== '') {
                                $foundString .= ' and ';
                            }
                        }
                    }
                }

                if ($expectedWarnings !== $numWarnings) {
                    $expectedMessage .= "$expectedWarnings warning(s)";
                    $foundMessage    .= "$numWarnings warning(s)";
                    if ($numWarnings !== 0) {
                        $foundString .= 'warning(s)';
                        if (empty($errors) === false) {
                            $errors .= PHP_EOL.' -> ';
                        }

                        $errors .= implode(PHP_EOL.' -> ', $problems['found_warnings']);
                    }
                }

                $fullMessage = "$lineMessage $expectedMessage $foundMessage.";
                if ($errors !== '') {
                    $fullMessage .= " The $foundString found were:".PHP_EOL." -> $errors";
                }

                $failureMessages[] = $fullMessage;
            }//end if
        }//end foreach

        return $failureMessages;

    }//end generateFailureMessages()

    /**
     * Get a list of CLI values to set before the file is tested.
     *
     * @param string                  $filename The name of the file being tested.
     * @param \Php_code_Sniffer\Config $config   The config data for the run.
     *
     * @return void
     */
    public function setCliValues($filename, $config)
    {
        return;

    }//end setCliValues()

    /**
     * Returns the lines where errors should occur.
     *
     * The key of the array should represent the line number and the value
     * should represent the number of errors that should occur on that line.
     *
     * @return array<int, int>
     */
    abstract protected function getErrorList();

    /**
     * Returns the lines where warnings should occur.
     *
     * The key of the array should represent the line number and the value
     * should represent the number of warnings that should occur on that line.
     *
     * @return array<int, int>
     */
    abstract protected function getWarningList();

}//end class
