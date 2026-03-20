<?php

declare (strict_types=1);
/**
 * Ensures there is a single space before cast tokens.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Generic\Sniffs\Formatting;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Space_Before_Cast_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return Tokens::$cast_tokens;
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
        if ($tokens[$stack_ptr]['column'] === 1) {
            return;
        }
        if ($tokens[$stack_ptr - 1]['code'] !== T_WHITESPACE) {
            $error = 'A cast statement must be preceded by a single space';
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'NoSpace');
            if ($fix === true) {
                $phpcs_file->fixer->add_content_before($stack_ptr, ' ');
            }
            $phpcs_file->record_metric($stack_ptr, 'Spacing before cast statement', 0);
            return;
        }
        $phpcs_file->record_metric($stack_ptr, 'Spacing before cast statement', $tokens[$stack_ptr - 1]['length']);
        if ($tokens[$stack_ptr - 1]['column'] !== 1 && $tokens[$stack_ptr - 1]['length'] !== 1) {
            $error = 'A cast statement must be preceded by a single space';
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'TooMuchSpace');
            if ($fix === true) {
                $phpcs_file->fixer->replace_token($stack_ptr - 1, ' ');
            }
        }
    }
    //end process()
}
//end class