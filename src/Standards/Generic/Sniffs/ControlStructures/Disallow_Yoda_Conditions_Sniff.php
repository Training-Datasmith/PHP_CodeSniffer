<?php

declare (strict_types=1);
/**
 * Ban the use of Yoda conditions.
 *
 * @author    Mponos George <gmponos@gmail.com>
 * @author    Mark Scherer <username@example.com>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Generic\Sniffs\Control_Structures;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Disallow_Yoda_Conditions_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return Tokens::$comparison_tokens;
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
        $previous_index = $phpcs_file->find_previous(Tokens::$empty_tokens, $stack_ptr - 1, null, true);
        $relevant_tokens = [T_CLOSE_SHORT_ARRAY, T_CLOSE_PARENTHESIS, T_TRUE, T_FALSE, T_NULL, T_LNUMBER, T_DNUMBER, T_CONSTANT_ENCAPSED_STRING];
        if ($previous_index === false || in_array($tokens[$previous_index]['code'], $relevant_tokens, true) === false) {
            return;
        }
        if ($tokens[$previous_index]['code'] === T_CLOSE_SHORT_ARRAY) {
            $previous_index = $tokens[$previous_index]['bracket_opener'];
            if ($this->is_array_static($phpcs_file, $previous_index) === false) {
                return;
            }
        }
        $prev_index = $phpcs_file->find_previous(Tokens::$empty_tokens, $previous_index - 1, null, true);
        if ($prev_index === false) {
            return;
        }
        if (in_array($tokens[$prev_index]['code'], Tokens::$arithmetic_tokens, true) === true) {
            return;
        }
        if ($tokens[$prev_index]['code'] === T_STRING_CONCAT) {
            return;
        }
        // Is it a parenthesis.
        if ($tokens[$previous_index]['code'] === T_CLOSE_PARENTHESIS) {
            // Check what exists inside the parenthesis.
            $close_parenthesis_index = $phpcs_file->find_previous(Tokens::$empty_tokens, $tokens[$previous_index]['parenthesis_opener'] - 1, null, true);
            if ($close_parenthesis_index === false || $tokens[$close_parenthesis_index]['code'] !== T_ARRAY) {
                if ($tokens[$close_parenthesis_index]['code'] === T_STRING) {
                    return;
                }
                // If it is not an array check what is inside.
                $found = $phpcs_file->find_previous(T_VARIABLE, $previous_index - 1, $tokens[$previous_index]['parenthesis_opener']);
                // If a variable exists, it is not Yoda.
                if ($found !== false) {
                    return;
                }
                // If there is nothing inside the parenthesis, it it not a Yoda.
                $opener = $tokens[$previous_index]['parenthesis_opener'];
                $prev = $phpcs_file->find_previous(Tokens::$empty_tokens, $previous_index - 1, $opener + 1, true);
                if ($prev === false) {
                    return;
                }
            } elseif ($tokens[$close_parenthesis_index]['code'] === T_ARRAY && $this->is_array_static($phpcs_file, $close_parenthesis_index) === false) {
                return;
            }
            //end if
        }
        //end if
        $phpcs_file->add_error('Usage of Yoda conditions is not allowed; switch the expression order', $stack_ptr, 'Found');
    }
    //end process()
    /**
     * Determines if an array is a static definition.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile  The file being scanned.
     * @param int                         $arrayToken The position of the array token.
     *
     * @return bool
     */
    public function is_array_static(File $phpcs_file, $array_token)
    {
        $tokens = $phpcs_file->get_tokens();
        if ($tokens[$array_token]['code'] === T_OPEN_SHORT_ARRAY) {
            $start = $array_token;
            $end = $tokens[$array_token]['bracket_closer'];
        } elseif ($tokens[$array_token]['code'] === T_ARRAY) {
            $start = $tokens[$array_token]['parenthesis_opener'];
            $end = $tokens[$array_token]['parenthesis_closer'];
        } else {
            return true;
        }
        $static_tokens = Tokens::$empty_tokens;
        $static_tokens += Tokens::$text_string_tokens;
        $static_tokens += Tokens::$assignment_tokens;
        $static_tokens += Tokens::$equality_tokens;
        $static_tokens += Tokens::$comparison_tokens;
        $static_tokens += Tokens::$arithmetic_tokens;
        $static_tokens += Tokens::$operators;
        $static_tokens += Tokens::$boolean_operators;
        $static_tokens += Tokens::$cast_tokens;
        $static_tokens += Tokens::$bracket_tokens;
        $static_tokens += [T_DOUBLE_ARROW => T_DOUBLE_ARROW, T_COMMA => T_COMMA, T_TRUE => T_TRUE, T_FALSE => T_FALSE];
        for ($i = $start + 1; $i < $end; $i++) {
            if (isset($tokens[$i]['scope_closer']) === true) {
                $i = $tokens[$i]['scope_closer'];
                continue;
            }
            if (isset($static_tokens[$tokens[$i]['code']]) === false) {
                return false;
            }
        }
        return true;
    }
    //end isArrayStatic()
}
//end class