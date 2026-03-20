<?php

declare (strict_types=1);
/**
 * Checks that there is one empty line before the closing brace of a function.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\White_Space;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Function_Closing_Brace_Space_Sniff implements Sniff
{
    /**
     * A list of tokenizers this sniff supports.
     *
     * @var array
     */
    public $supported_tokenizers = ['PHP', 'JS'];
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_FUNCTION, T_CLOSURE];
    }
    //end register()
    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token
     *                                               in the stack passed in $tokens.
     *
     * @return void
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        if (isset($tokens[$stack_ptr]['scope_closer']) === false) {
            // Probably an interface method.
            return;
        }
        $close_brace = $tokens[$stack_ptr]['scope_closer'];
        $prev_content = $phpcs_file->find_previous(T_WHITESPACE, $close_brace - 1, null, true);
        // Special case for empty JS functions.
        if ($phpcs_file->tokenizer_type === 'JS' && $prev_content === $tokens[$stack_ptr]['scope_opener']) {
            // In this case, the opening and closing brace must be
            // right next to each other.
            if ($tokens[$stack_ptr]['scope_closer'] !== $tokens[$stack_ptr]['scope_opener'] + 1) {
                $error = 'The opening and closing braces of empty functions must be directly next to each other; e.g., function () {}';
                $fix = $phpcs_file->add_fixable_error($error, $close_brace, 'SpacingBetween');
                if ($fix === true) {
                    $phpcs_file->fixer->begin_changeset();
                    for ($i = $tokens[$stack_ptr]['scope_opener'] + 1; $i < $close_brace; $i++) {
                        $phpcs_file->fixer->replace_token($i, '');
                    }
                    $phpcs_file->fixer->end_changeset();
                }
            }
            return;
        }
        $nested_function = false;
        if ($phpcs_file->has_condition($stack_ptr, [T_FUNCTION, T_CLOSURE]) === true || isset($tokens[$stack_ptr]['nested_parenthesis']) === true) {
            $nested_function = true;
        }
        $brace_line = $tokens[$close_brace]['line'];
        $prev_line = $tokens[$prev_content]['line'];
        $found = $brace_line - $prev_line - 1;
        if ($nested_function === true) {
            if ($found < 0) {
                $error = 'Closing brace of nested function must be on a new line';
                $fix = $phpcs_file->add_fixable_error($error, $close_brace, 'ContentBeforeClose');
                if ($fix === true) {
                    $phpcs_file->fixer->add_newline_before($close_brace);
                }
            } elseif ($found > 0) {
                $error = 'Expected 0 blank lines before closing brace of nested function; %s found';
                $data = [$found];
                $fix = $phpcs_file->add_fixable_error($error, $close_brace, 'SpacingBeforeNestedClose', $data);
                if ($fix === true) {
                    $phpcs_file->fixer->begin_changeset();
                    $change_made = false;
                    for ($i = $prev_content + 1; $i < $close_brace; $i++) {
                        // Try and maintain indentation.
                        if ($tokens[$i]['line'] === $brace_line - 1) {
                            break;
                        }
                        $phpcs_file->fixer->replace_token($i, '');
                        $change_made = true;
                    }
                    // Special case for when the last content contains the newline
                    // token as well, like with a comment.
                    if ($change_made === false) {
                        $phpcs_file->fixer->replace_token($prev_content + 1, '');
                    }
                    $phpcs_file->fixer->end_changeset();
                }
                //end if
            }
            //end if
        } else {
            if ($found !== 1) {
                if ($found < 0) {
                    $found = 0;
                }
                $error = 'Expected 1 blank line before closing function brace; %s found';
                $data = [$found];
                $fix = $phpcs_file->add_fixable_error($error, $close_brace, 'SpacingBeforeClose', $data);
                if ($fix === true) {
                    if ($found > 1) {
                        $phpcs_file->fixer->begin_changeset();
                        for ($i = $prev_content + 1; $i < $close_brace - 1; $i++) {
                            $phpcs_file->fixer->replace_token($i, '');
                        }
                        $phpcs_file->fixer->replace_token($i, $phpcs_file->eol_char);
                        $phpcs_file->fixer->end_changeset();
                    } else if ($tokens[$close_brace - 1]['code'] === T_WHITESPACE) {
                        $phpcs_file->fixer->add_newline_before($close_brace - 1);
                    } else {
                        $phpcs_file->fixer->add_newline_before($close_brace);
                    }
                }
            }
            //end if
        }
        //end if
    }
    //end process()
}
//end class