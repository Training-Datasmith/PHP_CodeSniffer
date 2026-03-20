<?php

declare (strict_types=1);
/**
 * Ensure there is no whitespace before/after an object operator.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\White_Space;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Object_Operator_Spacing_Sniff implements Sniff
{
    /**
     * Allow newlines instead of spaces.
     *
     * @var boolean
     */
    public $ignore_newlines = false;
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_NULLSAFE_OBJECT_OPERATOR];
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
        if ($tokens[$stack_ptr - 1]['code'] !== T_WHITESPACE) {
            $before = 0;
        } else if ($tokens[$stack_ptr - 2]['line'] !== $tokens[$stack_ptr]['line']) {
            $before = 'newline';
        } else {
            $before = $tokens[$stack_ptr - 1]['length'];
        }
        $phpcs_file->record_metric($stack_ptr, 'Spacing before object operator', $before);
        $this->check_spacing_before_operator($phpcs_file, $stack_ptr, $before);
        if (isset($tokens[$stack_ptr + 1]) === false || isset($tokens[$stack_ptr + 2]) === false) {
            return;
        }
        if ($tokens[$stack_ptr + 1]['code'] !== T_WHITESPACE) {
            $after = 0;
        } else if ($tokens[$stack_ptr + 2]['line'] !== $tokens[$stack_ptr]['line']) {
            $after = 'newline';
        } else {
            $after = $tokens[$stack_ptr + 1]['length'];
        }
        $phpcs_file->record_metric($stack_ptr, 'Spacing after object operator', $after);
        $this->check_spacing_after_operator($phpcs_file, $stack_ptr, $after);
    }
    //end process()
    /**
     * Check the spacing before the operator.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token
     *                                               in the stack passed in $tokens.
     * @param mixed                       $before    The number of spaces found before the
     *                                               operator or the string 'newline'.
     *
     * @return boolean true if there was no error, false otherwise.
     */
    protected function check_spacing_before_operator(File $phpcs_file, $stack_ptr, $before)
    {
        if ($before !== 0 && ($before !== 'newline' || $this->ignore_newlines === false)) {
            $error = 'Space found before object operator';
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'Before');
            if ($fix === true) {
                $tokens = $phpcs_file->get_tokens();
                $cur_pos = $stack_ptr - 1;
                $phpcs_file->fixer->begin_changeset();
                while ($tokens[$cur_pos]['code'] === T_WHITESPACE) {
                    $phpcs_file->fixer->replace_token($cur_pos, '');
                    --$cur_pos;
                }
                $phpcs_file->fixer->end_changeset();
            }
            return false;
        }
        return true;
    }
    //end checkSpacingBeforeOperator()
    /**
     * Check the spacing after the operator.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token
     *                                               in the stack passed in $tokens.
     * @param mixed                       $after     The number of spaces found after the
     *                                               operator or the string 'newline'.
     *
     * @return boolean true if there was no error, false otherwise.
     */
    protected function check_spacing_after_operator(File $phpcs_file, $stack_ptr, $after)
    {
        if ($after !== 0 && ($after !== 'newline' || $this->ignore_newlines === false)) {
            $error = 'Space found after object operator';
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'After');
            if ($fix === true) {
                $tokens = $phpcs_file->get_tokens();
                $cur_pos = $stack_ptr + 1;
                $phpcs_file->fixer->begin_changeset();
                while ($tokens[$cur_pos]['code'] === T_WHITESPACE) {
                    $phpcs_file->fixer->replace_token($cur_pos, '');
                    ++$cur_pos;
                }
                $phpcs_file->fixer->end_changeset();
            }
            return false;
        }
        return true;
    }
    //end checkSpacingAfterOperator()
}
//end class