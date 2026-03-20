<?php

declare (strict_types=1);
/**
 * Verifies that import statements are defined correctly.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\PSR12\Sniffs\Files;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Import_Statement_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_USE];
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
        // Make sure this is not a closure USE group.
        $next = $phpcs_file->find_next(Tokens::$empty_tokens, $stack_ptr + 1, null, true);
        if ($tokens[$next]['code'] === T_OPEN_PARENTHESIS) {
            return;
        }
        if ($phpcs_file->has_condition($stack_ptr, Tokens::$oo_scope_tokens) === true) {
            // This rule only applies to import statements.
            return;
        }
        if ($tokens[$next]['code'] === T_STRING && (strtolower($tokens[$next]['content']) === 'function' || strtolower($tokens[$next]['content']) === 'const')) {
            $next = $phpcs_file->find_next(Tokens::$empty_tokens, $next + 1, null, true);
        }
        if ($tokens[$next]['code'] !== T_NS_SEPARATOR) {
            return;
        }
        $error = 'Import statements must not begin with a leading backslash';
        $fix = $phpcs_file->add_fixable_error($error, $next, 'LeadingSlash');
        if ($fix === true) {
            $phpcs_file->fixer->replace_token($next, '');
        }
    }
    //end process()
}
//end class