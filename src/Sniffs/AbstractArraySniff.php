<?php

declare (strict_types=1);
/**
 * Processes single and multi-line arrays.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Sniffs;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Util\Tokens;
abstract class Abstract_Array_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    final public function register()
    {
        return [T_ARRAY, T_OPEN_SHORT_ARRAY];
    }
    //end register()
    /**
     * Processes this sniff, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The current file being checked.
     * @param int                         $stackPtr  The position of the current token in
     *                                               the stack passed in $tokens.
     *
     * @return void
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        if ($tokens[$stack_ptr]['code'] === T_ARRAY) {
            $phpcs_file->record_metric($stack_ptr, 'Short array syntax used', 'no');
            $array_start = $tokens[$stack_ptr]['parenthesis_opener'];
            if (isset($tokens[$array_start]['parenthesis_closer']) === false) {
                // Incomplete array.
                return;
            }
            $array_end = $tokens[$array_start]['parenthesis_closer'];
        } else {
            $phpcs_file->record_metric($stack_ptr, 'Short array syntax used', 'yes');
            $array_start = $stack_ptr;
            $array_end = $tokens[$stack_ptr]['bracket_closer'];
        }
        $last_content = $phpcs_file->find_previous(Tokens::$empty_tokens, $array_end - 1, null, true);
        if ($tokens[$last_content]['code'] === T_COMMA) {
            // Last array item ends with a comma.
            $phpcs_file->record_metric($stack_ptr, 'Array end comma', 'yes');
        } else {
            $phpcs_file->record_metric($stack_ptr, 'Array end comma', 'no');
        }
        $indices = [];
        $current = $array_start;
        while (($next = $phpcs_file->find_next(Tokens::$empty_tokens, $current + 1, $array_end, true)) !== false) {
            $end = $this->get_next($phpcs_file, $next, $array_end);
            if ($tokens[$end]['code'] === T_DOUBLE_ARROW) {
                $index_end = $phpcs_file->find_previous(T_WHITESPACE, $end - 1, null, true);
                $value_start = $phpcs_file->find_next(Tokens::$empty_tokens, $end + 1, null, true);
                $indices[] = ['index_start' => $next, 'index_end' => $index_end, 'arrow' => $end, 'value_start' => $value_start];
            } else {
                $value_start = $next;
                $indices[] = ['value_start' => $value_start];
            }
            $current = $this->get_next($phpcs_file, $value_start, $array_end);
        }
        if ($tokens[$array_start]['line'] === $tokens[$array_end]['line']) {
            $this->process_single_line_array($phpcs_file, $stack_ptr, $array_start, $array_end, $indices);
        } else {
            $this->process_multi_line_array($phpcs_file, $stack_ptr, $array_start, $array_end, $indices);
        }
    }
    //end process()
    /**
     * Find next separator in array - either: comma or double arrow.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The current file being checked.
     * @param int                         $ptr       The position of current token.
     * @param int                         $arrayEnd  The token that ends the array definition.
     *
     * @return int
     */
    private function get_next(File $phpcs_file, $ptr, $array_end)
    {
        $tokens = $phpcs_file->get_tokens();
        while ($ptr < $array_end) {
            if (isset($tokens[$ptr]['scope_closer']) === true) {
                $ptr = $tokens[$ptr]['scope_closer'];
            } elseif (isset($tokens[$ptr]['parenthesis_closer']) === true) {
                $ptr = $tokens[$ptr]['parenthesis_closer'];
            } elseif (isset($tokens[$ptr]['bracket_closer']) === true) {
                $ptr = $tokens[$ptr]['bracket_closer'];
            }
            if ($tokens[$ptr]['code'] === T_COMMA || $tokens[$ptr]['code'] === T_DOUBLE_ARROW) {
                return $ptr;
            }
            ++$ptr;
        }
        return $ptr;
    }
    //end getNext()
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
    abstract protected function process_single_line_array($phpcs_file, $stack_ptr, $array_start, $array_end, $indices);
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
    abstract protected function process_multi_line_array($phpcs_file, $stack_ptr, $array_start, $array_end, $indices);
}
//end class