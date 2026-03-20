<?php

declare (strict_types=1);
/**
 * Ensures that functions within functions are never used.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\PHP;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Inner_Functions_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_FUNCTION];
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
        if (isset($tokens[$stack_ptr]['conditions']) === false) {
            return;
        }
        $conditions = $tokens[$stack_ptr]['conditions'];
        $reversed_conditions = array_reverse($conditions, true);
        $outer_func_token = null;
        foreach ($reversed_conditions as $cond_token => $condition) {
            if ($condition === T_FUNCTION || $condition === T_CLOSURE) {
                $outer_func_token = $cond_token;
                break;
            }
            if (\array_key_exists($condition, Tokens::$oo_scope_tokens) === true) {
                // Ignore methods in OOP structures defined within functions.
                return;
            }
        }
        if ($outer_func_token === null) {
            // Not a nested function.
            return;
        }
        $error = 'The use of inner functions is forbidden';
        $phpcs_file->add_error($error, $stack_ptr, 'NotAllowed');
    }
    //end process()
}
//end class