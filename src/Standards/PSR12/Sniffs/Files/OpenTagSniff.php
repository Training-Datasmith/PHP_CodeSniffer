<?php

declare (strict_types=1);
/**
 * Checks that the open tag is defined correctly.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2019 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\PSR12\Sniffs\Files;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Open_Tag_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_OPEN_TAG];
    }
    //end register()
    /**
     * Processes this sniff when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current
     *                                               token in the stack.
     *
     * @return int
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        if ($stack_ptr !== 0) {
            // This rule only applies if the open tag is on the first line of the file.
            return $phpcs_file->num_tokens;
        }
        $next = $phpcs_file->find_next(T_INLINE_HTML, 0);
        if ($next !== false) {
            // This rule only applies to PHP-only files.
            return $phpcs_file->num_tokens;
        }
        $tokens = $phpcs_file->get_tokens();
        $next = $phpcs_file->find_next(T_WHITESPACE, $stack_ptr + 1, null, true);
        if ($next === false) {
            // Empty file.
            return;
        }
        if ($tokens[$next]['line'] === $tokens[$stack_ptr]['line']) {
            $error = 'Opening PHP tag must be on a line by itself';
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'NotAlone');
            if ($fix === true) {
                $phpcs_file->fixer->add_newline($stack_ptr);
            }
        }
        return $phpcs_file->num_tokens;
    }
    //end process()
}
//end class