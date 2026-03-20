<?php

declare (strict_types=1);
/**
 * Ensures the last member of an object is not followed by a comma.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\Objects;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Object_Member_Comma_Sniff implements Sniff
{
    /**
     * A list of tokenizers this sniff supports.
     *
     * @var array
     */
    public $supported_tokenizers = ['JS'];
    /**
     * Registers the token types that this sniff wishes to listen to.
     *
     * @return array
     */
    public function register()
    {
        return [T_CLOSE_OBJECT];
    }
    //end register()
    /**
     * Process the tokens that this sniff is listening for.
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
        $prev = $phpcs_file->find_previous(Tokens::$empty_tokens, $stack_ptr - 1, null, true);
        if ($tokens[$prev]['code'] === T_COMMA) {
            $error = 'Last member of object must not be followed by a comma';
            $fix = $phpcs_file->add_fixable_error($error, $prev, 'Found');
            if ($fix === true) {
                $phpcs_file->fixer->replace_token($prev, '');
            }
        }
    }
    //end process()
}
//end class