<?php

declare (strict_types=1);
/**
 * Ensure that all style definitions have a colon.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\CSS;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Missing_Colon_Sniff implements Sniff
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
        if (isset($tokens[$stack_ptr]['bracket_closer']) === false) {
            // Syntax error or live coding, bow out.
            return;
        }
        $last_line = $tokens[$stack_ptr]['line'];
        $end = $tokens[$stack_ptr]['bracket_closer'];
        // Do not check nested style definitions as, for example, in @media style rules.
        $nested = $phpcs_file->find_next(T_OPEN_CURLY_BRACKET, $stack_ptr + 1, $end);
        if ($nested !== false) {
            return;
        }
        $found_colon = false;
        $found_string = false;
        for ($i = $stack_ptr + 1; $i <= $end; $i++) {
            if ($tokens[$i]['line'] !== $last_line) {
                // We changed lines.
                if ($found_colon === false && $found_string !== false) {
                    // We didn't find a colon on the previous line.
                    $error = 'No style definition found on line; check for missing colon';
                    $phpcs_file->add_error($error, $found_string, 'Found');
                }
                $found_colon = false;
                $found_string = false;
                $last_line = $tokens[$i]['line'];
            }
            if ($tokens[$i]['code'] === T_STRING) {
                $found_string = $i;
            } elseif ($tokens[$i]['code'] === T_COLON) {
                $found_colon = $i;
            }
        }
        //end for
    }
    //end process()
}
//end class