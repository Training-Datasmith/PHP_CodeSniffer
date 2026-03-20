<?php

declare (strict_types=1);
/**
 * Verifies spacing between variables and increment/decrement operators.
 *
 * @author    Juliette Reinders Folmer <phpcs_nospam@adviesenzo.nl>
 * @copyright 2018 Juliette Reinders Folmer. All rights reserved.
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Generic\Sniffs\White_Space;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Increment_Decrement_Spacing_Sniff implements Sniff
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
        return [T_DEC, T_INC];
    }
    //end register()
    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token in
     *                                               the stack passed in $tokens.
     *
     * @return void
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        $token_name = 'increment';
        if ($tokens[$stack_ptr]['code'] === T_DEC) {
            $token_name = 'decrement';
        }
        // Is this a pre-increment/decrement ?
        $next_non_empty = $phpcs_file->find_next(Tokens::$empty_tokens, $stack_ptr + 1, null, true);
        if ($next_non_empty !== false && ($phpcs_file->tokenizer_type === 'PHP' && $tokens[$next_non_empty]['code'] === T_VARIABLE || $phpcs_file->tokenizer_type === 'JS' && $tokens[$next_non_empty]['code'] === T_STRING)) {
            if ($next_non_empty === $stack_ptr + 1) {
                $phpcs_file->record_metric($stack_ptr, 'Spacing between in/decrementor and variable', 0);
                return;
            }
            $spaces = 0;
            $fixable = true;
            $next_non_whitespace = $phpcs_file->find_next(T_WHITESPACE, $stack_ptr + 1, null, true);
            if ($next_non_whitespace !== $next_non_empty) {
                $fixable = false;
                $spaces = 'comment';
            } else if ($tokens[$stack_ptr]['line'] !== $tokens[$next_non_empty]['line']) {
                $spaces = 'newline';
            } else {
                $spaces = $tokens[$stack_ptr + 1]['length'];
            }
            $phpcs_file->record_metric($stack_ptr, 'Spacing between in/decrementor and variable', $spaces);
            $error = 'Expected no spaces between the %s operator and %s; %s found';
            $error_code = 'SpaceAfter' . ucfirst($token_name);
            $data = [$token_name, $tokens[$next_non_empty]['content'], $spaces];
            if ($fixable === false) {
                $phpcs_file->add_error($error, $stack_ptr, $error_code, $data);
                return;
            }
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, $error_code, $data);
            if ($fix === true) {
                $phpcs_file->fixer->begin_changeset();
                for ($i = $stack_ptr + 1; $i < $next_non_empty; $i++) {
                    $phpcs_file->fixer->replace_token($i, '');
                }
                $phpcs_file->fixer->end_changeset();
            }
            return;
        }
        //end if
        // Is this a post-increment/decrement ?
        $prev_non_empty = $phpcs_file->find_previous(Tokens::$empty_tokens, $stack_ptr - 1, null, true);
        if ($prev_non_empty !== false && ($phpcs_file->tokenizer_type === 'PHP' && $tokens[$prev_non_empty]['code'] === T_VARIABLE || $phpcs_file->tokenizer_type === 'JS' && $tokens[$prev_non_empty]['code'] === T_STRING)) {
            if ($prev_non_empty === $stack_ptr - 1) {
                $phpcs_file->record_metric($stack_ptr, 'Spacing between in/decrementor and variable', 0);
                return;
            }
            $spaces = 0;
            $fixable = true;
            $prev_non_whitespace = $phpcs_file->find_previous(T_WHITESPACE, $stack_ptr - 1, null, true);
            if ($prev_non_whitespace !== $prev_non_empty) {
                $fixable = false;
                $spaces = 'comment';
            } else if ($tokens[$stack_ptr]['line'] !== $tokens[$next_non_empty]['line']) {
                $spaces = 'newline';
            } else {
                $spaces = $tokens[$stack_ptr - 1]['length'];
            }
            $phpcs_file->record_metric($stack_ptr, 'Spacing between in/decrementor and variable', $spaces);
            $error = 'Expected no spaces between %s and the %s operator; %s found';
            $error_code = 'SpaceAfter' . ucfirst($token_name);
            $data = [$tokens[$prev_non_empty]['content'], $token_name, $spaces];
            if ($fixable === false) {
                $phpcs_file->add_error($error, $stack_ptr, $error_code, $data);
                return;
            }
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, $error_code, $data);
            if ($fix === true) {
                $phpcs_file->fixer->begin_changeset();
                for ($i = $stack_ptr - 1; $prev_non_empty < $i; $i--) {
                    $phpcs_file->fixer->replace_token($i, '');
                }
                $phpcs_file->fixer->end_changeset();
            }
        }
        //end if
    }
    //end process()
}
//end class