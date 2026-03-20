<?php

declare (strict_types=1);
/**
 * Ensure that each style definition is on a line by itself.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\CSS;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Disallow_Multiple_Style_Definitions_Sniff implements Sniff
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
        return [T_STYLE];
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
        $next = $phpcs_file->find_next(T_STYLE, $stack_ptr + 1);
        if ($next === false) {
            return;
        }
        if ($tokens[$next]['content'] === 'progid') {
            // Special case for IE filters.
            return;
        }
        if ($tokens[$next]['line'] === $tokens[$stack_ptr]['line']) {
            $error = 'Each style definition must be on a line by itself';
            $fix = $phpcs_file->add_fixable_error($error, $next, 'Found');
            if ($fix === true) {
                $phpcs_file->fixer->add_newline_before($next);
            }
        }
    }
    //end process()
}
//end class