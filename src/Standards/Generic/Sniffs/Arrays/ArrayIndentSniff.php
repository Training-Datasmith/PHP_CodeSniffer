<?php

declare (strict_types=1);
/**
 * Ensures that array are indented one tab stop.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Generic\Sniffs\Arrays;

use Php_code_Sniffer\Sniffs\Abstract_Array_Sniff;
use Php_code_Sniffer\Util\Tokens;
class Array_Indent_Sniff extends Abstract_Array_Sniff
{
    /**
     * The number of spaces each array key should be indented.
     *
     * @var integer
     */
    public $indent = 4;
    /**
     * Processes a single-line array definition.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile  The current file being checked.
     * @param int                         $stackPtr   The position of the current token
     *                                                in the stack passed in $tokens.
     * @param int                         $arrayStart The token that starts the array definition.
     * @param int                         $arrayEnd   The token that ends the array definition.
     * @param array                       $indices    An array of token positions for the array keys,
     *                                                double arrows, and values.
     *
     * @return void
     */
    public function process_single_line_array($phpcs_file, $stack_ptr, $array_start, $array_end, $indices)
    {
    }
    //end processSingleLineArray()
    /**
     * Processes a multi-line array definition.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile  The current file being checked.
     * @param int                         $stackPtr   The position of the current token
     *                                                in the stack passed in $tokens.
     * @param int                         $arrayStart The token that starts the array definition.
     * @param int                         $arrayEnd   The token that ends the array definition.
     * @param array                       $indices    An array of token positions for the array keys,
     *                                                double arrows, and values.
     *
     * @return void
     */
    public function process_multi_line_array($phpcs_file, $stack_ptr, $array_start, $array_end, $indices)
    {
        $tokens = $phpcs_file->get_tokens();
        // Determine how far indented the entire array declaration should be.
        $ignore = Tokens::$empty_tokens;
        $ignore[] = T_DOUBLE_ARROW;
        $ignore[] = T_COMMA;
        $prev = $phpcs_file->find_previous($ignore, $stack_ptr - 1, null, true);
        $start = $phpcs_file->find_start_of_statement($prev);
        $first = $phpcs_file->find_first_on_line(T_WHITESPACE, $start, true);
        $base_indent = $tokens[$first]['column'] - 1;
        $first = $phpcs_file->find_first_on_line(T_WHITESPACE, $stack_ptr, true);
        $start_indent = $tokens[$first]['column'] - 1;
        // If the open brace is not indented to at least to the level of the start
        // of the statement, the sniff will conflict with other sniffs trying to
        // check indent levels because it's not valid. But we don't enforce exactly
        // how far indented it should be.
        if ($start_indent < $base_indent) {
            $error = 'Array open brace not indented correctly; expected at least %s spaces but found %s';
            $data = [$base_indent, $start_indent];
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'OpenBraceIncorrect', $data);
            if ($fix === true) {
                $padding = str_repeat(' ', $base_indent);
                if ($start_indent === 0) {
                    $phpcs_file->fixer->add_content_before($first, $padding);
                } else {
                    $phpcs_file->fixer->replace_token($first - 1, $padding);
                }
            }
            return;
        }
        //end if
        $expected_indent = $start_indent + $this->indent;
        foreach ($indices as $index) {
            if (isset($index['index_start']) === true) {
                $start = $index['index_start'];
            } else {
                $start = $index['value_start'];
            }
            $prev = $phpcs_file->find_previous(Tokens::$empty_tokens, $start - 1, null, true);
            if ($tokens[$prev]['line'] === $tokens[$start]['line']) {
                // This index isn't the only content on the line
                // so we can't check indent rules.
                continue;
            }
            $first = $phpcs_file->find_first_on_line(T_WHITESPACE, $start, true);
            $found_indent = $tokens[$first]['column'] - 1;
            if ($found_indent === $expected_indent) {
                continue;
            }
            $error = 'Array key not indented correctly; expected %s spaces but found %s';
            $data = [$expected_indent, $found_indent];
            $fix = $phpcs_file->add_fixable_error($error, $first, 'KeyIncorrect', $data);
            if ($fix === false) {
                continue;
            }
            $padding = str_repeat(' ', $expected_indent);
            if ($found_indent === 0) {
                $phpcs_file->fixer->add_content_before($first, $padding);
            } else {
                $phpcs_file->fixer->replace_token($first - 1, $padding);
            }
        }
        //end foreach
        $prev = $phpcs_file->find_previous(T_WHITESPACE, $array_end - 1, null, true);
        if ($tokens[$prev]['line'] === $tokens[$array_end]['line']) {
            $error = 'Closing brace of array declaration must be on a new line';
            $fix = $phpcs_file->add_fixable_error($error, $array_end, 'CloseBraceNotNewLine');
            if ($fix === true) {
                $padding = $phpcs_file->eol_char . str_repeat(' ', $expected_indent);
                $phpcs_file->fixer->add_content_before($array_end, $padding);
            }
            return;
        }
        // The close brace must be indented one stop less.
        $expected_indent -= $this->indent;
        $found_indent = $tokens[$array_end]['column'] - 1;
        if ($found_indent === $expected_indent) {
            return;
        }
        $error = 'Array close brace not indented correctly; expected %s spaces but found %s';
        $data = [$expected_indent, $found_indent];
        $fix = $phpcs_file->add_fixable_error($error, $array_end, 'CloseBraceIncorrect', $data);
        if ($fix === false) {
            return;
        }
        $padding = str_repeat(' ', $expected_indent);
        if ($found_indent === 0) {
            $phpcs_file->fixer->add_content_before($array_end, $padding);
        } else {
            $phpcs_file->fixer->replace_token($array_end - 1, $padding);
        }
    }
    //end processMultiLineArray()
}
//end class