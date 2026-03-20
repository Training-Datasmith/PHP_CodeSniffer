<?php

declare (strict_types=1);
/**
 * Verifies that the short form of type keywords is used (e.g., int, bool).
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\PSR12\Sniffs\Keywords;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Short_Form_Type_Keywords_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_BOOL_CAST, T_INT_CAST];
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
        $typecast = str_replace(' ', '', $tokens[$stack_ptr]['content']);
        $typecast = str_replace("\t", '', $typecast);
        $typecast = trim($typecast, '()');
        $typecast_lc = strtolower($typecast);
        if ($tokens[$stack_ptr]['code'] === T_BOOL_CAST && $typecast_lc === 'bool' || $tokens[$stack_ptr]['code'] === T_INT_CAST && $typecast_lc === 'int') {
            return;
        }
        $error = 'Short form type keywords must be used. Found: %s';
        $data = [$tokens[$stack_ptr]['content']];
        $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'LongFound', $data);
        if ($fix === true) {
            if ($tokens[$stack_ptr]['code'] === T_BOOL_CAST) {
                $replacement = str_replace($typecast, 'bool', $tokens[$stack_ptr]['content']);
            } else {
                $replacement = str_replace($typecast, 'int', $tokens[$stack_ptr]['content']);
            }
            $phpcs_file->fixer->replace_token($stack_ptr, $replacement);
        }
    }
    //end process()
}
//end class