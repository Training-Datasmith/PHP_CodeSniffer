<?php

declare (strict_types=1);
/**
 * Verifies that closing braces are the last content on a line.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2019 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\PSR12\Sniffs\Classes;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Closing_Brace_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM, T_FUNCTION];
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
        if (isset($tokens[$stack_ptr]['scope_closer']) === false) {
            return;
        }
        $closer = $tokens[$stack_ptr]['scope_closer'];
        $next = $phpcs_file->find_next(T_WHITESPACE, $closer + 1, null, true);
        if ($next === false || $tokens[$next]['line'] !== $tokens[$closer]['line']) {
            return;
        }
        $error = 'Closing brace must not be followed by any comment or statement on the same line';
        $phpcs_file->add_error($error, $closer, 'StatementAfter');
    }
    //end process()
}
//end class