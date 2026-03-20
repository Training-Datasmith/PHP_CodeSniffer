<?php

declare (strict_types=1);
/**
 * Checks that calls to methods and functions are spaced correctly.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Generic\Sniffs\Functions;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Function_Call_Argument_Spacing_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_STRING, T_ISSET, T_UNSET, T_SELF, T_STATIC, T_PARENT, T_VARIABLE, T_CLOSE_CURLY_BRACKET, T_CLOSE_PARENTHESIS];
    }
    //end register()
    /**
     * Processes this test, when one of its tokens is encountered.
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
        // Skip tokens that are the names of functions or classes
        // within their definitions. For example:
        // function myFunction...
        // "myFunction" is T_STRING but we should skip because it is not a
        // function or method *call*.
        $function_name = $stack_ptr;
        $ignore_tokens = Tokens::$empty_tokens;
        $ignore_tokens[] = T_BITWISE_AND;
        $function_keyword = $phpcs_file->find_previous($ignore_tokens, $stack_ptr - 1, null, true);
        if ($tokens[$function_keyword]['code'] === T_FUNCTION || $tokens[$function_keyword]['code'] === T_CLASS) {
            return;
        }
        if ($tokens[$stack_ptr]['code'] === T_CLOSE_CURLY_BRACKET && isset($tokens[$stack_ptr]['scope_condition']) === true) {
            // Not a function call.
            return;
        }
        // If the next non-whitespace token after the function or method call
        // is not an opening parenthesis then it can't really be a *call*.
        $open_bracket = $phpcs_file->find_next(Tokens::$empty_tokens, $function_name + 1, null, true);
        if ($tokens[$open_bracket]['code'] !== T_OPEN_PARENTHESIS) {
            return;
        }
        if (isset($tokens[$open_bracket]['parenthesis_closer']) === false) {
            return;
        }
        $this->check_spacing($phpcs_file, $stack_ptr, $open_bracket);
    }
    //end process()
    /**
     * Checks the spacing around commas.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile   The file being scanned.
     * @param int                         $stackPtr    The position of the current token in the
     *                                                 stack passed in $tokens.
     * @param int                         $openBracket The position of the opening bracket
     *                                                 in the stack passed in $tokens.
     *
     * @return void
     */
    public function check_spacing(File $phpcs_file, $stack_ptr, $open_bracket)
    {
        $tokens = $phpcs_file->get_tokens();
        $close_bracket = $tokens[$open_bracket]['parenthesis_closer'];
        $next_separator = $open_bracket;
        $find = [T_COMMA, T_CLOSURE, T_ANON_CLASS, T_OPEN_SHORT_ARRAY];
        while (($next_separator = $phpcs_file->find_next($find, $next_separator + 1, $close_bracket)) !== false) {
            if ($tokens[$next_separator]['code'] === T_CLOSURE || $tokens[$next_separator]['code'] === T_ANON_CLASS) {
                // Skip closures.
                $next_separator = $tokens[$next_separator]['scope_closer'];
                continue;
            }
            if ($tokens[$next_separator]['code'] === T_OPEN_SHORT_ARRAY) {
                // Skips arrays using short notation.
                $next_separator = $tokens[$next_separator]['bracket_closer'];
                continue;
            }
            // Make sure the comma or variable belongs directly to this function call,
            // and is not inside a nested function call or array.
            $brackets = $tokens[$next_separator]['nested_parenthesis'];
            $last_bracket = array_pop($brackets);
            if ($last_bracket !== $close_bracket) {
                continue;
            }
            if ($tokens[$next_separator]['code'] === T_COMMA) {
                if ($tokens[$next_separator - 1]['code'] === T_WHITESPACE) {
                    $prev = $phpcs_file->find_previous(Tokens::$empty_tokens, $next_separator - 2, null, true);
                    if (isset(Tokens::$heredoc_tokens[$tokens[$prev]['code']]) === false) {
                        $error = 'Space found before comma in argument list';
                        $fix = $phpcs_file->add_fixable_error($error, $next_separator, 'SpaceBeforeComma');
                        if ($fix === true) {
                            $phpcs_file->fixer->begin_changeset();
                            if ($tokens[$prev]['line'] !== $tokens[$next_separator]['line']) {
                                $phpcs_file->fixer->add_content($prev, ',');
                                $phpcs_file->fixer->replace_token($next_separator, '');
                            } else {
                                $phpcs_file->fixer->replace_token($next_separator - 1, '');
                            }
                            $phpcs_file->fixer->end_changeset();
                        }
                    }
                    //end if
                }
                //end if
                if ($tokens[$next_separator + 1]['code'] !== T_WHITESPACE) {
                    // Ignore trailing comma's after last argument as that's outside the scope of this sniff.
                    if ($next_separator + 1 !== $close_bracket) {
                        $error = 'No space found after comma in argument list';
                        $fix = $phpcs_file->add_fixable_error($error, $next_separator, 'NoSpaceAfterComma');
                        if ($fix === true) {
                            $phpcs_file->fixer->add_content($next_separator, ' ');
                        }
                    }
                } else {
                    // If there is a newline in the space, then they must be formatting
                    // each argument on a newline, which is valid, so ignore it.
                    $next = $phpcs_file->find_next(Tokens::$empty_tokens, $next_separator + 1, null, true);
                    if ($tokens[$next]['line'] === $tokens[$next_separator]['line']) {
                        $space = $tokens[$next_separator + 1]['length'];
                        if ($space > 1) {
                            $error = 'Expected 1 space after comma in argument list; %s found';
                            $data = [$space];
                            $fix = $phpcs_file->add_fixable_error($error, $next_separator, 'TooMuchSpaceAfterComma', $data);
                            if ($fix === true) {
                                $phpcs_file->fixer->replace_token($next_separator + 1, ' ');
                            }
                        }
                    }
                }
                //end if
            }
            //end if
        }
        //end while
    }
    //end checkSpacing()
}
//end class