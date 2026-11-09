<?php

declare(strict_types=1);
/**
 * Tests for the \Php_code_Sniffer\Ruleset class.
 *
 * @author    Juliette Reinders Folmer <phpcs_nospam@adviesenzo.nl>
 * @copyright 2019 Juliette Reinders Folmer. All rights reserved.
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */

namespace Php_code_Sniffer\Tests\Core\Ruleset;

use Php_code_Sniffer\Config;
use Php_code_Sniffer\Ruleset;
use PHPUnit\Framework\TestCase;

class RuleInclusionTest extends TestCase
{
    use SniffClassResolveTrait;
    /**
     * The Ruleset object.
     *
     * @var \Php_code_Sniffer\Ruleset
     */
    protected static $ruleset;

    /**
     * Path to the ruleset file.
     *
     * @var string
     */
    private static $standard = '';

    /**
     * The original content of the ruleset.
     *
     * @var string
     */
    private static $contents = '';

    /**
     * Initialize the test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        if ($GLOBALS['PHP_CODESNIFFER_PEAR'] === true) {
            // PEAR installs test and sniff files into different locations
            // so these tests will not pass as they directly reference files
            // by relative location.
            $this->markTestSkipped('Test cannot run from a PEAR install');
        }

    }//end setUp()

    /**
     * Initialize the config and ruleset objects based on the `RuleInclusionTest.xml` ruleset file.
     *
     * @return void
     */
    public static function setUpBeforeClass(): void
    {
        if ($GLOBALS['PHP_CODESNIFFER_PEAR'] === true) {
            // This test will be skipped.
            return;
        }

        $standard       = __DIR__.'/'.basename(__FILE__, '.php').'.xml';
        self::$standard = $standard;

        // On-the-fly adjust the ruleset test file to be able to test
        // sniffs included with relative paths.
        $contents       = file_get_contents($standard);
        self::$contents = $contents;

        $repoRootDir = basename(dirname(dirname(dirname(__DIR__))));

        $newPath = $repoRootDir;
        if (DIRECTORY_SEPARATOR === '\\') {
            $newPath = str_replace('\\', '/', $repoRootDir);
        }

        $adjusted = str_replace('%path_root_dir%', $newPath, $contents);

        if (file_put_contents($standard, $adjusted) === false) {
            self::markTestSkipped('On the fly ruleset adjustment failed');
        }

        $config        = new Config(["--standard=$standard"]);
        self::$ruleset = new Ruleset($config);

    }//end setUpBeforeClass()

    /**
     * Reset ruleset file.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        file_put_contents(self::$standard, self::$contents);

    }//end tearDown()

    /**
     * Test that sniffs are registered.
     *
     * @return void
     */
    public function testHasSniffCodes()
    {
        $this->assertTrue(property_exists(self::$ruleset, 'sniff_codes'));
        $this->assertGreaterThanOrEqual(47, count(self::$ruleset->sniff_codes));

    }//end testHasSniffCodes()

    /**
     * Test that sniffs are correctly registered, independently of the syntax used to include the sniff.
     *
     * @param string $key   Expected array key.
     * @param string $value Expected array value.
     *
     * @dataProvider dataRegisteredSniffCodes
     *
     * @return void
     */
    public function testRegisteredSniffCodes($key, $value = null)
    {
        $this->assertArrayHasKey($key, self::$ruleset->sniff_codes);
        $this->assertNotSame('', self::$ruleset->sniff_codes[$key]);

    }//end testRegisteredSniffCodes()

