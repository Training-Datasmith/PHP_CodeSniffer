<?php

declare (strict_types=1);
/**
 * Tests for functions outside of classes.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\Functions;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Global_Function_Sniff implements Sniff
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
        if (empty($tokens[$stack_ptr]['conditions']) === true) {
            $function_name = $phpcs_file->get_declaration_name($stack_ptr);
            if ($function_name === null) {
                return;
            }
            // Special exception for __autoload as it needs to be global.
            if ($function_name !== '__autoload') {
                $error = 'Consider putting global function "%s" in a static class';
                $data = [$function_name];
                $phpcs_file->add_warning($error, $stack_ptr, 'Found', $data);
            }
        }
    }
    //end process()
}
//end class