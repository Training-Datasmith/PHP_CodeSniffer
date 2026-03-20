<?php

declare (strict_types=1);
/**
 * Ensures that strings are not joined using array.join().
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\My_Source\Sniffs\Strings;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Join_Strings_Sniff implements Sniff
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
        return [T_STRING];
    }
    //end register()
    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param integer                     $stackPtr  The position of the current token
     *                                               in the stack passed in $tokens.
     *
     * @return void
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        if ($tokens[$stack_ptr]['content'] !== 'join') {
            return;
        }
        $prev = $phpcs_file->find_previous(Tokens::$empty_tokens, $stack_ptr - 1, null, true);
        if ($tokens[$prev]['code'] !== T_OBJECT_OPERATOR) {
            return;
        }
        $prev = $phpcs_file->find_previous(Tokens::$empty_tokens, $prev - 1, null, true);
        if ($tokens[$prev]['code'] === T_CLOSE_SQUARE_BRACKET) {
            $opener = $tokens[$prev]['bracket_opener'];
            if ($tokens[$opener - 1]['code'] !== T_STRING) {
                // This means the array is declared inline, like x = [a,b,c].join()
                // and not elsewhere, like x = y[a].join()
                // The first is not allowed while the second is.
                $error = 'Joining strings using inline arrays is not allowed; use the + operator instead';
                $phpcs_file->add_error($error, $stack_ptr, 'ArrayNotAllowed');
            }
        }
    }
    //end process()
}
//end class