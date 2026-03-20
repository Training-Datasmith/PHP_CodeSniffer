<?php

declare (strict_types=1);
/**
 * Ensures that constant names are all uppercase.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Generic\Sniffs\Naming_Conventions;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Upper_Case_Constant_Name_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_STRING, T_CONST];
    }
    //end register()
    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token in the
     *                                               stack passed in $tokens.
     *
     * @return void
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        if ($tokens[$stack_ptr]['code'] === T_CONST) {
            // This is a class constant.
            $constant = $phpcs_file->find_next(Tokens::$empty_tokens, $stack_ptr + 1, null, true);
            if ($constant === false) {
                return;
            }
            $const_name = $tokens[$constant]['content'];
            if (strtoupper($const_name) !== $const_name) {
                if (strtolower($const_name) === $const_name) {
                    $phpcs_file->record_metric($constant, 'Constant name case', 'lower');
                } else {
                    $phpcs_file->record_metric($constant, 'Constant name case', 'mixed');
                }
                $error = 'Class constants must be uppercase; expected %s but found %s';
                $data = [strtoupper($const_name), $const_name];
                $phpcs_file->add_error($error, $constant, 'ClassConstantNotUpperCase', $data);
            } else {
                $phpcs_file->record_metric($constant, 'Constant name case', 'upper');
            }
            return;
        }
        //end if
        // Only interested in define statements now.
        if (strtolower($tokens[$stack_ptr]['content']) !== 'define') {
            return;
        }
        // Make sure this is not a method call.
        $prev = $phpcs_file->find_previous(T_WHITESPACE, $stack_ptr - 1, null, true);
        if ($tokens[$prev]['code'] === T_OBJECT_OPERATOR || $tokens[$prev]['code'] === T_DOUBLE_COLON || $tokens[$prev]['code'] === T_NULLSAFE_OBJECT_OPERATOR) {
            return;
        }
        // If the next non-whitespace token after this token
        // is not an opening parenthesis then it is not a function call.
        $open_bracket = $phpcs_file->find_next(Tokens::$empty_tokens, $stack_ptr + 1, null, true);
        if ($open_bracket === false) {
            return;
        }
        // The next non-whitespace token must be the constant name.
        $const_ptr = $phpcs_file->find_next(T_WHITESPACE, $open_bracket + 1, null, true);
        if ($tokens[$const_ptr]['code'] !== T_CONSTANT_ENCAPSED_STRING) {
            return;
        }
        $const_name = $tokens[$const_ptr]['content'];
        // Check for constants like self::CONSTANT.
        $prefix = '';
        $split_pos = strpos($const_name, '::');
        if ($split_pos !== false) {
            $prefix = substr($const_name, 0, $split_pos + 2);
            $const_name = substr($const_name, $split_pos + 2);
        }
        // Strip namespace from constant like /foo/bar/CONSTANT.
        $split_pos = strrpos($const_name, '\\');
        if ($split_pos !== false) {
            $prefix = substr($const_name, 0, $split_pos + 1);
            $const_name = substr($const_name, $split_pos + 1);
        }
        if (strtoupper($const_name) !== $const_name) {
            if (strtolower($const_name) === $const_name) {
                $phpcs_file->record_metric($stack_ptr, 'Constant name case', 'lower');
            } else {
                $phpcs_file->record_metric($stack_ptr, 'Constant name case', 'mixed');
            }
            $error = 'Constants must be uppercase; expected %s but found %s';
            $data = [$prefix . strtoupper($const_name), $prefix . $const_name];
            $phpcs_file->add_error($error, $stack_ptr, 'ConstantNotUpperCase', $data);
        } else {
            $phpcs_file->record_metric($stack_ptr, 'Constant name case', 'upper');
        }
    }
    //end process()
}
//end class