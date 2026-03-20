<?php

declare (strict_types=1);
/**
 * Ensure colour names are not used.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\CSS;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Named_Colours_Sniff implements Sniff
{
    /**
     * A list of tokenizers this sniff supports.
     *
     * @var array
     */
    public $supported_tokenizers = ['CSS'];
    /**
     * A list of named colours.
     *
     * This is the list of standard colours defined in the CSS specification.
     *
     * @var array
     */
    protected $colour_names = ['aqua' => 'aqua', 'black' => 'black', 'blue' => 'blue', 'fuchsia' => 'fuchsia', 'gray' => 'gray', 'green' => 'green', 'lime' => 'lime', 'maroon' => 'maroon', 'navy' => 'navy', 'olive' => 'olive', 'orange' => 'orange', 'purple' => 'purple', 'red' => 'red', 'silver' => 'silver', 'teal' => 'teal', 'white' => 'white', 'yellow' => 'yellow'];
    /**
     * Returns the token types that this sniff is interested in.
     *
     * @return int[]
     */
    public function register()
    {
        return [T_STRING];
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
        if ($tokens[$stack_ptr - 1]['code'] === T_HASH || $tokens[$stack_ptr - 1]['code'] === T_STRING_CONCAT) {
            // Class name.
            return;
        }
        if (isset($this->colour_names[strtolower($tokens[$stack_ptr]['content'])]) === true) {
            $error = 'Named colours are forbidden; use hex, rgb, or rgba values instead';
            $phpcs_file->add_error($error, $stack_ptr, 'Forbidden');
        }
    }
    //end process()
}
//end class