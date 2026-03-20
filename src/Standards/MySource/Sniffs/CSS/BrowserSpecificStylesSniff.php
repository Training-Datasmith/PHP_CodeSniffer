<?php

declare (strict_types=1);
/**
 * Ensure that browser-specific styles are not used.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\My_Source\Sniffs\CSS;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Browser_Specific_Styles_Sniff implements Sniff
{
    /**
     * A list of tokenizers this sniff supports.
     *
     * @var array
     */
    public $supported_tokenizers = ['CSS'];
    /**
     * A list of specific stylesheet suffixes we allow.
     *
     * These stylesheets contain browser specific styles
     * so this sniff ignore them files in the form:
     * *_moz.css and *_ie7.css etc.
     *
     * @var array
     */
    protected $specific_stylesheets = ['moz' => true, 'ie' => true, 'ie7' => true, 'ie8' => true, 'webkit' => true];
    /**
     * Returns the token types that this sniff is interested in.
     *
     * @return int[]
     */
    public function register()
    {
        return [T_STYLE];
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
        // Ignore files with browser-specific suffixes.
        $filename = $phpcs_file->get_filename();
        $break_char = strrpos($filename, '_');
        if ($break_char !== false && substr($filename, -4) === '.css') {
            $specific = substr($filename, $break_char + 1, -4);
            if (isset($this->specific_stylesheets[$specific]) === true) {
                return;
            }
        }
        $tokens = $phpcs_file->get_tokens();
        $content = $tokens[$stack_ptr]['content'];
        if ($content[0] === '-') {
            $error = 'Browser-specific styles are not allowed';
            $phpcs_file->add_error($error, $stack_ptr, 'ForbiddenStyle');
        }
    }
    //end process()
}
//end class