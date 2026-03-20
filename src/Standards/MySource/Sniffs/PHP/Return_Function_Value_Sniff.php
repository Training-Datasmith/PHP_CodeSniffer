<?php

declare (strict_types=1);
/**
 * Warns when function values are returned directly.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\My_Source\Sniffs\PHP;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Return_Function_Value_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_RETURN];
    }
    //end register()
    /**
     * Processes this sniff, when one of its tokens is encountered.
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
        $function_name = $phpcs_file->find_next(T_STRING, $stack_ptr + 1, null, false, null, true);
        while ($function_name !== false) {
            // Check if this is really a function.
            $bracket = $phpcs_file->find_next(T_WHITESPACE, $function_name + 1, null, true);
            if ($tokens[$bracket]['code'] !== T_OPEN_PARENTHESIS) {
                // Not a function call.
                $function_name = $phpcs_file->find_next(T_STRING, $function_name + 1, null, false, null, true);
                continue;
            }
            $error = 'The result of a function call should be assigned to a variable before being returned';
            $phpcs_file->add_warning($error, $stack_ptr, 'NotAssigned');
            break;
        }
    }
    //end process()
}
//end class