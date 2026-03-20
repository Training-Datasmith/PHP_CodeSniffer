<?php

declare (strict_types=1);
/**
 * Ensures all control structure keywords are lowercase.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\Control_Structures;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Lowercase_Declaration_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_IF, T_ELSE, T_ELSEIF, T_FOREACH, T_FOR, T_DO, T_SWITCH, T_WHILE, T_TRY, T_CATCH, T_MATCH];
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
        $content = $tokens[$stack_ptr]['content'];
        $content_lc = strtolower($content);
        if ($content !== $content_lc) {
            $error = '%s keyword must be lowercase; expected "%s" but found "%s"';
            $data = [strtoupper($content), $content_lc, $content];
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'FoundUppercase', $data);
            if ($fix === true) {
                $phpcs_file->fixer->replace_token($stack_ptr, $content_lc);
            }
        }
    }
    //end process()
}
//end class