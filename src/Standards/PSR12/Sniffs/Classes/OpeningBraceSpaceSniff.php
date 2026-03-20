<?php

declare (strict_types=1);
/**
 * Verifies that opening braces are not followed by blank lines.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2019 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\PSR12\Sniffs\Classes;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Opening_Brace_Space_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return Tokens::$oo_scope_tokens;
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
        if (isset($tokens[$stack_ptr]['scope_opener']) === false) {
            return;
        }
        $opener = $tokens[$stack_ptr]['scope_opener'];
        $next = $phpcs_file->find_next(T_WHITESPACE, $opener + 1, null, true);
        if ($next === false || $tokens[$next]['line'] <= $tokens[$opener]['line'] + 1) {
            return;
        }
        $error = 'Opening brace must not be followed by a blank line';
        $fix = $phpcs_file->add_fixable_error($error, $opener, 'Found');
        if ($fix === false) {
            return;
        }
        $phpcs_file->fixer->begin_changeset();
        for ($i = $opener + 1; $i < $next; $i++) {
            if ($tokens[$i]['line'] === $tokens[$opener]['line']) {
                continue;
            }
            if ($tokens[$i]['line'] === $tokens[$next]['line']) {
                break;
            }
            $phpcs_file->fixer->replace_token($i, '');
        }
        $phpcs_file->fixer->end_changeset();
    }
    //end process()
}
//end class