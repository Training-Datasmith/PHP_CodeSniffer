<?php

declare (strict_types=1);
/**
 * Tests that all arithmetic operations are bracketed.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\Formatting;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Operator_Bracket_Sniff implements Sniff
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
        return Tokens::$operators;
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
        if ($phpcs_file->tokenizer_type === 'JS' && $tokens[$stack_ptr]['code'] === T_PLUS) {
            // JavaScript uses the plus operator for string concatenation as well
            // so we cannot accurately determine if it is a string concat or addition.
            // So just ignore it.
            return;
        }
        // If the & is a reference, then we don't want to check for brackets.
        if ($tokens[$stack_ptr]['code'] === T_BITWISE_AND && $phpcs_file->is_reference($stack_ptr) === true) {
            return;
        }
        // There is one instance where brackets aren't needed, which involves
        // the minus sign being used to assign a negative number to a variable.
        if ($tokens[$stack_ptr]['code'] === T_MINUS) {
            // Check to see if we are trying to return -n.
            $prev = $phpcs_file->find_previous(Tokens::$empty_tokens, $stack_ptr - 1, null, true);
            if ($tokens[$prev]['code'] === T_RETURN) {
                return;
            }
            $number = $phpcs_file->find_next(T_WHITESPACE, $stack_ptr + 1, null, true);
            if ($tokens[$number]['code'] === T_LNUMBER || $tokens[$number]['code'] === T_DNUMBER) {
                $previous = $phpcs_file->find_previous(T_WHITESPACE, $stack_ptr - 1, null, true);
                if ($previous !== false) {
                    $is_assignment = isset(Tokens::$assignment_tokens[$tokens[$previous]['code']]);
                    $is_equality = isset(Tokens::$equality_tokens[$tokens[$previous]['code']]);
                    $is_comparison = isset(Tokens::$comparison_tokens[$tokens[$previous]['code']]);
                    $is_unary = isset(Tokens::$operators[$tokens[$previous]['code']]);
                    if ($is_assignment === true || $is_equality === true || $is_comparison === true || $is_unary === true) {
                        // This is a negative assignment or comparison.
                        // We need to check that the minus and the number are
                        // adjacent.
                        if ($number - $stack_ptr !== 1) {
                            $error = 'No space allowed between minus sign and number';
                            $phpcs_file->add_error($error, $stack_ptr, 'SpacingAfterMinus');
                        }
                        return;
                    }
                }
            }
        }
        //end if
        $previous_token = $phpcs_file->find_previous(T_WHITESPACE, $stack_ptr - 1, null, true, null, true);
        if ($previous_token !== false) {
            // A list of tokens that indicate that the token is not
            // part of an arithmetic operation.
            $invalid_tokens = [T_COMMA => true, T_COLON => true, T_OPEN_PARENTHESIS => true, T_OPEN_SQUARE_BRACKET => true, T_OPEN_CURLY_BRACKET => true, T_OPEN_SHORT_ARRAY => true, T_CASE => true, T_EXIT => true, T_MATCH_ARROW => true];
            if (isset($invalid_tokens[$tokens[$previous_token]['code']]) === true) {
                return;
            }
        }
        if ($tokens[$stack_ptr]['code'] === T_BITWISE_OR && isset($tokens[$stack_ptr]['nested_parenthesis']) === true) {
            $brackets = $tokens[$stack_ptr]['nested_parenthesis'];
            $last_bracket = array_pop($brackets);
            if (isset($tokens[$last_bracket]['parenthesis_owner']) === true && $tokens[$tokens[$last_bracket]['parenthesis_owner']]['code'] === T_CATCH) {
                // This is a pipe character inside a catch statement, so it is acting
                // as an exception type separator and not an arithmetic operation.
                return;
            }
        }
        // Tokens that are allowed inside a bracketed operation.
        $allowed = [T_VARIABLE, T_LNUMBER, T_DNUMBER, T_STRING, T_WHITESPACE, T_NS_SEPARATOR, T_THIS, T_SELF, T_STATIC, T_PARENT, T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_OPEN_SQUARE_BRACKET, T_CLOSE_SQUARE_BRACKET, T_MODULUS, T_NONE, T_BITWISE_NOT];
        $allowed += Tokens::$operators;
        $last_bracket = false;
        if (isset($tokens[$stack_ptr]['nested_parenthesis']) === true) {
            $parenthesis = array_reverse($tokens[$stack_ptr]['nested_parenthesis'], true);
            foreach ($parenthesis as $bracket => $end_bracket) {
                $prev_token = $phpcs_file->find_previous(T_WHITESPACE, $bracket - 1, null, true);
                $prev_code = $tokens[$prev_token]['code'];
                if ($prev_code === T_ISSET) {
                    // This operation is inside an isset() call, but has
                    // no bracket of it's own.
                    break;
                }
                if ($prev_code === T_STRING || $prev_code === T_SWITCH || $prev_code === T_MATCH) {
                    // We allow simple operations to not be bracketed.
                    // For example, ceil($one / $two).
                    for ($prev = $stack_ptr - 1; $prev > $bracket; $prev--) {
                        if (in_array($tokens[$prev]['code'], $allowed, true) === true) {
                            continue;
                        }
                        if ($tokens[$prev]['code'] === T_CLOSE_PARENTHESIS) {
                            $prev = $tokens[$prev]['parenthesis_opener'];
                        } else {
                            break;
                        }
                    }
                    if ($prev !== $bracket) {
                        break;
                    }
                    for ($next = $stack_ptr + 1; $next < $end_bracket; $next++) {
                        if (in_array($tokens[$next]['code'], $allowed, true) === true) {
                            continue;
                        }
                        if ($tokens[$next]['code'] === T_OPEN_PARENTHESIS) {
                            $next = $tokens[$next]['parenthesis_closer'];
                        } else {
                            break;
                        }
                    }
                    if ($next !== $end_bracket) {
                        break;
                    }
                }
                //end if
                if (in_array($prev_code, Tokens::$scope_openers, true) === true) {
                    // This operation is inside a control structure like FOREACH
                    // or IF, but has no bracket of it's own.
                    // The only control structures allowed to do this are SWITCH and MATCH.
                    if ($prev_code !== T_SWITCH && $prev_code !== T_MATCH) {
                        break;
                    }
                }
                if ($prev_code === T_OPEN_PARENTHESIS) {
                    // These are two open parenthesis in a row. If the current
                    // one doesn't enclose the operator, go to the previous one.
                    if ($end_bracket < $stack_ptr) {
                        continue;
                    }
                }
                $last_bracket = $bracket;
                break;
            }
            //end foreach
        }
        //end if
        if ($last_bracket === false) {
            // It is not in a bracketed statement at all.
            $this->add_missing_brackets_error($phpcs_file, $stack_ptr);
            return;
        }
        if ($tokens[$last_bracket]['parenthesis_closer'] < $stack_ptr) {
            // There are a set of brackets in front of it that don't include it.
            $this->add_missing_brackets_error($phpcs_file, $stack_ptr);
            return;
        }
        // We are enclosed in a set of bracket, so the last thing to
        // check is that we are not also enclosed in square brackets
        // like this: ($array[$index + 1]), which is invalid.
        $brackets = [T_OPEN_SQUARE_BRACKET, T_CLOSE_SQUARE_BRACKET];
        $square_bracket = $phpcs_file->find_previous($brackets, $stack_ptr - 1, $last_bracket);
        if ($square_bracket !== false && $tokens[$square_bracket]['code'] === T_OPEN_SQUARE_BRACKET) {
            $close_square_bracket = $phpcs_file->find_next($brackets, $stack_ptr + 1);
            if ($close_square_bracket !== false && $tokens[$close_square_bracket]['code'] === T_CLOSE_SQUARE_BRACKET) {
                $this->add_missing_brackets_error($phpcs_file, $stack_ptr);
            }
        }
    }
    //end process()
    /**
     * Add and fix the missing brackets error.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token in the
     *                                               stack passed in $tokens.
     *
     * @return void
     */
    public function add_missing_brackets_error($phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        $allowed = [T_VARIABLE => true, T_LNUMBER => true, T_DNUMBER => true, T_STRING => true, T_CONSTANT_ENCAPSED_STRING => true, T_DOUBLE_QUOTED_STRING => true, T_WHITESPACE => true, T_NS_SEPARATOR => true, T_THIS => true, T_SELF => true, T_STATIC => true, T_OBJECT_OPERATOR => true, T_NULLSAFE_OBJECT_OPERATOR => true, T_DOUBLE_COLON => true, T_MODULUS => true, T_ISSET => true, T_ARRAY => true, T_NONE => true, T_BITWISE_NOT => true];
        // Find the first token in the expression.
        for ($before = $stack_ptr - 1; $before > 0; $before--) {
            // Special case for plus operators because we can't tell if they are used
            // for addition or string contact. So assume string concat to be safe.
            if ($phpcs_file->tokenizer_type === 'JS' && $tokens[$before]['code'] === T_PLUS) {
                break;
            }
            if (isset(Tokens::$empty_tokens[$tokens[$before]['code']]) === true) {
                continue;
            }
            if (isset(Tokens::$operators[$tokens[$before]['code']]) === true) {
                continue;
            }
            if (isset(Tokens::$cast_tokens[$tokens[$before]['code']]) === true) {
                continue;
            }
            if (isset($allowed[$tokens[$before]['code']]) === true) {
                continue;
            }
            if ($tokens[$before]['code'] === T_CLOSE_PARENTHESIS) {
                $before = $tokens[$before]['parenthesis_opener'];
                continue;
            }
            if ($tokens[$before]['code'] === T_CLOSE_SQUARE_BRACKET) {
                $before = $tokens[$before]['bracket_opener'];
                continue;
            }
            if ($tokens[$before]['code'] === T_CLOSE_SHORT_ARRAY) {
                $before = $tokens[$before]['bracket_opener'];
                continue;
            }
            break;
        }
        //end for
        $before = $phpcs_file->find_next(Tokens::$empty_tokens, $before + 1, null, true);
        // A few extra tokens are allowed to be on the right side of the expression.
        $allowed[T_EQUAL] = true;
        $allowed[T_NEW] = true;
        // Find the last token in the expression.
        for ($after = $stack_ptr + 1; $after < $phpcs_file->num_tokens; $after++) {
            // Special case for plus operators because we can't tell if they are used
            // for addition or string concat. So assume string concat to be safe.
            if ($phpcs_file->tokenizer_type === 'JS' && $tokens[$after]['code'] === T_PLUS) {
                break;
            }
            if (isset(Tokens::$empty_tokens[$tokens[$after]['code']]) === true) {
                continue;
            }
            if (isset(Tokens::$operators[$tokens[$after]['code']]) === true) {
                continue;
            }
            if (isset(Tokens::$cast_tokens[$tokens[$after]['code']]) === true) {
                continue;
            }
            if (isset($allowed[$tokens[$after]['code']]) === true) {
                continue;
            }
            if ($tokens[$after]['code'] === T_OPEN_PARENTHESIS) {
                $after = $tokens[$after]['parenthesis_closer'];
                continue;
            }
            if ($tokens[$after]['code'] === T_OPEN_SQUARE_BRACKET) {
                $after = $tokens[$after]['bracket_closer'];
                continue;
            }
            if ($tokens[$after]['code'] === T_OPEN_SHORT_ARRAY) {
                $after = $tokens[$after]['bracket_closer'];
                continue;
            }
            break;
        }
        //end for
        $after = $phpcs_file->find_previous(Tokens::$empty_tokens, $after - 1, null, true);
        $error = 'Operation must be bracketed';
        if ($before === $after || $before === $stack_ptr || $after === $stack_ptr) {
            $phpcs_file->add_error($error, $stack_ptr, 'MissingBrackets');
            return;
        }
        $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'MissingBrackets');
        if ($fix === true) {
            // Can only fix this error if both tokens are available for fixing.
            // Adding one bracket without the other will create parse errors.
            $phpcs_file->fixer->begin_changeset();
            $phpcs_file->fixer->replace_token($before, '(' . $tokens[$before]['content']);
            $phpcs_file->fixer->replace_token($after, $tokens[$after]['content'] . ')');
            $phpcs_file->fixer->end_changeset();
        }
    }
    //end addMissingBracketsError()
}
//end class