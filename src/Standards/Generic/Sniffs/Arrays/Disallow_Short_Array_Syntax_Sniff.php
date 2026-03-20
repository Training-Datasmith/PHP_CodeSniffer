<?php

declare (strict_types=1);
/**
 * Bans the use of the PHP short array syntax.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Generic\Sniffs\Arrays;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Disallow_Short_Array_Syntax_Sniff implements Sniff
{
    /**
     * Registers the tokens that this sniff wants to listen for.
     *
     * @return int[]
     */
    public function register()
    {
        return [T_OPEN_SHORT_ARRAY];
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
        $phpcs_file->record_metric($stack_ptr, 'Short array syntax used', 'yes');
        $error = 'Short array syntax is not allowed';
        $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'Found');
        if ($fix === true) {
            $tokens = $phpcs_file->get_tokens();
            $opener = $tokens[$stack_ptr]['bracket_opener'];
            $closer = $tokens[$stack_ptr]['bracket_closer'];
            $phpcs_file->fixer->begin_changeset();
            $phpcs_file->fixer->replace_token($opener, 'array(');
            $phpcs_file->fixer->replace_token($closer, ')');
            $phpcs_file->fixer->end_changeset();
        }
    }
    //end process()
}
//end class