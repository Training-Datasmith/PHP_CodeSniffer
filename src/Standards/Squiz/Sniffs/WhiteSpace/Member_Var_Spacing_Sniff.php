<?php

declare (strict_types=1);
/**
 * Verifies that class members are spaced correctly.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\White_Space;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Abstract_Variable_Sniff;
use Php_code_Sniffer\Util\Tokens;
class Member_Var_Spacing_Sniff extends Abstract_Variable_Sniff
{
    /**
     * The number of blank lines between member vars.
     *
     * @var integer
     */
    public $spacing = 1;
    /**
     * The number of blank lines before the first member var.
     *
     * @var integer
     */
    public $spacing_before_first = 1;
    /**
     * Processes the function tokens within the class.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file where this token was found.
     * @param int                         $stackPtr  The position where the token was found.
     *
     * @return void|int Optionally returns a stack pointer. The sniff will not be
     *                  called again on the current file until the returned stack
     *                  pointer is reached.
     */
    protected function process_member_var(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        $valid_prefixes = Tokens::$method_prefixes;
        $valid_prefixes[] = T_VAR;
        $start_of_statement = $phpcs_file->find_previous($valid_prefixes, $stack_ptr - 1, null, false, null, true);
        if ($start_of_statement === false) {
            return;
        }
        $end_of_statement = $phpcs_file->find_next(T_SEMICOLON, $stack_ptr + 1, null, false, null, true);
        $ignore = $valid_prefixes;
        $ignore[T_WHITESPACE] = T_WHITESPACE;
        $start = $start_of_statement;
        for ($prev = $start_of_statement - 1; $prev >= 0; $prev--) {
            if (isset($ignore[$tokens[$prev]['code']]) === true) {
                continue;
            }
            if ($tokens[$prev]['code'] === T_ATTRIBUTE_END && isset($tokens[$prev]['attribute_opener']) === true) {
                $prev = $tokens[$prev]['attribute_opener'];
                $start = $prev;
                continue;
            }
            break;
        }
        if (isset(Tokens::$comment_tokens[$tokens[$prev]['code']]) === true) {
            // Assume the comment belongs to the member var if it is on a line by itself.
            $prev_content = $phpcs_file->find_previous(Tokens::$empty_tokens, $prev - 1, null, true);
            if ($tokens[$prev_content]['line'] !== $tokens[$prev]['line']) {
                // Check the spacing, but then skip it.
                $found_lines = $tokens[$start_of_statement]['line'] - $tokens[$prev]['line'] - 1;
                if ($found_lines > 0) {
                    for ($i = $prev + 1; $i < $start_of_statement; $i++) {
                        if ($tokens[$i]['column'] !== 1) {
                            continue;
                        }
                        if ($tokens[$i]['code'] === T_WHITESPACE && $tokens[$i]['line'] !== $tokens[$i + 1]['line']) {
                            $error = 'Expected 0 blank lines after member var comment; %s found';
                            $data = [$found_lines];
                            $fix = $phpcs_file->add_fixable_error($error, $prev, 'AfterComment', $data);
                            if ($fix === true) {
                                $phpcs_file->fixer->begin_changeset();
                                // Inline comments have the newline included in the content but
                                // docblocks do not.
                                if ($tokens[$prev]['code'] === T_COMMENT) {
                                    $phpcs_file->fixer->replace_token($prev, rtrim($tokens[$prev]['content']));
                                }
                                for ($i = $prev + 1; $i <= $start_of_statement; $i++) {
                                    if ($tokens[$i]['line'] === $tokens[$start_of_statement]['line']) {
                                        break;
                                    }
                                    // Remove the newline after the docblock, and any entirely
                                    // empty lines before the member var.
                                    if ($tokens[$i]['code'] === T_WHITESPACE && $tokens[$i]['line'] === $tokens[$prev]['line'] || $tokens[$i]['column'] === 1 && $tokens[$i]['line'] !== $tokens[$i + 1]['line']) {
                                        $phpcs_file->fixer->replace_token($i, '');
                                    }
                                }
                                $phpcs_file->fixer->add_newline($prev);
                                $phpcs_file->fixer->end_changeset();
                            }
                            //end if
                            break;
                        }
                        //end if
                    }
                    //end for
                }
                //end if
                $start = $prev;
            }
            //end if
        }
        //end if
        // There needs to be n blank lines before the var, not counting comments.
        if ($start === $start_of_statement) {
            // No comment found.
            $first = $phpcs_file->find_first_on_line(Tokens::$empty_tokens, $start, true);
            if ($first === false) {
                $first = $start;
            }
        } elseif ($tokens[$start]['code'] === T_DOC_COMMENT_CLOSE_TAG) {
            $first = $tokens[$start]['comment_opener'];
        } else {
            $first = $phpcs_file->find_previous(Tokens::$empty_tokens, $start - 1, null, true);
            $first = $phpcs_file->find_next(array_merge(Tokens::$comment_tokens, [T_ATTRIBUTE]), $first + 1);
        }
        // Determine if this is the first member var.
        $prev = $phpcs_file->find_previous(T_WHITESPACE, $first - 1, null, true);
        if ($tokens[$prev]['code'] === T_CLOSE_CURLY_BRACKET && isset($tokens[$prev]['scope_condition']) === true && $tokens[$tokens[$prev]['scope_condition']]['code'] === T_FUNCTION) {
            return;
        }
        if ($tokens[$prev]['code'] === T_OPEN_CURLY_BRACKET && isset(Tokens::$oo_scope_tokens[$tokens[$tokens[$prev]['scope_condition']]['code']]) === true) {
            $error_msg = 'Expected %s blank line(s) before first member var; %s found';
            $error_code = 'FirstIncorrect';
            $spacing = (int) $this->spacing_before_first;
        } else {
            $error_msg = 'Expected %s blank line(s) before member var; %s found';
            $error_code = 'Incorrect';
            $spacing = (int) $this->spacing;
        }
        $found_lines = $tokens[$first]['line'] - $tokens[$prev]['line'] - 1;
        if ($error_code === 'FirstIncorrect') {
            $phpcs_file->record_metric($stack_ptr, 'Member var spacing before first', $found_lines);
        } else {
            $phpcs_file->record_metric($stack_ptr, 'Member var spacing before', $found_lines);
        }
        if ($found_lines === $spacing) {
            if ($end_of_statement !== false) {
                return $end_of_statement;
            }
            return;
        }
        $data = [$spacing, $found_lines];
        $fix = $phpcs_file->add_fixable_error($error_msg, $start_of_statement, $error_code, $data);
        if ($fix === true) {
            $phpcs_file->fixer->begin_changeset();
            for ($i = $prev + 1; $i < $first; $i++) {
                if ($tokens[$i]['line'] === $tokens[$prev]['line']) {
                    continue;
                }
                if ($tokens[$i]['line'] === $tokens[$first]['line']) {
                    for ($x = 1; $x <= $spacing; $x++) {
                        $phpcs_file->fixer->add_newline_before($i);
                    }
                    break;
                }
                $phpcs_file->fixer->replace_token($i, '');
            }
            $phpcs_file->fixer->end_changeset();
        }
        //end if
        if ($end_of_statement !== false) {
            return $end_of_statement;
        }
    }
    //end processMemberVar()
    /**
     * Processes normal variables.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file where this token was found.
     * @param int                         $stackPtr  The position where the token was found.
     *
     * @return void
     */
    protected function process_variable(File $phpcs_file, $stack_ptr)
    {
        /*
            We don't care about normal variables.
        */
    }
    //end processVariable()
    /**
     * Processes variables in double quoted strings.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file where this token was found.
     * @param int                         $stackPtr  The position where the token was found.
     *
     * @return void
     */
    protected function process_variable_in_string(File $phpcs_file, $stack_ptr)
    {
        /*
            We don't care about normal variables.
        */
    }
    //end processVariableInString()
}
//end class