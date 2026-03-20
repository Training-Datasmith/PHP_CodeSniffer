<?php

declare (strict_types=1);
/**
 * Ensures namespaces are declared correctly.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\PSR2\Sniffs\Namespaces;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Namespace_Declaration_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_NAMESPACE];
    }
    //end register()
    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token in
     *                                               the stack passed in $tokens.
     *
     * @return void
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        $next_non_empty = $phpcs_file->find_next(Tokens::$empty_tokens, $stack_ptr + 1, null, true);
        if ($tokens[$next_non_empty]['code'] === T_NS_SEPARATOR) {
            // Namespace keyword as operator. Not a declaration.
            return;
        }
        $end = $phpcs_file->find_end_of_statement($stack_ptr);
        for ($i = $end + 1; $i < $phpcs_file->num_tokens - 1; $i++) {
            if ($tokens[$i]['line'] === $tokens[$end]['line']) {
                continue;
            }
            break;
        }
        // The $i var now points to the first token on the line after the
        // namespace declaration, which must be a blank line.
        $next = $phpcs_file->find_next(T_WHITESPACE, $i, $phpcs_file->num_tokens, true);
        if ($next === false) {
            return;
        }
        $diff = $tokens[$next]['line'] - $tokens[$i]['line'];
        if ($diff === 1) {
            return;
        }
        if ($diff < 0) {
            $diff = 0;
        }
        $error = 'There must be one blank line after the namespace declaration';
        $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'BlankLineAfter');
        if ($fix === true) {
            if ($diff === 0) {
                $phpcs_file->fixer->add_newline_before($i);
            } else {
                $phpcs_file->fixer->begin_changeset();
                for ($x = $i; $x < $next; $x++) {
                    if ($tokens[$x]['line'] === $tokens[$next]['line']) {
                        break;
                    }
                    $phpcs_file->fixer->replace_token($x, '');
                }
                $phpcs_file->fixer->add_newline($i);
                $phpcs_file->fixer->end_changeset();
            }
        }
    }
    //end process()
}
//end class