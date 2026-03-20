<?php

declare (strict_types=1);
/**
 * Verifies that operators have valid spacing surrounding them.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\PSR12\Sniffs\Operators;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Standards\Squiz\Sniffs\White_Space\Operator_Spacing_Sniff as SquizOperatorSpacingSniff;
use Php_code_Sniffer\Util\Tokens;
class Operator_Spacing_Sniff extends Squiz_Operator_Spacing_Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        parent::register();
        $targets = Tokens::$comparison_tokens;
        $targets += Tokens::$operators;
        $targets += Tokens::$assignment_tokens;
        $targets += Tokens::$boolean_operators;
        $targets[] = T_INLINE_THEN;
        $targets[] = T_INLINE_ELSE;
        $targets[] = T_STRING_CONCAT;
        $targets[] = T_INSTANCEOF;
        return $targets;
    }
    //end register()
    /**
     * Processes this sniff, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The current file being checked.
     * @param int                         $stackPtr  The position of the current token in
     *                                               the stack passed in $tokens.
     *
     * @return void
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        if ($this->is_operator($phpcs_file, $stack_ptr) === false) {
            return;
        }
        $operator = $tokens[$stack_ptr]['content'];
        $check_before = true;
        $check_after = true;
        // Skip short ternary.
        if ($tokens[$stack_ptr]['code'] === T_INLINE_ELSE && $tokens[$stack_ptr - 1]['code'] === T_INLINE_THEN) {
            $check_before = false;
        }
        // Skip operator with comment on previous line.
        if ($tokens[$stack_ptr - 1]['code'] === T_COMMENT && $tokens[$stack_ptr - 1]['line'] < $tokens[$stack_ptr]['line']) {
            $check_before = false;
        }
        if (isset($tokens[$stack_ptr + 1]) === true) {
            // Skip short ternary.
            if ($tokens[$stack_ptr]['code'] === T_INLINE_THEN && $tokens[$stack_ptr + 1]['code'] === T_INLINE_ELSE) {
                $check_after = false;
            }
        } else {
            // Skip partial files.
            $check_after = false;
        }
        if ($check_before === true && $tokens[$stack_ptr - 1]['code'] !== T_WHITESPACE) {
            $error = 'Expected at least 1 space before "%s"; 0 found';
            $data = [$operator];
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'NoSpaceBefore', $data);
            if ($fix === true) {
                $phpcs_file->fixer->add_content_before($stack_ptr, ' ');
            }
        }
        if ($check_after === true && $tokens[$stack_ptr + 1]['code'] !== T_WHITESPACE) {
            $error = 'Expected at least 1 space after "%s"; 0 found';
            $data = [$operator];
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'NoSpaceAfter', $data);
            if ($fix === true) {
                $phpcs_file->fixer->add_content($stack_ptr, ' ');
            }
        }
    }
    //end process()
}
//end class