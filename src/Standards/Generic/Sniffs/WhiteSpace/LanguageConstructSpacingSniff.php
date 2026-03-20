<?php

declare (strict_types=1);
/**
 * Ensures all language constructs contain a single space between themselves and their content.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2017 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Generic\Sniffs\White_Space;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Common;
use Php_code_Sniffer\Util\Tokens;
class Language_Construct_Spacing_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_ECHO, T_PRINT, T_RETURN, T_INCLUDE, T_INCLUDE_ONCE, T_REQUIRE, T_REQUIRE_ONCE, T_NEW, T_YIELD, T_YIELD_FROM, T_THROW, T_NAMESPACE, T_USE];
    }
    //end register()
    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token in
     *                                               the stack passed in $tokens.
     *
     * @return void
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        $next_token = $phpcs_file->find_next(T_WHITESPACE, $stack_ptr + 1, null, true);
        if ($next_token === false) {
            // Skip when at end of file.
            return;
        }
        if ($tokens[$stack_ptr + 1]['code'] === T_SEMICOLON) {
            // No content for this language construct.
            return;
        }
        $content = $tokens[$stack_ptr]['content'];
        if ($tokens[$stack_ptr]['code'] === T_NAMESPACE) {
            $next_non_empty = $phpcs_file->find_next(Tokens::$empty_tokens, $stack_ptr + 1, null, true);
            if ($next_non_empty !== false && $tokens[$next_non_empty]['code'] === T_NS_SEPARATOR) {
                // Namespace keyword used as operator, not as the language construct.
                return;
            }
        }
        if ($tokens[$stack_ptr]['code'] === T_YIELD_FROM && strtolower($content) !== 'yield from') {
            if ($tokens[$stack_ptr - 1]['code'] === T_YIELD_FROM) {
                // A multi-line statements that has already been processed.
                return;
            }
            $found = $content;
            if ($tokens[$stack_ptr + 1]['code'] === T_YIELD_FROM) {
                // This yield from statement is split over multiple lines.
                $i = $stack_ptr + 1;
                do {
                    $found .= $tokens[$i]['content'];
                    $i++;
                } while ($tokens[$i]['code'] === T_YIELD_FROM);
            }
            $error = 'Language constructs must be followed by a single space; expected 1 space between YIELD FROM found "%s"';
            $data = [Common::prepare_for_output($found)];
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'IncorrectYieldFrom', $data);
            if ($fix === true) {
                preg_match('/yield/i', $found, $yield);
                preg_match('/from/i', $found, $from);
                $phpcs_file->fixer->begin_changeset();
                $phpcs_file->fixer->replace_token($stack_ptr, $yield[0] . ' ' . $from[0]);
                if ($tokens[$stack_ptr + 1]['code'] === T_YIELD_FROM) {
                    $i = $stack_ptr + 1;
                    do {
                        $phpcs_file->fixer->replace_token($i, '');
                        $i++;
                    } while ($tokens[$i]['code'] === T_YIELD_FROM);
                }
                $phpcs_file->fixer->end_changeset();
            }
            return;
        }
        //end if
        if ($tokens[$stack_ptr + 1]['code'] === T_WHITESPACE) {
            $content = $tokens[$stack_ptr + 1]['content'];
            if ($content !== ' ') {
                $error = 'Language constructs must be followed by a single space; expected 1 space but found "%s"';
                $data = [Common::prepare_for_output($content)];
                $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'IncorrectSingle', $data);
                if ($fix === true) {
                    $phpcs_file->fixer->replace_token($stack_ptr + 1, ' ');
                }
            }
        } elseif ($tokens[$stack_ptr + 1]['code'] !== T_OPEN_PARENTHESIS) {
            $error = 'Language constructs must be followed by a single space; expected "%s" but found "%s"';
            $data = [$tokens[$stack_ptr]['content'] . ' ' . $tokens[$stack_ptr + 1]['content'], $tokens[$stack_ptr]['content'] . $tokens[$stack_ptr + 1]['content']];
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'Incorrect', $data);
            if ($fix === true) {
                $phpcs_file->fixer->add_content($stack_ptr, ' ');
            }
        }
        //end if
    }
    //end process()
}
//end class