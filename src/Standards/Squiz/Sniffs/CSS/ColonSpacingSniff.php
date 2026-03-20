<?php

declare (strict_types=1);
/**
 * Ensure there is no space before a colon and one space after it.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\CSS;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Colon_Spacing_Sniff implements Sniff
{
    /**
     * A list of tokenizers this sniff supports.
     *
     * @var array
     */
    public $supported_tokenizers = ['CSS'];
    /**
     * Returns the token types that this sniff is interested in.
     *
     * @return int[]
     */
    public function register()
    {
        return [T_COLON];
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
        $prev = $phpcs_file->find_previous(Tokens::$empty_tokens, $stack_ptr - 1, null, true);
        if ($tokens[$prev]['code'] !== T_STYLE) {
            // The colon is not part of a style definition.
            return;
        }
        if ($tokens[$prev]['content'] === 'progid') {
            // Special case for IE filters.
            return;
        }
        if ($tokens[$stack_ptr - 1]['code'] === T_WHITESPACE) {
            $error = 'There must be no space before a colon in a style definition';
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'Before');
            if ($fix === true) {
                $phpcs_file->fixer->replace_token($stack_ptr - 1, '');
            }
        }
        $next = $phpcs_file->find_next(T_WHITESPACE, $stack_ptr + 1, null, true);
        if ($tokens[$next]['code'] === T_SEMICOLON || $tokens[$next]['code'] === T_STYLE) {
            // Empty style definition, ignore it.
            return;
        }
        if ($tokens[$stack_ptr + 1]['code'] !== T_WHITESPACE) {
            $error = 'Expected 1 space after colon in style definition; 0 found';
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'NoneAfter');
            if ($fix === true) {
                $phpcs_file->fixer->add_content($stack_ptr, ' ');
            }
        } else {
            $content = $tokens[$stack_ptr + 1]['content'];
            if (strpos($content, $phpcs_file->eol_char) === false) {
                $length = strlen($content);
                if ($length !== 1) {
                    $error = 'Expected 1 space after colon in style definition; %s found';
                    $data = [$length];
                    $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'After', $data);
                    if ($fix === true) {
                        $phpcs_file->fixer->replace_token($stack_ptr + 1, ' ');
                    }
                }
            } else {
                $error = 'Expected 1 space after colon in style definition; newline found';
                $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'AfterNewline');
                if ($fix === true) {
                    $phpcs_file->fixer->replace_token($stack_ptr + 1, ' ');
                }
            }
        }
        //end if
    }
    //end process()
}
//end class