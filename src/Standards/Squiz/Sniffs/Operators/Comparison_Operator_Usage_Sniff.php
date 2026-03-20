<?php

declare (strict_types=1);
/**
 * A Sniff to enforce the use of IDENTICAL type operators rather than EQUAL operators.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\Operators;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Comparison_Operator_Usage_Sniff implements Sniff
{
    /**
     * A list of tokenizers this sniff supports.
     *
     * @var array
     */
    public $supported_tokenizers = ['PHP', 'JS'];
    /**
     * A list of valid comparison operators.
     *
     * @var array
     */
    private static $valid_ops = [T_IS_IDENTICAL => true, T_IS_NOT_IDENTICAL => true, T_LESS_THAN => true, T_GREATER_THAN => true, T_IS_GREATER_OR_EQUAL => true, T_IS_SMALLER_OR_EQUAL => true, T_INSTANCEOF => true];
    /**
     * A list of invalid operators with their alternatives.
     *
     * @var array<int, string>
     */
    private static $invalid_ops = ['PHP' => [T_IS_EQUAL => '===', T_IS_NOT_EQUAL => '!==', T_BOOLEAN_NOT => '=== FALSE'], 'JS' => [T_IS_EQUAL => '===', T_IS_NOT_EQUAL => '!==']];
    /**
     * Registers the token types that this sniff wishes to listen to.
     *
     * @return array
     */
    public function register()
    {
        return [T_IF, T_ELSEIF, T_INLINE_THEN, T_WHILE, T_FOR];
    }
    //end register()
    /**
     * Process the tokens that this sniff is listening for.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file where the token was found.
     * @param int                         $stackPtr  The position in the stack where the token
     *                                               was found.
     *
     * @return void
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        $tokenizer = $phpcs_file->tokenizer_type;
        if ($tokens[$stack_ptr]['code'] === T_INLINE_THEN) {
            $end = $phpcs_file->find_previous(Tokens::$empty_tokens, $stack_ptr - 1, null, true);
            if ($tokens[$end]['code'] !== T_CLOSE_PARENTHESIS) {
                // This inline IF statement does not have its condition
                // bracketed, so we need to guess where it starts.
                for ($i = $end - 1; $i >= 0; $i--) {
                    if ($tokens[$i]['code'] === T_SEMICOLON) {
                        // Stop here as we assume it is the end
                        // of the previous statement.
                        break;
                    } elseif ($tokens[$i]['code'] === T_OPEN_TAG) {
                        // Stop here as this is the start of the file.
                        break;
                    } elseif ($tokens[$i]['code'] === T_CLOSE_CURLY_BRACKET) {
                        // Stop if this is the closing brace of
                        // a code block.
                        if (isset($tokens[$i]['scope_opener']) === true) {
                            break;
                        }
                    } elseif ($tokens[$i]['code'] === T_OPEN_CURLY_BRACKET) {
                        // Stop if this is the opening brace of
                        // a code block.
                        if (isset($tokens[$i]['scope_closer']) === true) {
                            break;
                        }
                    } elseif ($tokens[$i]['code'] === T_OPEN_PARENTHESIS) {
                        // Stop if this is the start of a pair of
                        // parentheses that surrounds the inline
                        // IF statement.
                        if (isset($tokens[$i]['parenthesis_closer']) === true && $tokens[$i]['parenthesis_closer'] >= $stack_ptr) {
                            break;
                        }
                    }
                    //end if
                }
                //end for
                $start = $phpcs_file->find_next(Tokens::$empty_tokens, $i + 1, null, true);
            } else {
                if (isset($tokens[$end]['parenthesis_opener']) === false) {
                    return;
                }
                $start = $tokens[$end]['parenthesis_opener'];
            }
            //end if
        } elseif ($tokens[$stack_ptr]['code'] === T_FOR) {
            if (isset($tokens[$stack_ptr]['parenthesis_opener']) === false) {
                return;
            }
            $opening_bracket = $tokens[$stack_ptr]['parenthesis_opener'];
            $closing_bracket = $tokens[$stack_ptr]['parenthesis_closer'];
            $start = $phpcs_file->find_next(T_SEMICOLON, $opening_bracket, $closing_bracket);
            $end = $phpcs_file->find_next(T_SEMICOLON, $start + 1, $closing_bracket);
            if ($start === false || $end === false) {
                return;
            }
        } else {
            if (isset($tokens[$stack_ptr]['parenthesis_opener']) === false) {
                return;
            }
            $start = $tokens[$stack_ptr]['parenthesis_opener'];
            $end = $tokens[$stack_ptr]['parenthesis_closer'];
        }
        //end if
        $required_ops = 0;
        $found_ops = 0;
        $found_booleans = 0;
        $last_non_empty = $start;
        for ($i = $start; $i <= $end; $i++) {
            $type = $tokens[$i]['code'];
            if (isset(self::$invalid_ops[$tokenizer][$type]) === true) {
                $error = 'Operator %s prohibited; use %s instead';
                $data = [$tokens[$i]['content'], self::$invalid_ops[$tokenizer][$type]];
                $phpcs_file->add_error($error, $i, 'NotAllowed', $data);
                $found_ops++;
            } elseif (isset(self::$valid_ops[$type]) === true) {
                $found_ops++;
            }
            if ($type === T_OPEN_PARENTHESIS && isset($tokens[$i]['parenthesis_closer']) === true && isset(Tokens::$function_name_tokens[$tokens[$last_non_empty]['code']]) === true) {
                $i = $tokens[$i]['parenthesis_closer'];
                $last_non_empty = $i;
                continue;
            }
            if ($tokens[$i]['code'] === T_TRUE || $tokens[$i]['code'] === T_FALSE) {
                $found_booleans++;
            }
            if ($phpcs_file->tokenizer_type !== 'JS' && ($tokens[$i]['code'] === T_BOOLEAN_AND || $tokens[$i]['code'] === T_BOOLEAN_OR)) {
                $required_ops++;
                // When the instanceof operator is used with another operator
                // like ===, you can get more ops than are required.
                if ($found_ops > $required_ops) {
                    $found_ops = $required_ops;
                }
                // If we get to here and we have not found the right number of
                // comparison operators, then we must have had an implicit
                // true operation i.e., if ($a) instead of the required
                // if ($a === true), so let's add an error.
                if ($required_ops !== $found_ops) {
                    $error = 'Implicit true comparisons prohibited; use === TRUE instead';
                    $phpcs_file->add_error($error, $stack_ptr, 'ImplicitTrue');
                    $found_ops++;
                }
            }
            if (isset(Tokens::$empty_tokens[$type]) === false) {
                $last_non_empty = $i;
            }
        }
        //end for
        $required_ops++;
        if ($phpcs_file->tokenizer_type !== 'JS' && $found_ops < $required_ops && $required_ops !== $found_booleans) {
            $error = 'Implicit true comparisons prohibited; use === TRUE instead';
            $phpcs_file->add_error($error, $stack_ptr, 'ImplicitTrue');
        }
    }
    //end process()
}
//end class