<?php

declare (strict_types=1);
/**
 * A doc generator that outputs text-based documentation.
 *
 * Output is designed to be displayed in a terminal and is wrapped to 100 characters.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Generators;

class Text extends Generator
{
    /**
     * Process the documentation for a single sniff.
     *
     * @param \DOMNode $doc The DOMNode object for the sniff.
     *                      It represents the "documentation" tag in the XML
     *                      standard file.
     *
     * @return void
     */
    public function process_sniff(\Dom_Node $doc)
    {
        $this->print_title($doc);
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
     * Prints the title area for a single sniff.
     *
     * @param \DOMNode $doc The DOMNode object for the sniff.
     *                      It represents the "documentation" tag in the XML
     *                      standard file.
     *
     * @return void
     */
    protected function print_title(\Dom_Node $doc)
    {
        $title = $this->get_title($doc);
        $standard = $this->ruleset->name;
        echo PHP_EOL;
        echo str_repeat('-', strlen("{$standard} CODING STANDARD: {$title}") + 4);
        echo strtoupper(PHP_EOL . "| {$standard} CODING STANDARD: {$title} |" . PHP_EOL);
        echo str_repeat('-', strlen("{$standard} CODING STANDARD: {$title}") + 4);
        echo PHP_EOL . PHP_EOL;
    }
    //end printTitle()
    /**
     * Print a text block found in a standard.
     *
     * @param \DOMNode $node The DOMNode object for the text block.
     *
     * @return void
     */
    protected function print_text_block(\Dom_Node $node)
    {
        $text = trim($node->node_value);
        $text = str_replace('<em>', '*', $text);
        $text = str_replace('</em>', '*', $text);
        $node_lines = explode("\n", $text);
        $lines = [];
        foreach ($node_lines as $current_line) {
            $current_line = trim($current_line);
            if ($current_line === '') {
                // The text contained a blank line. Respect this.
                $lines[] = '';
                continue;
            }
            $temp_line = '';
            $words = explode(' ', $current_line);
            foreach ($words as $word) {
                $current_length = strlen($temp_line . $word);
                if ($current_length < 99) {
                    $temp_line .= $word . ' ';
                    continue;
                }
                if ($current_length === 99 || $current_length === 100) {
                    // We are already at the edge, so we are done.
                    $lines[] = $temp_line . $word;
                    $temp_line = '';
                } else {
                    $lines[] = rtrim($temp_line);
                    $temp_line = $word . ' ';
                }
            }
            //end foreach
            if ($temp_line !== '') {
                $lines[] = rtrim($temp_line);
            }
        }
        //end foreach
        echo implode(PHP_EOL, $lines) . PHP_EOL . PHP_EOL;
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
        $first = trim($code_blocks->item(0)->node_value);
        $first_title = $code_blocks->item(0)->get_attribute('title');
        $first_title_lines = [];
        $temp_title = '';
        $words = explode(' ', $first_title);
        foreach ($words as $word) {
            if (strlen($temp_title . $word) >= 45) {
                if (strlen($temp_title . $word) === 45) {
                    // Adding the extra space will push us to the edge
                    // so we are done.
                    $first_title_lines[] = $temp_title . $word;
                    $temp_title = '';
                } elseif (strlen($temp_title . $word) === 46) {
                    // We are already at the edge, so we are done.
                    $first_title_lines[] = $temp_title . $word;
                    $temp_title = '';
                } else {
                    $first_title_lines[] = $temp_title;
                    $temp_title = $word . ' ';
                }
            } else {
                $temp_title .= $word . ' ';
            }
        }
        //end foreach
        if ($temp_title !== '') {
            $first_title_lines[] = $temp_title;
        }
        $first = str_replace('<em>', '', $first);
        $first = str_replace('</em>', '', $first);
        $first_lines = explode("\n", $first);
        $second = trim($code_blocks->item(1)->node_value);
        $second_title = $code_blocks->item(1)->get_attribute('title');
        $second_title_lines = [];
        $temp_title = '';
        $words = explode(' ', $second_title);
        foreach ($words as $word) {
            if (strlen($temp_title . $word) >= 45) {
                if (strlen($temp_title . $word) === 45) {
                    // Adding the extra space will push us to the edge
                    // so we are done.
                    $second_title_lines[] = $temp_title . $word;
                    $temp_title = '';
                } elseif (strlen($temp_title . $word) === 46) {
                    // We are already at the edge, so we are done.
                    $second_title_lines[] = $temp_title . $word;
                    $temp_title = '';
                } else {
                    $second_title_lines[] = $temp_title;
                    $temp_title = $word . ' ';
                }
            } else {
                $temp_title .= $word . ' ';
            }
        }
        //end foreach
        if ($temp_title !== '') {
            $second_title_lines[] = $temp_title;
        }
        $second = str_replace('<em>', '', $second);
        $second = str_replace('</em>', '', $second);
        $second_lines = explode("\n", $second);
        $max_code_lines = max(count($first_lines), count($second_lines));
        $max_title_lines = max(count($first_title_lines), count($second_title_lines));
        echo str_repeat('-', 41);
        echo ' CODE COMPARISON ';
        echo str_repeat('-', 42) . PHP_EOL;
        for ($i = 0; $i < $max_title_lines; $i++) {
            if (isset($first_title_lines[$i]) === true) {
                $first_line_text = $first_title_lines[$i];
            } else {
                $first_line_text = '';
            }
            if (isset($second_title_lines[$i]) === true) {
                $second_line_text = $second_title_lines[$i];
            } else {
                $second_line_text = '';
            }
            echo '| ';
            echo $first_line_text . str_repeat(' ', 46 - strlen($first_line_text));
            echo ' | ';
            echo $second_line_text . str_repeat(' ', 47 - strlen($second_line_text));
            echo ' |' . PHP_EOL;
        }
        //end for
        echo str_repeat('-', 100) . PHP_EOL;
        for ($i = 0; $i < $max_code_lines; $i++) {
            if (isset($first_lines[$i]) === true) {
                $first_line_text = $first_lines[$i];
            } else {
                $first_line_text = '';
            }
            if (isset($second_lines[$i]) === true) {
                $second_line_text = $second_lines[$i];
            } else {
                $second_line_text = '';
            }
            echo '| ';
            echo $first_line_text . str_repeat(' ', max(0, 47 - strlen($first_line_text)));
            echo '| ';
            echo $second_line_text . str_repeat(' ', max(0, 48 - strlen($second_line_text)));
            echo '|' . PHP_EOL;
        }
        //end for
        echo str_repeat('-', 100) . PHP_EOL . PHP_EOL;
    }
    //end printCodeComparisonBlock()
}
//end class