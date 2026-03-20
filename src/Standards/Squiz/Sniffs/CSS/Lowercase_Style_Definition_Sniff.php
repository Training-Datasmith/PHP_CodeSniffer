<?php

declare (strict_types=1);
/**
 * Ensure that all style definitions are in lowercase.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\CSS;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Lowercase_Style_Definition_Sniff implements Sniff
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
        return [T_OPEN_CURLY_BRACKET];
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
        $start = $stack_ptr + 1;
        $end = $tokens[$stack_ptr]['bracket_closer'] - 1;
        $in_style = null;
        for ($i = $start; $i <= $end; $i++) {
            // Skip nested definitions as they are checked individually.
            if ($tokens[$i]['code'] === T_OPEN_CURLY_BRACKET) {
                $i = $tokens[$i]['bracket_closer'];
                continue;
            }
            if ($tokens[$i]['code'] === T_STYLE) {
                $in_style = $tokens[$i]['content'];
            }
            if ($tokens[$i]['code'] === T_SEMICOLON) {
                $in_style = null;
            }
            if ($in_style === 'progid') {
                // Special case for IE filters.
                continue;
            }
            if ($tokens[$i]['code'] === T_STYLE || $in_style !== null && $tokens[$i]['code'] === T_STRING) {
                $expected = strtolower($tokens[$i]['content']);
                if ($expected !== $tokens[$i]['content']) {
                    $error = 'Style definitions must be lowercase; expected %s but found %s';
                    $data = [$expected, $tokens[$i]['content']];
                    $fix = $phpcs_file->add_fixable_error($error, $i, 'FoundUpper', $data);
                    if ($fix === true) {
                        $phpcs_file->fixer->replace_token($i, $expected);
                    }
                }
            }
        }
        //end for
    }
    //end process()
}
//end class