    /**
     * Data provider.
     *
     * @see self::testRegisteredSniffCodes()
     *
     * @return array
     */
    public function dataRegisteredSniffCodes()
    {
        return [
            [
                'PSR2.Classes.ClassDeclaration',
                'Php_code_Sniffer\Standards\PSR2\Sniffs\Classes\Class__Declaration__Sniff',
            ],
            [
                'PSR2.Classes.PropertyDeclaration',
                'Php_code_Sniffer\Standards\PSR2\Sniffs\Classes\Property__Declaration__Sniff',
            ],
            [
                'PSR2.ControlStructures.ControlStructureSpacing',
                'Php_code_Sniffer\Standards\PSR2\Sniffs\Control_Structures\Control__Structure__Spacing__Sniff',
            ],
            [
                'PSR2.ControlStructures.ElseIfDeclaration',
                'Php_code_Sniffer\Standards\PSR2\Sniffs\Control_Structures\Else__If__Declaration__Sniff',
            ],
            [
                'PSR2.ControlStructures.SwitchDeclaration',
                'Php_code_Sniffer\Standards\PSR2\Sniffs\Control_Structures\Switch__Declaration__Sniff',
            ],
            [
                'PSR2.Files.ClosingTag',
                'Php_code_Sniffer\Standards\PSR2\Sniffs\Files\Closing__Tag__Sniff',
            ],
            [
                'PSR2.Files.EndFileNewline',
                'Php_code_Sniffer\Standards\PSR2\Sniffs\Files\End__File__Newline__Sniff',
            ],
            [
                'PSR2.Methods.FunctionCallSignature',
                'Php_code_Sniffer\Standards\PSR2\Sniffs\Methods\Function__Call__Signature__Sniff',
            ],
            [
                'PSR2.Methods.FunctionClosingBrace',
                'Php_code_Sniffer\Standards\PSR2\Sniffs\Methods\Function__Closing__Brace__Sniff',
            ],
            [
                'PSR2.Methods.MethodDeclaration',
                'Php_code_Sniffer\Standards\PSR2\Sniffs\Methods\Method__Declaration__Sniff',
            ],
            [
                'PSR2.Namespaces.NamespaceDeclaration',
                'Php_code_Sniffer\Standards\PSR2\Sniffs\Namespaces\Namespace__Declaration__Sniff',
            ],
            [
                'PSR2.Namespaces.UseDeclaration',
                'Php_code_Sniffer\Standards\PSR2\Sniffs\Namespaces\Use__Declaration__Sniff',
            ],
            [
                'PSR1.Classes.ClassDeclaration',
                'Php_code_Sniffer\Standards\PSR1\Sniffs\Classes\Class__Declaration__Sniff',
            ],
            [
                'PSR1.Files.SideEffects',
                'Php_code_Sniffer\Standards\PSR1\Sniffs\Files\Side__Effects__Sniff',
            ],
            [
                'PSR1.Methods.CamelCapsMethodName',
                'Php_code_Sniffer\Standards\PSR1\Sniffs\Methods\Camel__Caps__Method__Name__Sniff',
            ],
            [
                'Generic.PHP.DisallowShortOpenTag',
                'Php_code_Sniffer\Standards\Generic\Sniffs\PHP\Disallow__Short__Open__Tag__Sniff',
            ],
            [
                'Generic.Files.ByteOrderMark',
                'Php_code_Sniffer\Standards\Generic\Sniffs\Files\Byte__Order__Mark__Sniff',
            ],
            [
                'Squiz.Classes.ValidClassName',
                'Php_code_Sniffer\Standards\Squiz\Sniffs\Classes\Valid__Class__Name__Sniff',
            ],
            [
                'Generic.NamingConventions.UpperCaseConstantName',
                'Php_code_Sniffer\Standards\Generic\Sniffs\Naming_Conventions\Upper__Case__Constant__Name__Sniff',
            ],
            [
                'Generic.Files.LineEndings',
                'Php_code_Sniffer\Standards\Generic\Sniffs\Files\Line__Endings__Sniff',
            ],
            [
                'Generic.Files.LineLength',
                'Php_code_Sniffer\Standards\Generic\Sniffs\Files\Line__Length__Sniff',
            ],
            [
                'Squiz.WhiteSpace.SuperfluousWhitespace',
                'Php_code_Sniffer\Standards\Squiz\Sniffs\White_Space\Superfluous__Whitespace__Sniff',
            ],
            [
                'Generic.Formatting.DisallowMultipleStatements',
                'Php_code_Sniffer\Standards\Generic\Sniffs\Formatting\Disallow__Multiple__Statements__Sniff',
            ],
            [
                'Generic.WhiteSpace.ScopeIndent',
                'Php_code_Sniffer\Standards\Generic\Sniffs\White_Space\Scope__Indent__Sniff',
            ],
            [
                'Generic.WhiteSpace.DisallowTabIndent',
                'Php_code_Sniffer\Standards\Generic\Sniffs\White_Space\Disallow__Tab__Indent__Sniff',
            ],
            [
                'Generic.PHP.LowerCaseKeyword',
                'Php_code_Sniffer\Standards\Generic\Sniffs\PHP\Lower__Case__Keyword__Sniff',
            ],
            [
                'Generic.PHP.LowerCaseConstant',
                'Php_code_Sniffer\Standards\Generic\Sniffs\PHP\Lower__Case__Constant__Sniff',
            ],
            [
                'Squiz.Scope.MethodScope',
                'Php_code_Sniffer\Standards\Squiz\Sniffs\Scope\Method__Scope__Sniff',
            ],
            [
                'Squiz.WhiteSpace.ScopeKeywordSpacing',
                'Php_code_Sniffer\Standards\Squiz\Sniffs\White_Space\Scope__Keyword__Spacing__Sniff',
            ],
            [
                'Squiz.Functions.FunctionDeclaration',
                'Php_code_Sniffer\Standards\Squiz\Sniffs\Functions\Function__Declaration__Sniff',
            ],
            [
                'Squiz.Functions.LowercaseFunctionKeywords',
                'Php_code_Sniffer\Standards\Squiz\Sniffs\Functions\Lowercase__Function__Keywords__Sniff',
            ],
            [
                'Squiz.Functions.FunctionDeclarationArgumentSpacing',
                'Php_code_Sniffer\Standards\Squiz\Sniffs\Functions\Function__Declaration__Argument__Spacing__Sniff',
            ],
            [
                'PEAR.Functions.ValidDefaultValue',
                'Php_code_Sniffer\Standards\PEAR\Sniffs\Functions\Valid__Default__Value__Sniff',
            ],
            [
                'Squiz.Functions.MultiLineFunctionDeclaration',
                'Php_code_Sniffer\Standards\Squiz\Sniffs\Functions\Multi__Line__Function__Declaration__Sniff',
            ],
            [
                'Generic.Functions.FunctionCallArgumentSpacing',
                'Php_code_Sniffer\Standards\Generic\Sniffs\Functions\Function__Call__Argument__Spacing__Sniff',
            ],
            [
                'Squiz.ControlStructures.ControlSignature',
                'Php_code_Sniffer\Standards\Squiz\Sniffs\Control_Structures\Control__Signature__Sniff',
            ],
            [
                'Squiz.WhiteSpace.ControlStructureSpacing',
                'Php_code_Sniffer\Standards\Squiz\Sniffs\White_Space\Control__Structure__Spacing__Sniff',
            ],
            [
                'Squiz.WhiteSpace.ScopeClosingBrace',
                'Php_code_Sniffer\Standards\Squiz\Sniffs\White_Space\Scope__Closing__Brace__Sniff',
            ],
            [
                'Squiz.ControlStructures.ForEachLoopDeclaration',
                'Php_code_Sniffer\Standards\Squiz\Sniffs\Control_Structures\For__Each__Loop__Declaration__Sniff',
            ],
            [
                'Squiz.ControlStructures.ForLoopDeclaration',
                'Php_code_Sniffer\Standards\Squiz\Sniffs\Control_Structures\For__Loop__Declaration__Sniff',
            ],
            [
                'Squiz.ControlStructures.LowercaseDeclaration',
                'Php_code_Sniffer\Standards\Squiz\Sniffs\Control_Structures\Lowercase__Declaration__Sniff',
            ],
            [
                'Generic.ControlStructures.InlineControlStructure',
                'Php_code_Sniffer\Standards\Generic\Sniffs\Control_Structures\Inline__Control__Structure__Sniff',
            ],
            [
                'PSR12.Operators.OperatorSpacing',
                'Php_code_Sniffer\Standards\PSR12\Sniffs\Operators\Operator__Spacing__Sniff',
            ],
            [
                'Generic.Arrays.ArrayIndent',
                'Php_code_Sniffer\Standards\Generic\Sniffs\Arrays\Array__Indent__Sniff',
            ],
            [
                'Generic.Metrics.CyclomaticComplexity',
                'Php_code_Sniffer\Standards\Generic\Sniffs\Metrics\Cyclomatic__Complexity__Sniff',
            ],
            [
                'Generic.NamingConventions.CamelCapsFunctionName',
                'Php_code_Sniffer\Standards\Generic\Sniffs\Naming_Conventions\Camel__Caps__Function__Name__Sniff',
            ],
            [
                'Generic.Metrics.NestingLevel',
                'Php_code_Sniffer\Standards\Generic\Sniffs\Metrics\Nesting__Level__Sniff',
            ],
        ];

    }//end dataRegisteredSniffCodes()

