<?php

declare (strict_types=1);
/**
 * Ensure there is a single space after scope keywords.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\White_Space;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Scope_Keyword_Spacing_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        $register = Tokens::$scope_modifiers;
        $register[] = T_STATIC;
        $register[] = T_READONLY;
        return $register;
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
        if (isset($tokens[$stack_ptr + 1]) === false) {
            return;
        }
        $prev_token = $phpcs_file->find_previous(Tokens::$empty_tokens, $stack_ptr - 1, null, true);
        $next_token = $phpcs_file->find_next(Tokens::$empty_tokens, $stack_ptr + 1, null, true);
        if ($tokens[$stack_ptr]['code'] === T_STATIC) {
            if ($next_token === false || $tokens[$next_token]['code'] === T_DOUBLE_COLON || $tokens[$prev_token]['code'] === T_NEW) {
                // Late static binding, e.g., static:: OR new static() usage or live coding.
                return;
            }
            if ($prev_token !== false && $tokens[$prev_token]['code'] === T_TYPE_UNION) {
                // Not a scope keyword, but a union return type.
                return;
            }
            if ($prev_token !== false && $tokens[$prev_token]['code'] === T_NULLABLE) {
                // Not a scope keyword, but a return type.
                return;
            }
            if ($prev_token !== false && $tokens[$prev_token]['code'] === T_COLON) {
                $prev_prev_token = $phpcs_file->find_previous(Tokens::$empty_tokens, $prev_token - 1, null, true);
                if ($prev_prev_token !== false && $tokens[$prev_prev_token]['code'] === T_CLOSE_PARENTHESIS) {
                    // Not a scope keyword, but a return type.
                    return;
                }
            }
        }
        //end if
        if ($tokens[$prev_token]['code'] === T_AS) {
            // Trait visibility change, e.g., "use HelloWorld { sayHello as private; }".
            return;
        }
        $is_in_function_declaration = false;
        if (empty($tokens[$stack_ptr]['nested_parenthesis']) === false) {
            // Check if this is PHP 8.0 constructor property promotion.
            // In that case, we can't have multi-property definitions.
            $nested_parens = $tokens[$stack_ptr]['nested_parenthesis'];
            $last_close_parens = end($nested_parens);
            if (isset($tokens[$last_close_parens]['parenthesis_owner']) === true && $tokens[$tokens[$last_close_parens]['parenthesis_owner']]['code'] === T_FUNCTION) {
                $is_in_function_declaration = true;
            }
        }
        if ($next_token !== false && $tokens[$next_token]['code'] === T_VARIABLE && $is_in_function_declaration === false) {
            $end_of_statement = $phpcs_file->find_next(T_SEMICOLON, $next_token + 1);
            if ($end_of_statement === false) {
                // Live coding.
                return;
            }
            $multi_property = $phpcs_file->find_next(T_VARIABLE, $next_token + 1, $end_of_statement);
            if ($multi_property !== false && $tokens[$stack_ptr]['line'] !== $tokens[$next_token]['line'] && $tokens[$next_token]['line'] !== $tokens[$end_of_statement]['line']) {
                // Allow for multiple properties definitions to each be on their own line.
                return;
            }
        }
        if ($tokens[$stack_ptr + 1]['code'] !== T_WHITESPACE) {
            $spacing = 0;
        } else if ($tokens[$stack_ptr + 2]['line'] !== $tokens[$stack_ptr]['line']) {
            $spacing = 'newline';
        } else {
            $spacing = $tokens[$stack_ptr + 1]['length'];
        }
        if ($spacing !== 1) {
            $error = 'Scope keyword "%s" must be followed by a single space; found %s';
            $data = [$tokens[$stack_ptr]['content'], $spacing];
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'Incorrect', $data);
            if ($fix === true) {
                if ($spacing === 0) {
                    $phpcs_file->fixer->add_content($stack_ptr, ' ');
                } else {
                    $phpcs_file->fixer->begin_changeset();
                    for ($i = $stack_ptr + 2; $i < $phpcs_file->num_tokens; $i++) {
                        if (isset($tokens[$i]) === false || $tokens[$i]['code'] !== T_WHITESPACE) {
                            break;
                        }
                        $phpcs_file->fixer->replace_token($i, '');
                    }
                    $phpcs_file->fixer->replace_token($stack_ptr + 1, ' ');
                    $phpcs_file->fixer->end_changeset();
                }
            }
            //end if
        }
        //end if
    }
    //end process()
}
//end class