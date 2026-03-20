<?php

declare (strict_types=1);
/**
 * Ensures that object indexes are written in dot notation.
 *
 * @author    Sertan Danis <sdanis@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\Objects;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Disallow_Object_String_Index_Sniff implements Sniff
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
        return [T_OPEN_SQUARE_BRACKET];
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
        // Check if the next non whitespace token is a string.
        $index = $phpcs_file->find_next(T_WHITESPACE, $stack_ptr + 1, null, true);
        if ($tokens[$index]['code'] !== T_CONSTANT_ENCAPSED_STRING) {
            return;
        }
        // Make sure it is the only thing in the square brackets.
        $next = $phpcs_file->find_next(T_WHITESPACE, $index + 1, null, true);
        if ($tokens[$next]['code'] !== T_CLOSE_SQUARE_BRACKET) {
            return;
        }
        // Allow indexes that have dots in them because we can't write
        // them in dot notation.
        $content = trim($tokens[$index]['content'], '"\' ');
        if (strpos($content, '.') !== false) {
            return;
        }
        // Also ignore reserved words.
        if ($content === 'super') {
            return;
        }
        // Token before the opening square bracket cannot be a var name.
        $prev = $phpcs_file->find_previous(T_WHITESPACE, $stack_ptr - 1, null, true);
        if ($tokens[$prev]['code'] === T_STRING) {
            $error = 'Object indexes must be written in dot notation';
            $phpcs_file->add_error($error, $prev, 'Found');
        }
    }
    //end process()
}
//end class