    /**
     * Test that setting properties for standards, categories, sniffs works for all supported rule
     * inclusion methods.
     *
     * @param string $sniffClass    The name of the sniff class.
     * @param string $propertyName  The name of the changed property.
     * @param mixed  $expectedValue The value expected for the property.
     *
     * @dataProvider dataSettingProperties
     *
     * @return void
     */
    public function testSettingProperties($sniffClass, $propertyName, $expectedValue)
    {
        $resolvedClass = $this->resolveRegisteredSniffClass(self::$ruleset, $sniffClass);
        $sniffObject   = self::$ruleset->sniffs[$resolvedClass];
        $property      = $this->resolveSniffPropertyName($sniffObject, $propertyName);

        $actualValue = $sniffObject->$property;
        if (is_numeric($expectedValue) === true && is_numeric($actualValue) === true) {
            $this->assertSame((string) $expectedValue, (string) $actualValue);
        } else {
            $this->assertSame($expectedValue, $actualValue);
        }

    }//end testSettingProperties()

    /**
     * Data provider.
     *
     * @see self::testSettingProperties()
     *
     * @return array
     */
    public function dataSettingProperties()
    {
        return [
            'Set property for complete standard: PSR2 ClassDeclaration'                                  => [
                'Php_code_Sniffer\Standards\PSR2\Sniffs\Classes\Class__Declaration__Sniff',
                'indent',
                '20',
            ],
            'Set property for complete standard: PSR2 SwitchDeclaration'                                 => [
                'Php_code_Sniffer\Standards\PSR2\Sniffs\Control_Structures\Switch__Declaration__Sniff',
                'indent',
                '20',
            ],
            'Set property for complete standard: PSR2 FunctionCallSignature'                             => [
                'Php_code_Sniffer\Standards\PSR2\Sniffs\Methods\Function__Call__Signature__Sniff',
                'indent',
                '20',
            ],
            'Set property for complete category: PSR12 OperatorSpacing'                                  => [
                'Php_code_Sniffer\Standards\PSR12\Sniffs\Operators\Operator__Spacing__Sniff',
                'ignoreSpacingBeforeAssignments',
                false,
            ],
            'Set property for individual sniff: Generic ArrayIndent'                                     => [
                'Php_code_Sniffer\Standards\Generic\Sniffs\Arrays\Array__Indent__Sniff',
                'indent',
                '2',
            ],
            'Set property for individual sniff using sniff file inclusion: Generic LineLength'           => [
                'Php_code_Sniffer\Standards\Generic\Sniffs\Files\Line__Length__Sniff',
                'lineLimit',
                '10',
            ],
            'Set property for individual sniff using sniff file inclusion: CamelCapsFunctionName'        => [
                'Php_code_Sniffer\Standards\Generic\Sniffs\Naming_Conventions\Camel__Caps__Function__Name__Sniff',
                'strict',
                false,
            ],
            'Set property for individual sniff via included ruleset: NestingLevel - nestingLevel'        => [
                'Php_code_Sniffer\Standards\Generic\Sniffs\Metrics\Nesting__Level__Sniff',
                'nestingLevel',
                '2',
            ],
            'Set property for all sniffs in an included ruleset: NestingLevel - absoluteNestingLevel'    => [
                'Php_code_Sniffer\Standards\Generic\Sniffs\Metrics\Nesting__Level__Sniff',
                'absoluteNestingLevel',
                true,
            ],

            // Testing that setting a property at error code level does *not* work.
            'Set property for error code will not change the sniff property value: CyclomaticComplexity' => [
                'Php_code_Sniffer\Standards\Generic\Sniffs\Metrics\Cyclomatic__Complexity__Sniff',
                'complexity',
                10,
            ],
        ];

    }//end dataSettingProperties()

