<?php

declare (strict_types=1);
/**
 * Ensures this is not assigned to any other var but self.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\My_Source\Sniffs\Objects;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Assign_This_Sniff implements Sniff
{
    /**
     * A list of tokenizers this sniff supports.
     *
     * @var array
     */
    public $supported_tokenizers = ['JS'];
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_THIS];
    }
    //end register()
    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token
     *                                               in the stack passed in $tokens.
     *
     * @return void
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        // Ignore this.something and other uses of "this" that are not
        // direct assignments.
        $next = $phpcs_file->find_next(T_WHITESPACE, $stack_ptr + 1, null, true);
        if ($tokens[$next]['code'] !== T_SEMICOLON) {
            if ($tokens[$next]['line'] === $tokens[$stack_ptr]['line']) {
                return;
            }
        }
        // Something must be assigned to "this".
        $prev = $phpcs_file->find_previous(T_WHITESPACE, $stack_ptr - 1, null, true);
        if ($tokens[$prev]['code'] !== T_EQUAL) {
            return;
        }
        // A variable needs to be assigned to "this".
        $prev = $phpcs_file->find_previous(T_WHITESPACE, $prev - 1, null, true);
        if ($tokens[$prev]['code'] !== T_STRING) {
            return;
        }
        // We can only assign "this" to a var called "self".
        if ($tokens[$prev]['content'] !== 'self' && $tokens[$prev]['content'] !== '_self') {
            $error = 'Keyword "this" can only be assigned to a variable called "self" or "_self"';
            $phpcs_file->add_error($error, $prev, 'NotSelf');
        }
    }
    //end process()
}
//end class