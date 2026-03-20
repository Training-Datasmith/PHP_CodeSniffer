<?php

declare (strict_types=1);
/**
 * Ensure colours are defined in upper-case and use shortcuts where possible.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\CSS;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Colour_Definition_Sniff implements Sniff
{
    /**
     * A list of tokenizers this sniff supports.
     *
     * @var array
     */
    public $supported_tokenizers = ['CSS'];
    /**
     * Returns the token types that this sniff is interested in.
     *
     * @return int[]
     */
    public function register()
    {
        return [T_COLOUR];
    }
    //end register()
    /**
     * Processes the tokens that this sniff is interested in.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file where the token was found.
     * @param int                         $stackPtr  The position in the stack where
     *                                               the token was found.
     *
     * @return void
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        $colour = $tokens[$stack_ptr]['content'];
        $expected = strtoupper($colour);
        if ($colour !== $expected) {
            $error = 'CSS colours must be defined in uppercase; expected %s but found %s';
            $data = [$expected, $colour];
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'NotUpper', $data);
            if ($fix === true) {
                $phpcs_file->fixer->replace_token($stack_ptr, $expected);
            }
        }
        // Now check if shorthand can be used.
        if (strlen($colour) !== 7) {
            return;
        }
        if ($colour[1] === $colour[2] && $colour[3] === $colour[4] && $colour[5] === $colour[6]) {
            $expected = '#' . $colour[1] . $colour[3] . $colour[5];
            $error = 'CSS colours must use shorthand if available; expected %s but found %s';
            $data = [$expected, $colour];
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'Shorthand', $data);
            if ($fix === true) {
                $phpcs_file->fixer->replace_token($stack_ptr, $expected);
            }
        }
    }
    //end process()
}
//end class