<?php

declare (strict_types=1);
/**
 * Ensure that there are no spaces around square brackets.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\Arrays;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Array_Bracket_Spacing_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_OPEN_SQUARE_BRACKET, T_CLOSE_SQUARE_BRACKET];
    }
    //end register()
    /**
     * Processes this sniff, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The current file being checked.
     * @param int                         $stackPtr  The position of the current token in the
     *                                               stack passed in $tokens.
     *
     * @return void
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        if ($tokens[$stack_ptr]['code'] === T_OPEN_SQUARE_BRACKET && isset($tokens[$stack_ptr]['bracket_closer']) === false || $tokens[$stack_ptr]['code'] === T_CLOSE_SQUARE_BRACKET && isset($tokens[$stack_ptr]['bracket_opener']) === false) {
            // Bow out for parse error/during live coding.
            return;
        }
        // Square brackets can not have a space before them.
        $prev_type = $tokens[$stack_ptr - 1]['code'];
        if ($prev_type === T_WHITESPACE) {
            $non_space = $phpcs_file->find_previous(T_WHITESPACE, $stack_ptr - 2, null, true);
            $expected = $tokens[$non_space]['content'] . $tokens[$stack_ptr]['content'];
            $found = $phpcs_file->get_tokens_as_string($non_space, $stack_ptr - $non_space) . $tokens[$stack_ptr]['content'];
            $error = 'Space found before square bracket; expected "%s" but found "%s"';
            $data = [$expected, $found];
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'SpaceBeforeBracket', $data);
            if ($fix === true) {
                $phpcs_file->fixer->replace_token($stack_ptr - 1, '');
            }
        }
        // Open square brackets can't ever have spaces after them.
        if ($tokens[$stack_ptr]['code'] === T_OPEN_SQUARE_BRACKET) {
            $next_type = $tokens[$stack_ptr + 1]['code'];
            if ($next_type === T_WHITESPACE) {
                $non_space = $phpcs_file->find_next(T_WHITESPACE, $stack_ptr + 2, null, true);
                $expected = $tokens[$stack_ptr]['content'] . $tokens[$non_space]['content'];
                $found = $phpcs_file->get_tokens_as_string($stack_ptr, $non_space - $stack_ptr + 1);
                $error = 'Space found after square bracket; expected "%s" but found "%s"';
                $data = [$expected, $found];
                $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'SpaceAfterBracket', $data);
                if ($fix === true) {
                    $phpcs_file->fixer->replace_token($stack_ptr + 1, '');
                }
            }
        }
    }
    //end process()
}
//end class