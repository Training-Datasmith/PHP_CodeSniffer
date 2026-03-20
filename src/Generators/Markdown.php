<?php

declare (strict_types=1);
/**
 * A doc generator that outputs documentation in Markdown format.
 *
 * @author    Stefano Kowalke <blueduck@gmx.net>
 * @copyright 2014 Arroba IT
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Generators;

use Php_code_Sniffer\Config;
class Markdown extends Generator
{
    /**
     * Generates the documentation for a standard.
     *
     * @return void
     * @see    processSniff()
     */
    public function generate()
    {
        ob_start();
        $this->print_header();
        foreach ($this->doc_files as $file) {
            $doc = new \Dom_Document();
            $doc->load($file);
            $documentation = $doc->get_elements_by_tag_name('documentation')->item(0);
            $this->process_sniff($documentation);
        }
        $this->print_footer();
        $content = ob_get_contents();
        ob_end_clean();
        echo $content;
    }
    //end generate()
    /**
     * Print the markdown header.
     *
     * @return void
     */
    protected function print_header()
    {
        $standard = $this->ruleset->name;
        echo "# {$standard} Coding Standard" . PHP_EOL;
    }
    //end printHeader()
    /**
     * Print the markdown footer.
     *
     * @return void
     */
    protected function print_footer()
    {
        // Turn off errors so we don't get timezone warnings if people
        // don't have their timezone set.
        error_reporting(0);
        echo 'Documentation generated on ' . date('r');
        echo ' by [PHP_CodeSniffer ' . Config::VERSION . '](https://github.com/squizlabs/PHP_CodeSniffer)' . PHP_EOL;
    }
    //end printFooter()
    /**
     * Process the documentation for a single sniff.
     *
     * @param \DOMNode $doc The DOMNode object for the sniff.
     *                      It represents the "documentation" tag in the XML
     *                      standard file.
     *
     * @return void
     */
    protected function process_sniff(\Dom_Node $doc)
    {
        $title = $this->get_title($doc);
        echo PHP_EOL . "## {$title}" . PHP_EOL;
        foreach ($doc->child_nodes as $node) {
            if ($node->node_name === 'standard') {
                $this->print_text_block($node);
            } elseif ($node->node_name === 'code_comparison') {
                $this->print_code_comparison_block($node);
            }
        }
    }
    //end processSniff()
    /**
     * Print a text block found in a standard.
     *
     * @param \DOMNode $node The DOMNode object for the text block.
     *
     * @return void
     */
    protected function print_text_block(\Dom_Node $node)
    {
        $content = trim($node->node_value);
        $content = htmlspecialchars($content);
        $content = str_replace('&lt;em&gt;', '*', $content);
        $content = str_replace('&lt;/em&gt;', '*', $content);
        echo $content . PHP_EOL;
    }
    //end printTextBlock()
    /**
     * Print a code comparison block found in a standard.
     *
     * @param \DOMNode $node The DOMNode object for the code comparison block.
     *
     * @return void
     */
    protected function print_code_comparison_block(\Dom_Node $node)
    {
        $code_blocks = $node->get_elements_by_tag_name('code');
        $first_title = $code_blocks->item(0)->get_attribute('title');
        $first = trim($code_blocks->item(0)->node_value);
        $first = str_replace("\n", "\n    ", $first);
        $first = str_replace('<em>', '', $first);
        $first = str_replace('</em>', '', $first);
        $second_title = $code_blocks->item(1)->get_attribute('title');
        $second = trim($code_blocks->item(1)->node_value);
        $second = str_replace("\n", "\n    ", $second);
        $second = str_replace('<em>', '', $second);
        $second = str_replace('</em>', '', $second);
        echo '  <table>' . PHP_EOL;
        echo '   <tr>' . PHP_EOL;
        echo "    <th>{$first_title}</th>" . PHP_EOL;
        echo "    <th>{$second_title}</th>" . PHP_EOL;
        echo '   </tr>' . PHP_EOL;
        echo '   <tr>' . PHP_EOL;
        echo '<td>' . PHP_EOL . PHP_EOL;
        echo "    {$first}" . PHP_EOL . PHP_EOL;
        echo '</td>' . PHP_EOL;
        echo '<td>' . PHP_EOL . PHP_EOL;
        echo "    {$second}" . PHP_EOL . PHP_EOL;
        echo '</td>' . PHP_EOL;
        echo '   </tr>' . PHP_EOL;
        echo '  </table>' . PHP_EOL;
    }
    //end printCodeComparisonBlock()
}
//end class