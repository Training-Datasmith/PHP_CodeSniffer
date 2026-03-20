<?php

declare (strict_types=1);
/**
 * Ensures the PHP_SAPI constant is used instead of php_sapi_name().
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Generic\Sniffs\PHP;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Sapi_Usage_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_STRING];
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
        $ignore = [T_DOUBLE_COLON => true, T_OBJECT_OPERATOR => true, T_NULLSAFE_OBJECT_OPERATOR => true, T_FUNCTION => true, T_CONST => true];
        $prev_token = $phpcs_file->find_previous(T_WHITESPACE, $stack_ptr - 1, null, true);
        if (isset($ignore[$tokens[$prev_token]['code']]) === true) {
            // Not a call to a PHP function.
            return;
        }
        $function = strtolower($tokens[$stack_ptr]['content']);
        if ($function === 'php_sapi_name') {
            $error = 'Use the PHP_SAPI constant instead of calling php_sapi_name()';
            $phpcs_file->add_error($error, $stack_ptr, 'FunctionFound');
        }
    }
    //end process()
}
//end class