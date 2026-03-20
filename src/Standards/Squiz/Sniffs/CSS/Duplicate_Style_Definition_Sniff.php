<?php

declare (strict_types=1);
/**
 * Check for duplicate style definitions in the same class.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\CSS;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Duplicate_Style_Definition_Sniff implements Sniff
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
        // Find the content of each style definition name.
        $style_names = [];
        $next = $stack_ptr;
        $end = $tokens[$stack_ptr]['bracket_closer'];
        do {
            $next = $phpcs_file->find_next([T_STYLE, T_OPEN_CURLY_BRACKET], $next + 1, $end);
            if ($next === false) {
                // Class definition is empty.
                break;
            }
            if ($tokens[$next]['code'] === T_OPEN_CURLY_BRACKET) {
                $next = $tokens[$next]['bracket_closer'];
                continue;
            }
            $name = $tokens[$next]['content'];
            if (isset($style_names[$name]) === true) {
                $first = $style_names[$name];
                $error = 'Duplicate style definition found; first defined on line %s';
                $data = [$tokens[$first]['line']];
                $phpcs_file->add_error($error, $next, 'Found', $data);
            } else {
                $style_names[$name] = $next;
            }
        } while ($next !== false);
    }
    //end process()
}
//end class