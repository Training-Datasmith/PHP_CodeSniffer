<?php

declare (strict_types=1);
/**
 * Ensures that the ++ operators are used when possible.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\Operators;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Increment_Decrement_Usage_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_EQUAL, T_PLUS_EQUAL, T_MINUS_EQUAL, T_INC, T_DEC];
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
        if ($tokens[$stack_ptr]['code'] === T_INC || $tokens[$stack_ptr]['code'] === T_DEC) {
            $this->process_inc_dec($phpcs_file, $stack_ptr);
        } else {
            $this->process_assignment($phpcs_file, $stack_ptr);
        }
    }
    //end process()
    /**
     * Checks to ensure increment and decrement operators are not confusing.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token
     *                                               in the stack passed in $tokens.
     *
     * @return void
     */
    protected function process_inc_dec($phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        // Work out where the variable is so we know where to
        // start looking for other operators.
        if ($tokens[$stack_ptr - 1]['code'] === T_VARIABLE || $tokens[$stack_ptr - 1]['code'] === T_STRING && ($tokens[$stack_ptr - 2]['code'] === T_OBJECT_OPERATOR || $tokens[$stack_ptr - 2]['code'] === T_NULLSAFE_OBJECT_OPERATOR)) {
            $start = $stack_ptr + 1;
        } else {
            $start = $stack_ptr + 2;
        }
        $next = $phpcs_file->find_next(Tokens::$empty_tokens, $start, null, true);
        if ($next === false) {
            return;
        }
        if (isset(Tokens::$arithmetic_tokens[$tokens[$next]['code']]) === true) {
            $error = 'Increment and decrement operators cannot be used in an arithmetic operation';
            $phpcs_file->add_error($error, $stack_ptr, 'NotAllowed');
            return;
        }
        $prev = $phpcs_file->find_previous(Tokens::$empty_tokens, $start - 3, null, true);
        if ($prev === false) {
            return;
        }
        // Check if this is in a string concat.
        if ($tokens[$next]['code'] === T_STRING_CONCAT || $tokens[$prev]['code'] === T_STRING_CONCAT) {
            $error = 'Increment and decrement operators must be bracketed when used in string concatenation';
            $phpcs_file->add_error($error, $stack_ptr, 'NoBrackets');
        }
    }
    //end processIncDec()
    /**
     * Checks to ensure increment and decrement operators are used.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token
     *                                               in the stack passed in $tokens.
     *
     * @return void
     */
    protected function process_assignment($phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        $assigned_var = $phpcs_file->find_previous(T_WHITESPACE, $stack_ptr - 1, null, true);
        // Not an assignment, return.
        if ($tokens[$assigned_var]['code'] !== T_VARIABLE) {
            return;
        }
        $statement_end = $phpcs_file->find_next([T_SEMICOLON, T_CLOSE_PARENTHESIS, T_CLOSE_SQUARE_BRACKET, T_CLOSE_CURLY_BRACKET], $stack_ptr);
        // If there is anything other than variables, numbers, spaces or operators we need to return.
        $noise_tokens = $phpcs_file->find_next([T_LNUMBER, T_VARIABLE, T_WHITESPACE, T_PLUS, T_MINUS, T_OPEN_PARENTHESIS], $stack_ptr + 1, $statement_end, true);
        if ($noise_tokens !== false) {
            return;
        }
        // If we are already using += or -=, we need to ignore
        // the statement if a variable is being used.
        if ($tokens[$stack_ptr]['code'] !== T_EQUAL) {
            $next_var = $phpcs_file->find_next(T_VARIABLE, $stack_ptr + 1, $statement_end);
            if ($next_var !== false) {
                return;
            }
        }
        if ($tokens[$stack_ptr]['code'] === T_EQUAL) {
            $next_var = $stack_ptr + 1;
            $previous_variable = $stack_ptr + 1;
            $variable_count = 0;
            while (($next_var = $phpcs_file->find_next(T_VARIABLE, $next_var + 1, $statement_end)) !== false) {
                $previous_variable = $next_var;
                $variable_count++;
            }
            if ($variable_count !== 1) {
                return;
            }
            $next_var = $previous_variable;
            if ($tokens[$next_var]['content'] !== $tokens[$assigned_var]['content']) {
                return;
            }
        }
        // We have only one variable, and it's the same as what is being assigned,
        // so we need to check what is being added or subtracted.
        $next_number = $stack_ptr + 1;
        $previous_number = $stack_ptr + 1;
        $number_count = 0;
        while (($next_number = $phpcs_file->find_next([T_LNUMBER], $next_number + 1, $statement_end, false)) !== false) {
            $previous_number = $next_number;
            $number_count++;
        }
        if ($number_count !== 1) {
            return;
        }
        $next_number = $previous_number;
        if ($tokens[$next_number]['content'] === '1') {
            if ($tokens[$stack_ptr]['code'] === T_EQUAL) {
                $op_token = $phpcs_file->find_next([T_PLUS, T_MINUS], $next_var + 1, $statement_end);
                if ($op_token === false) {
                    // Operator was before the variable, like:
                    // $var = 1 + $var;
                    // So we ignore it.
                    return;
                }
                $operator = $tokens[$op_token]['content'];
            } else {
                $operator = substr($tokens[$stack_ptr]['content'], 0, 1);
            }
            // If we are adding or subtracting negative value, the operator
            // needs to be reversed.
            if ($tokens[$stack_ptr]['code'] !== T_EQUAL) {
                $negative = $phpcs_file->find_previous(T_MINUS, $next_number - 1, $stack_ptr);
                if ($negative !== false) {
                    if ($operator === '+') {
                        $operator = '-';
                    } else {
                        $operator = '+';
                    }
                }
            }
            $expected = $operator . $operator . $tokens[$assigned_var]['content'];
            $found = $phpcs_file->get_tokens_as_string($assigned_var, $statement_end - $assigned_var + 1);
            if ($operator === '+') {
                $error = 'Increment';
            } else {
                $error = 'Decrement';
            }
            $error .= " operators should be used where possible; found \"{$found}\" but expected \"{$expected}\"";
            $phpcs_file->add_error($error, $stack_ptr, 'Found');
        }
        //end if
    }
    //end processAssignment()
}
//end class