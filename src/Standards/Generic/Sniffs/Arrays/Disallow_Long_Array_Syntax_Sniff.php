<?php

declare (strict_types=1);
/**
 * Bans the use of the PHP long array syntax.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Generic\Sniffs\Arrays;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Disallow_Long_Array_Syntax_Sniff implements Sniff
{
    /**
     * Registers the tokens that this sniff wants to listen for.
     *
     * @return int[]
     */
    public function register()
    {
        return [T_ARRAY];
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
        $phpcs_file->record_metric($stack_ptr, 'Short array syntax used', 'no');
        $error = 'Short array syntax must be used to define arrays';
        if (isset($tokens[$stack_ptr]['parenthesis_opener']) === false || isset($tokens[$stack_ptr]['parenthesis_closer']) === false) {
            // Live coding/parse error, just show the error, don't try and fix it.
            $phpcs_file->add_error($error, $stack_ptr, 'Found');
            return;
        }
        $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'Found');
        if ($fix === true) {
            $opener = $tokens[$stack_ptr]['parenthesis_opener'];
            $closer = $tokens[$stack_ptr]['parenthesis_closer'];
            $phpcs_file->fixer->begin_changeset();
            if ($opener === null) {
                $phpcs_file->fixer->replace_token($stack_ptr, '[]');
            } else {
                $phpcs_file->fixer->replace_token($stack_ptr, '');
                $phpcs_file->fixer->replace_token($opener, '[');
                $phpcs_file->fixer->replace_token($closer, ']');
            }
            $phpcs_file->fixer->end_changeset();
        }
    }
    //end process()
}
//end class