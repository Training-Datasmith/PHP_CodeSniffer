<?php

declare (strict_types=1);
/**
 * Ensures that a property or label colon has a single space after it and no space before it.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\White_Space;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Property_Label_Spacing_Sniff implements Sniff
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
        return [T_PROPERTY, T_LABEL];
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
        $colon = $phpcs_file->find_next(T_COLON, $stack_ptr + 1);
        if ($colon !== $stack_ptr + 1) {
            $error = 'There must be no space before the colon in a property/label declaration';
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'Before');
            if ($fix === true) {
                $phpcs_file->fixer->replace_token($stack_ptr + 1, '');
            }
        }
        if ($tokens[$colon + 1]['code'] !== T_WHITESPACE || $tokens[$colon + 1]['content'] !== ' ') {
            $error = 'There must be a single space after the colon in a property/label declaration';
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'After');
            if ($fix === true) {
                if ($tokens[$colon + 1]['code'] === T_WHITESPACE) {
                    $phpcs_file->fixer->replace_token($colon + 1, ' ');
                } else {
                    $phpcs_file->fixer->add_content($colon, ' ');
                }
            }
        }
    }
    //end process()
}
//end class