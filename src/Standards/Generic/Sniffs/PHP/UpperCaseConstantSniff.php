<?php

declare (strict_types=1);
/**
 * Checks that all uses of TRUE, FALSE and NULL are uppercase.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Generic\Sniffs\PHP;

use Php_code_Sniffer\Files\File;
class Upper_Case_Constant_Sniff extends Lower_Case_Constant_Sniff
{
    /**
     * Processes a non-type declaration constant.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token in the
     *                                               stack passed in $tokens.
     *
     * @return void
     */
    protected function process_constant(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        $keyword = $tokens[$stack_ptr]['content'];
        $expected = strtoupper($keyword);
        if ($keyword !== $expected) {
            if ($keyword === strtolower($keyword)) {
                $phpcs_file->record_metric($stack_ptr, 'PHP constant case', 'lower');
            } else {
                $phpcs_file->record_metric($stack_ptr, 'PHP constant case', 'mixed');
            }
            $error = 'TRUE, FALSE and NULL must be uppercase; expected "%s" but found "%s"';
            $data = [$expected, $keyword];
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'Found', $data);
            if ($fix === true) {
                $phpcs_file->fixer->replace_token($stack_ptr, $expected);
            }
        } else {
            $phpcs_file->record_metric($stack_ptr, 'PHP constant case', 'upper');
        }
    }
    //end processConstant()
}
//end class