<?php

declare (strict_types=1);
/**
 * Tests the spacing of shorthand IF statements.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\Control_Structures;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Inline_If_Declaration_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_INLINE_THEN];
    }
    //end register()
    /**
     * Processes this sniff, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token in the
     *                                               stack passed in $tokens.
     *
     * @return void
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        $open_bracket = null;
        $close_bracket = null;
        if (isset($tokens[$stack_ptr]['nested_parenthesis']) === true) {
            $parens = $tokens[$stack_ptr]['nested_parenthesis'];
            $open_bracket = array_pop($parens);
            $close_bracket = $tokens[$open_bracket]['parenthesis_closer'];
        }
        // Find the beginning of the statement. If we don't find a
        // semicolon (end of statement) or comma (end of array value)
        // then assume the content before the closing parenthesis is the end.
        $else = $phpcs_file->find_next(T_INLINE_ELSE, $stack_ptr + 1);
        $statement_end = $phpcs_file->find_next([T_SEMICOLON, T_COMMA], $else + 1, $close_bracket);
        if ($statement_end === false) {
            $statement_end = $phpcs_file->find_previous(T_WHITESPACE, $close_bracket - 1, null, true);
        }
        // Make sure it's all on the same line.
        if ($tokens[$statement_end]['line'] !== $tokens[$stack_ptr]['line']) {
            $error = 'Inline shorthand IF statement must be declared on a single line';
            $phpcs_file->add_error($error, $stack_ptr, 'NotSingleLine');
            return;
        }
        // Make sure there are spaces around the question mark.
        $content_before = $phpcs_file->find_previous(T_WHITESPACE, $stack_ptr - 1, null, true);
        $content_after = $phpcs_file->find_next(T_WHITESPACE, $stack_ptr + 1, null, true);
        if ($tokens[$content_before]['code'] !== T_CLOSE_PARENTHESIS) {
            $error = 'Inline shorthand IF statement requires brackets around comparison';
            $phpcs_file->add_error($error, $stack_ptr, 'NoBrackets');
        }
        $space_before = $tokens[$stack_ptr]['column'] - ($tokens[$content_before]['column'] + $tokens[$content_before]['length']);
        if ($space_before !== 1) {
            $error = 'Inline shorthand IF statement requires 1 space before THEN; %s found';
            $data = [$space_before];
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'SpacingBeforeThen', $data);
            if ($fix === true) {
                if ($space_before === 0) {
                    $phpcs_file->fixer->add_content_before($stack_ptr, ' ');
                } else {
                    $phpcs_file->fixer->replace_token($stack_ptr - 1, ' ');
                }
            }
        }
        // If there is no content between the ? and the : operators, then they are
        // trying to replicate an elvis operator, even though PHP doesn't have one.
        // In this case, we want no spaces between the two operators so ?: looks like
        // an operator itself.
        $next = $phpcs_file->find_next(T_WHITESPACE, $stack_ptr + 1, null, true);
        if ($tokens[$next]['code'] === T_INLINE_ELSE) {
            $inline_else = $next;
            if ($inline_else !== $stack_ptr + 1) {
                $error = 'Inline shorthand IF statement without THEN statement requires 0 spaces between THEN and ELSE';
                $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'ElvisSpacing');
                if ($fix === true) {
                    $phpcs_file->fixer->replace_token($stack_ptr + 1, '');
                }
            }
        } else {
            $space_after = $tokens[$content_after]['column'] - ($tokens[$stack_ptr]['column'] + 1);
            if ($space_after !== 1) {
                $error = 'Inline shorthand IF statement requires 1 space after THEN; %s found';
                $data = [$space_after];
                $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'SpacingAfterThen', $data);
                if ($fix === true) {
                    if ($space_after === 0) {
                        $phpcs_file->fixer->add_content($stack_ptr, ' ');
                    } else {
                        $phpcs_file->fixer->replace_token($stack_ptr + 1, ' ');
                    }
                }
            }
            // Make sure the ELSE has the correct spacing.
            $inline_else = $phpcs_file->find_next(T_INLINE_ELSE, $stack_ptr + 1, $statement_end, false);
            $content_before = $phpcs_file->find_previous(T_WHITESPACE, $inline_else - 1, null, true);
            $space_before = $tokens[$inline_else]['column'] - ($tokens[$content_before]['column'] + $tokens[$content_before]['length']);
            if ($space_before !== 1) {
                $error = 'Inline shorthand IF statement requires 1 space before ELSE; %s found';
                $data = [$space_before];
                $fix = $phpcs_file->add_fixable_error($error, $inline_else, 'SpacingBeforeElse', $data);
                if ($fix === true) {
                    if ($space_before === 0) {
                        $phpcs_file->fixer->add_content_before($inline_else, ' ');
                    } else {
                        $phpcs_file->fixer->replace_token($inline_else - 1, ' ');
                    }
                }
            }
        }
        //end if
        $content_after = $phpcs_file->find_next(T_WHITESPACE, $inline_else + 1, null, true);
        $space_after = $tokens[$content_after]['column'] - ($tokens[$inline_else]['column'] + 1);
        if ($space_after !== 1) {
            $error = 'Inline shorthand IF statement requires 1 space after ELSE; %s found';
            $data = [$space_after];
            $fix = $phpcs_file->add_fixable_error($error, $inline_else, 'SpacingAfterElse', $data);
            if ($fix === true) {
                if ($space_after === 0) {
                    $phpcs_file->fixer->add_content($inline_else, ' ');
                } else {
                    $phpcs_file->fixer->replace_token($inline_else + 1, ' ');
                }
            }
        }
    }
    //end process()
}
//end class