<?php

declare (strict_types=1);
/**
 * Checks the //end ... comments on classes, interfaces and functions.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\Commenting;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Closing_Declaration_Comment_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_FUNCTION, T_CLASS, T_INTERFACE, T_ENUM];
    }
    //end register()
    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token in the
     *                                               stack passed in $tokens..
     *
     * @return void
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        if ($tokens[$stack_ptr]['code'] === T_FUNCTION) {
            $method_props = $phpcs_file->get_method_properties($stack_ptr);
            // Abstract methods do not require a closing comment.
            if ($method_props['is_abstract'] === true) {
                return;
            }
            // If this function is in an interface then we don't require
            // a closing comment.
            if ($phpcs_file->has_condition($stack_ptr, T_INTERFACE) === true) {
                return;
            }
            if (isset($tokens[$stack_ptr]['scope_closer']) === false) {
                $error = 'Possible parse error: non-abstract method defined as abstract';
                $phpcs_file->add_warning($error, $stack_ptr, 'Abstract');
                return;
            }
            $dec_name = $phpcs_file->get_declaration_name($stack_ptr);
            $comment = '//end ' . $dec_name . '()';
        } elseif ($tokens[$stack_ptr]['code'] === T_CLASS) {
            $comment = '//end class';
        } elseif ($tokens[$stack_ptr]['code'] === T_INTERFACE) {
            $comment = '//end interface';
        } else {
            $comment = '//end enum';
        }
        //end if
        if (isset($tokens[$stack_ptr]['scope_closer']) === false) {
            $error = 'Possible parse error: %s missing opening or closing brace';
            $data = [$tokens[$stack_ptr]['content']];
            $phpcs_file->add_warning($error, $stack_ptr, 'MissingBrace', $data);
            return;
        }
        $closing_bracket = $tokens[$stack_ptr]['scope_closer'];
        if ($closing_bracket === null) {
            // Possible inline structure. Other tests will handle it.
            return;
        }
        $data = [$comment];
        if (isset($tokens[$closing_bracket + 1]) === false || $tokens[$closing_bracket + 1]['code'] !== T_COMMENT) {
            $next = $phpcs_file->find_next(T_WHITESPACE, $closing_bracket + 1, null, true);
            if (rtrim($tokens[$next]['content']) === $comment) {
                // The comment isn't really missing; it is just in the wrong place.
                $fix = $phpcs_file->add_fixable_error('Expected %s directly after closing brace', $closing_bracket, 'Misplaced', $data);
                if ($fix === true) {
                    $phpcs_file->fixer->begin_changeset();
                    for ($i = $closing_bracket + 1; $i < $next; $i++) {
                        $phpcs_file->fixer->replace_token($i, '');
                    }
                    // Just in case, because indentation fixes can add indents onto
                    // these comments and cause us to be unable to fix them.
                    $phpcs_file->fixer->replace_token($next, $comment . $phpcs_file->eol_char);
                    $phpcs_file->fixer->end_changeset();
                }
            } else {
                $fix = $phpcs_file->add_fixable_error('Expected %s', $closing_bracket, 'Missing', $data);
                if ($fix === true) {
                    $phpcs_file->fixer->replace_token($closing_bracket, '}' . $comment . $phpcs_file->eol_char);
                }
            }
            return;
        }
        //end if
        if (rtrim($tokens[$closing_bracket + 1]['content']) !== $comment) {
            $fix = $phpcs_file->add_fixable_error('Expected %s', $closing_bracket, 'Incorrect', $data);
            if ($fix === true) {
                $phpcs_file->fixer->replace_token($closing_bracket + 1, $comment . $phpcs_file->eol_char);
            }
            return;
        }
    }
    //end process()
}
//end class