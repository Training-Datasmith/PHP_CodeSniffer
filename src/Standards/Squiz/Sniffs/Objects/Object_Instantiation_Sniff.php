<?php

declare (strict_types=1);
/**
 * Ensures objects are assigned to a variable when instantiated.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\Objects;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Object_Instantiation_Sniff implements Sniff
{
    /**
     * Registers the token types that this sniff wishes to listen to.
     *
     * @return array
     */
    public function register()
    {
        return [T_NEW];
    }
    //end register()
    /**
     * Process the tokens that this sniff is listening for.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file where the token was found.
     * @param int                         $stackPtr  The position in the stack where
     *                                               the token was found.
     *
     * @return void
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        $allowed_tokens = Tokens::$empty_tokens;
        $allowed_tokens[] = T_BITWISE_AND;
        $prev = $phpcs_file->find_previous($allowed_tokens, $stack_ptr - 1, null, true);
        $allowed_tokens = [T_EQUAL => T_EQUAL, T_COALESCE_EQUAL => T_COALESCE_EQUAL, T_DOUBLE_ARROW => T_DOUBLE_ARROW, T_FN_ARROW => T_FN_ARROW, T_MATCH_ARROW => T_MATCH_ARROW, T_THROW => T_THROW, T_RETURN => T_RETURN];
        if (isset($allowed_tokens[$tokens[$prev]['code']]) === true) {
            return;
        }
        $ternary_like_tokens = [T_COALESCE => true, T_INLINE_THEN => true, T_INLINE_ELSE => true];
        // For ternary like tokens, walk a little further back to see if it is preceded by
        // one of the allowed tokens (within the same statement).
        if (isset($ternary_like_tokens[$tokens[$prev]['code']]) === true) {
            $has_allowed_before = $phpcs_file->find_previous($allowed_tokens, $prev - 1, null, false, null, true);
            if ($has_allowed_before !== false) {
                return;
            }
        }
        $error = 'New objects must be assigned to a variable';
        $phpcs_file->add_error($error, $stack_ptr, 'NotAssigned');
    }
    //end process()
}
//end class