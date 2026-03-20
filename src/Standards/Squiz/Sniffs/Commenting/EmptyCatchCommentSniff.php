<?php

declare (strict_types=1);
/**
 * Checks for empty catch clause without a comment.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\Commenting;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Empty_Catch_Comment_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_CATCH];
    }
    //end register()
    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile All the tokens found in the document.
     * @param int                         $stackPtr  The position of the current token in the
     *                                               stack passed in $tokens.
     *
     * @return void
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        $scope_start = $tokens[$stack_ptr]['scope_opener'];
        $first_content = $phpcs_file->find_next(T_WHITESPACE, $scope_start + 1, $tokens[$stack_ptr]['scope_closer'], true);
        if ($first_content === false) {
            $error = 'Empty CATCH statement must have a comment to explain why the exception is not handled';
            $phpcs_file->add_error($error, $scope_start, 'Missing');
        }
    }
    //end process()
}
//end class