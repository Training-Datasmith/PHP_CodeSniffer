<?php

declare (strict_types=1);
/**
 * The base class for all PHP_CodeSniffer documentation generators.
 *
 * Documentation generators are used to print documentation about code sniffs
 * in a standard.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Generators;

use Php_code_Sniffer\Autoload;
use Php_code_Sniffer\Ruleset;
abstract class Generator
{
    /**
     * The ruleset used for the run.
     *
     * @var \PHP_CodeSniffer\Ruleset
     */
    public $ruleset;
    /**
     * XML documentation files used to produce the final output.
     *
     * @var string[]
     */
    public $doc_files = [];
    /**
     * Constructs a doc generator.
     *
     * @param \PHP_CodeSniffer\Ruleset $ruleset The ruleset used for the run.
     *
     * @see generate()
     */
    public function __construct(Ruleset $ruleset)
    {
        $this->ruleset = $ruleset;
        foreach ($ruleset->sniffs as $class_name => $sniff_class) {
            $file = Autoload::get_loaded_file_name($class_name);
            $doc_file = str_replace(DIRECTORY_SEPARATOR . 'Sniffs' . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR . 'Docs' . DIRECTORY_SEPARATOR, $file);
            $doc_file = str_replace('Sniff.php', 'Standard.xml', $doc_file);
            if (is_file($doc_file) === true) {
                $this->doc_files[] = $doc_file;
            }
        }
    }
    //end __construct()
    /**
     * Retrieves the title of the sniff from the DOMNode supplied.
     *
     * @param \DOMNode $doc The DOMNode object for the sniff.
     *                      It represents the "documentation" tag in the XML
     *                      standard file.
     *
     * @return string
     */
    protected function get_title(\Dom_Node $doc)
    {
        return $doc->get_attribute('title');
    }
    //end getTitle()
    /**
     * Generates the documentation for a standard.
     *
     * It's probably wise for doc generators to override this method so they
     * have control over how the docs are produced. Otherwise, the processSniff
     * method should be overridden to output content for each sniff.
     *
     * @return void
     * @see    processSniff()
     */
    public function generate()
    {
        foreach ($this->doc_files as $file) {
            $doc = new \Dom_Document();
            $doc->load($file);
            $documentation = $doc->get_elements_by_tag_name('documentation')->item(0);
            $this->process_sniff($documentation);
        }
    }
    //end generate()
    /**
     * Process the documentation for a single sniff.
     *
     * Doc generators must implement this function to produce output.
     *
     * @param \DOMNode $doc The DOMNode object for the sniff.
     *                      It represents the "documentation" tag in the XML
     *                      standard file.
     *
     * @return void
     * @see    generate()
     */
    abstract protected function process_sniff(\Dom_Node $doc);
}
//end class