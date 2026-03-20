<?php

declare (strict_types=1);
/**
 * Ensure cast statements don't contain whitespace.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\White_Space;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Cast_Spacing_Sniff implements Sniff
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
     * @param int                         $stackPtr  The position of the current token in the
     *                                               stack passed in $tokens.
     *
     * @return void
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        $content = $tokens[$stack_ptr]['content'];
        $expected = str_replace(' ', '', $content);
        $expected = str_replace("\t", '', $expected);
        if ($content !== $expected) {
            $error = 'Cast statements must not contain whitespace; expected "%s" but found "%s"';
            $data = [$expected, $content];
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'ContainsWhiteSpace', $data);
            if ($fix === true) {
                $phpcs_file->fixer->replace_token($stack_ptr, $expected);
            }
        }
    }
    //end process()
}
//end class