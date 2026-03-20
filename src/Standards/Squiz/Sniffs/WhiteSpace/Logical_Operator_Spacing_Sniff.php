<?php

declare (strict_types=1);
/**
 * Verifies that operators have valid spacing surrounding them.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\White_Space;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Logical_Operator_Spacing_Sniff implements Sniff
{
    /**
     * A list of tokenizers this sniff supports.
     *
     * @var array
     */
    public $supported_tokenizers = ['PHP', 'JS'];
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return Tokens::$boolean_operators;
    }
    //end register()
    /**
     * Processes this sniff, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The current file being checked.
     * @param int                         $stackPtr  The position of the current token
     *                                               in the stack passed in $tokens.
     *
     * @return void
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        // Check there is one space before the operator.
        if ($tokens[$stack_ptr - 1]['code'] !== T_WHITESPACE) {
            $error = 'Expected 1 space before logical operator; 0 found';
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'NoSpaceBefore');
            if ($fix === true) {
                $phpcs_file->fixer->add_content_before($stack_ptr, ' ');
            }
        } else {
            $prev = $phpcs_file->find_previous(T_WHITESPACE, $stack_ptr - 1, null, true);
            if ($tokens[$stack_ptr]['line'] === $tokens[$prev]['line'] && $tokens[$stack_ptr - 1]['length'] !== 1) {
                $found = $tokens[$stack_ptr - 1]['length'];
                $error = 'Expected 1 space before logical operator; %s found';
                $data = [$found];
                $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'TooMuchSpaceBefore', $data);
                if ($fix === true) {
                    $phpcs_file->fixer->replace_token($stack_ptr - 1, ' ');
                }
            }
        }
        // Check there is one space after the operator.
        if ($tokens[$stack_ptr + 1]['code'] !== T_WHITESPACE) {
            $error = 'Expected 1 space after logical operator; 0 found';
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'NoSpaceAfter');
            if ($fix === true) {
                $phpcs_file->fixer->add_content($stack_ptr, ' ');
            }
        } else {
            $next = $phpcs_file->find_next(T_WHITESPACE, $stack_ptr + 1, null, true);
            if ($tokens[$stack_ptr]['line'] === $tokens[$next]['line'] && $tokens[$stack_ptr + 1]['length'] !== 1) {
                $found = $tokens[$stack_ptr + 1]['length'];
                $error = 'Expected 1 space after logical operator; %s found';
                $data = [$found];
                $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'TooMuchSpaceAfter', $data);
                if ($fix === true) {
                    $phpcs_file->fixer->replace_token($stack_ptr + 1, ' ');
                }
            }
        }
    }
    //end process()
}
//end class