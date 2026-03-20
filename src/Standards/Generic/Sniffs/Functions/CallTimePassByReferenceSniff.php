<?php

declare (strict_types=1);
/**
 * Ensures that variables are not passed by reference when calling a function.
 *
 * @author    Florian Grandel <jerico.dev@gmail.com>
 * @copyright 2009-2014 Florian Grandel
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Generic\Sniffs\Functions;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Call_Time_Pass_By_Reference_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_STRING, T_VARIABLE];
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
        $find_tokens = Tokens::$empty_tokens;
        $find_tokens[] = T_BITWISE_AND;
        $prev = $phpcs_file->find_previous($find_tokens, $stack_ptr - 1, null, true);
        // Skip tokens that are the names of functions or classes
        // within their definitions. For example: function myFunction...
        // "myFunction" is T_STRING but we should skip because it is not a
        // function or method *call*.
        $prev_code = $tokens[$prev]['code'];
        if ($prev_code === T_FUNCTION || $prev_code === T_CLASS) {
            return;
        }
        // If the next non-whitespace token after the function or method call
        // is not an opening parenthesis then it cant really be a *call*.
        $function_name = $stack_ptr;
        $open_bracket = $phpcs_file->find_next(Tokens::$empty_tokens, $function_name + 1, null, true);
        if ($tokens[$open_bracket]['code'] !== T_OPEN_PARENTHESIS) {
            return;
        }
        if (isset($tokens[$open_bracket]['parenthesis_closer']) === false) {
            return;
        }
        $close_bracket = $tokens[$open_bracket]['parenthesis_closer'];
        $next_separator = $open_bracket;
        $find = [T_VARIABLE, T_OPEN_SHORT_ARRAY];
        while (($next_separator = $phpcs_file->find_next($find, $next_separator + 1, $close_bracket)) !== false) {
            if (isset($tokens[$next_separator]['nested_parenthesis']) === false) {
                continue;
            }
            if ($tokens[$next_separator]['code'] === T_OPEN_SHORT_ARRAY) {
                $next_separator = $tokens[$next_separator]['bracket_closer'];
                continue;
            }
            // Make sure the variable belongs directly to this function call
            // and is not inside a nested function call or array.
            $brackets = $tokens[$next_separator]['nested_parenthesis'];
            $last_bracket = array_pop($brackets);
            if ($last_bracket !== $close_bracket) {
                continue;
            }
            $token_before = $phpcs_file->find_previous(Tokens::$empty_tokens, $next_separator - 1, null, true);
            if ($tokens[$token_before]['code'] === T_BITWISE_AND) {
                if ($phpcs_file->is_reference($token_before) === false) {
                    continue;
                }
                // We also want to ignore references used in assignment
                // operations passed as function arguments, but isReference()
                // sees them as valid references (which they are).
                $token_before = $phpcs_file->find_previous(Tokens::$empty_tokens, $token_before - 1, null, true);
                if (isset(Tokens::$assignment_tokens[$tokens[$token_before]['code']]) === true) {
                    continue;
                }
                // T_BITWISE_AND represents a pass-by-reference.
                $error = 'Call-time pass-by-reference calls are prohibited';
                $phpcs_file->add_error($error, $token_before, 'NotAllowed');
            }
            //end if
        }
        //end while
    }
    //end process()
}
//end class