    /**
     * Test that setting properties for standards, categories on sniffs which don't support the property will
     * silently ignore the property and not set it.
     *
     * @param string $sniffClass   The name of the sniff class.
     * @param string $propertyName The name of the property which should not be set.
     *
     * @dataProvider dataSettingInvalidPropertiesOnStandardsAndCategoriesSilentlyFails
     *
     * @return void
     */
    public function testSettingInvalidPropertiesOnStandardsAndCategoriesSilentlyFails($sniffClass, $propertyName)
    {
        $resolvedClass = $this->resolveRegisteredSniffClass(self::$ruleset, $sniffClass);
        $sniffObject   = self::$ruleset->sniffs[$resolvedClass];
        $this->assertFalse(property_exists($sniffObject, $propertyName));

    }//end testSettingInvalidPropertiesOnStandardsAndCategoriesSilentlyFails()

    /**
     * Data provider.
     *
     * @see self::testSettingInvalidPropertiesOnStandardsAndCategoriesSilentlyFails()
     *
     * @return array
     */
    public function dataSettingInvalidPropertiesOnStandardsAndCategoriesSilentlyFails()
    {
        return [
            'Set property for complete standard: PSR2 ClassDeclaration'      => [
                'Php_code_Sniffer\Standards\PSR1\Sniffs\Classes\Class__Declaration__Sniff',
                'setforallsniffs',
            ],
            'Set property for complete standard: PSR2 FunctionCallSignature' => [
                'Php_code_Sniffer\Standards\PSR2\Sniffs\Methods\Function__Call__Signature__Sniff',
                'setforallsniffs',
            ],
            'Set property for complete category: PSR12 OperatorSpacing'      => [
                'Php_code_Sniffer\Standards\PSR12\Sniffs\Operators\Operator__Spacing__Sniff',
                'setforallincategory',
            ],
        ];

    }//end dataSettingInvalidPropertiesOnStandardsAndCategoriesSilentlyFails()

    /**
     * Resolve a ruleset XML property name to a declared sniff property.
     *
     * @param object $sniffObject  Sniff instance.
     * @param string $propertyName Property name from ruleset XML/tests.
     *
     * @return string
     */
    private function resolveSniffPropertyName($sniffObject, $propertyName)
    {
        $candidates = [
            $propertyName,
            strtolower(preg_replace('/(?<=[a-z])([A-Z])/', '_$1', $propertyName)),
        ];
        foreach ($candidates as $name) {
            if (property_exists($sniffObject, $name) === true) {
                return $name;
            }
        }

        $this->fail('Property not found on sniff: '.$propertyName);

    }//end resolveSniffPropertyName()

}//end class
