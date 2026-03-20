<?php

declare (strict_types=1);
/**
 * Ensures that the value of a comparison is not assigned to a variable.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\PHP;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Disallow_Comparison_Assignment_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_EQUAL];
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
        // Ignore default value assignments in function definitions.
        $function = $phpcs_file->find_previous(T_FUNCTION, $stack_ptr - 1, null, false, null, true);
        if ($function !== false) {
            $opener = $tokens[$function]['parenthesis_opener'];
            $closer = $tokens[$function]['parenthesis_closer'];
            if ($opener < $stack_ptr && $closer > $stack_ptr) {
                return;
            }
        }
        // Ignore values in array definitions or match structures.
        $next_non_empty = $phpcs_file->find_next(Tokens::$empty_tokens, $stack_ptr + 1, null, true);
        if ($next_non_empty !== false && ($tokens[$next_non_empty]['code'] === T_ARRAY || $tokens[$next_non_empty]['code'] === T_MATCH)) {
            return;
        }
        // Ignore function calls.
        $ignore = [T_NULLSAFE_OBJECT_OPERATOR, T_OBJECT_OPERATOR, T_STRING, T_VARIABLE, T_WHITESPACE];
        $next = $phpcs_file->find_next($ignore, $stack_ptr + 1, null, true);
        if ($tokens[$next]['code'] === T_CLOSURE || $tokens[$next]['code'] === T_OPEN_PARENTHESIS && $tokens[$next - 1]['code'] === T_STRING) {
            // Code will look like: $var = myFunction(
            // and will be ignored.
            return;
        }
        $end_statement = $phpcs_file->find_end_of_statement($stack_ptr);
        for ($i = $stack_ptr + 1; $i < $end_statement; $i++) {
            if (isset(Tokens::$comparison_tokens[$tokens[$i]['code']]) === true && $tokens[$i]['code'] !== T_COALESCE || $tokens[$i]['code'] === T_INLINE_THEN) {
                $error = 'The value of a comparison must not be assigned to a variable';
                $phpcs_file->add_error($error, $stack_ptr, 'AssignedComparison');
                break;
            }
            if (isset(Tokens::$boolean_operators[$tokens[$i]['code']]) === true || $tokens[$i]['code'] === T_BOOLEAN_NOT) {
                $error = 'The value of a boolean operation must not be assigned to a variable';
                $phpcs_file->add_error($error, $stack_ptr, 'AssignedBool');
                break;
            }
        }
    }
    //end process()
}
//end class