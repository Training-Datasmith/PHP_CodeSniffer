<?php

declare (strict_types=1);
/**
 * Ensures the $_REQUEST superglobal is not used
 *
 * @author    Jeantwan Teuma <jeant.m24@gmail.com>
 * @copyright 2006-2019 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Generic\Sniffs\PHP;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Disallow_Request_Superglobal_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_VARIABLE];
    }
    //end register()
    /**
     * Processes this sniff, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token in the stack
     *                                               passed in $tokens.
     *
     * @return void
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        $var_name = $tokens[$stack_ptr]['content'];
        if ($var_name !== '$_REQUEST') {
            return;
        }
        $error = 'The $_REQUEST superglobal should not be used; use $_GET, $_POST, or $_COOKIE instead';
        $phpcs_file->add_error($error, $stack_ptr, 'Found');
    }
    //end process()
}
//end class