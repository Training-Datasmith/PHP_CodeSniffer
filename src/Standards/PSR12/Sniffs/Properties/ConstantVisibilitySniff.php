<?php

declare (strict_types=1);
/**
 * Verifies that all class constants have their visibility set.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2019 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\PSR12\Sniffs\Properties;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Constant_Visibility_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_CONST];
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
        // Make sure this is a class constant.
        if ($phpcs_file->has_condition($stack_ptr, Tokens::$oo_scope_tokens) === false) {
            return;
        }
        $ignore = Tokens::$empty_tokens;
        $ignore[] = T_FINAL;
        $prev = $phpcs_file->find_previous($ignore, $stack_ptr - 1, null, true);
        if (isset(Tokens::$scope_modifiers[$tokens[$prev]['code']]) === true) {
            return;
        }
        $error = 'Visibility must be declared on all constants if your project supports PHP 7.1 or later';
        $phpcs_file->add_warning($error, $stack_ptr, 'NotFound');
    }
    //end process()
}
//end class