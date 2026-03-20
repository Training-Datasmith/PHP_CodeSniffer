<?php

declare (strict_types=1);
/**
 * Ensures function calls are formatted correctly.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\PEAR\Sniffs\Functions;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Function_Call_Signature_Sniff implements Sniff
{
    /**
     * A list of tokenizers this sniff supports.
     *
     * @var array
     */
    public $supported_tokenizers = ['PHP', 'JS'];
    /**
     * The number of spaces code should be indented.
     *
     * @var integer
     */
    public $indent = 4;
    /**
     * If TRUE, multiple arguments can be defined per line in a multi-line call.
     *
     * @var boolean
     */
    public $allow_multiple_arguments = true;
    /**
     * How many spaces should follow the opening bracket.
     *
     * @var integer
     */
    public $required_spaces_after_open = 0;
    /**
     * How many spaces should precede the closing bracket.
     *
     * @var integer
     */
    public $required_spaces_before_close = 0;
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        $tokens = Tokens::$function_name_tokens;
        $tokens[] = T_VARIABLE;
        $tokens[] = T_CLOSE_CURLY_BRACKET;
        $tokens[] = T_CLOSE_SQUARE_BRACKET;
        $tokens[] = T_CLOSE_PARENTHESIS;
        return $tokens;
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
        $this->required_spaces_after_open = (int) $this->required_spaces_after_open;
        $this->required_spaces_before_close = (int) $this->required_spaces_before_close;
        $tokens = $phpcs_file->get_tokens();
        if ($tokens[$stack_ptr]['code'] === T_CLOSE_CURLY_BRACKET && isset($tokens[$stack_ptr]['scope_condition']) === true) {
            // Not a function call.
            return;
        }
        // Find the next non-empty token.
        $open_bracket = $phpcs_file->find_next(Tokens::$empty_tokens, $stack_ptr + 1, null, true);
        if ($tokens[$open_bracket]['code'] !== T_OPEN_PARENTHESIS) {
            // Not a function call.
            return;
        }
        if (isset($tokens[$open_bracket]['parenthesis_closer']) === false) {
            // Not a function call.
            return;
        }
        // Find the previous non-empty token.
        $search = Tokens::$empty_tokens;
        $search[] = T_BITWISE_AND;
        $previous = $phpcs_file->find_previous($search, $stack_ptr - 1, null, true);
        if ($tokens[$previous]['code'] === T_FUNCTION) {
            // It's a function definition, not a function call.
            return;
        }
        $close_bracket = $tokens[$open_bracket]['parenthesis_closer'];
        if ($stack_ptr + 1 !== $open_bracket) {
            // Checking this: $value = my_function[*](...).
            $error = 'Space before opening parenthesis of function call prohibited';
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'SpaceBeforeOpenBracket');
            if ($fix === true) {
                $phpcs_file->fixer->begin_changeset();
                for ($i = $stack_ptr + 1; $i < $open_bracket; $i++) {
                    $phpcs_file->fixer->replace_token($i, '');
                }
                // Modify the bracket as well to ensure a conflict if the bracket
                // has been changed in some way by another sniff.
                $phpcs_file->fixer->replace_token($open_bracket, '(');
                $phpcs_file->fixer->end_changeset();
            }
        }
        $next = $phpcs_file->find_next(T_WHITESPACE, $close_bracket + 1, null, true);
        if ($tokens[$next]['code'] === T_SEMICOLON) {
            if (isset(Tokens::$empty_tokens[$tokens[$close_bracket + 1]['code']]) === true) {
                $error = 'Space after closing parenthesis of function call prohibited';
                $fix = $phpcs_file->add_fixable_error($error, $close_bracket, 'SpaceAfterCloseBracket');
                if ($fix === true) {
                    $phpcs_file->fixer->begin_changeset();
                    for ($i = $close_bracket + 1; $i < $next; $i++) {
                        $phpcs_file->fixer->replace_token($i, '');
                    }
                    // Modify the bracket as well to ensure a conflict if the bracket
                    // has been changed in some way by another sniff.
                    $phpcs_file->fixer->replace_token($close_bracket, ')');
                    $phpcs_file->fixer->end_changeset();
                }
            }
        }
        // Check if this is a single line or multi-line function call.
        if ($this->is_multi_line_call($phpcs_file, $stack_ptr, $open_bracket, $tokens) === true) {
            $this->process_multi_line_call($phpcs_file, $stack_ptr, $open_bracket, $tokens);
        } else {
            $this->process_single_line_call($phpcs_file, $stack_ptr, $open_bracket, $tokens);
        }
    }
    //end process()
    /**
     * Determine if this is a multi-line function call.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile   The file being scanned.
     * @param int                         $stackPtr    The position of the current token
     *                                                 in the stack passed in $tokens.
     * @param int                         $openBracket The position of the opening bracket
     *                                                 in the stack passed in $tokens.
     * @param array                       $tokens      The stack of tokens that make up
     *                                                 the file.
     *
     * @return bool
     */
    public function is_multi_line_call(File $phpcs_file, $stack_ptr, $open_bracket, array $tokens)
    {
        $close_bracket = $tokens[$open_bracket]['parenthesis_closer'];
        if ($tokens[$open_bracket]['line'] !== $tokens[$close_bracket]['line']) {
            return true;
        }
        return false;
    }
    //end isMultiLineCall()
    /**
     * Processes single-line calls.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile   The file being scanned.
     * @param int                         $stackPtr    The position of the current token
     *                                                 in the stack passed in $tokens.
     * @param int                         $openBracket The position of the opening bracket
     *                                                 in the stack passed in $tokens.
     * @param array                       $tokens      The stack of tokens that make up
     *                                                 the file.
     *
     * @return void
     */
    public function process_single_line_call(File $phpcs_file, $stack_ptr, $open_bracket, array $tokens)
    {
        $closer = $tokens[$open_bracket]['parenthesis_closer'];
        if ($open_bracket === $closer - 1) {
            return;
        }
        // If the function call has no arguments or comments, enforce 0 spaces.
        $next = $phpcs_file->find_next(T_WHITESPACE, $open_bracket + 1, $closer, true);
        if ($next === false) {
            $required_spaces_after_open = 0;
            $required_spaces_before_close = 0;
        } else {
            $required_spaces_after_open = $this->required_spaces_after_open;
            $required_spaces_before_close = $this->required_spaces_before_close;
        }
        if ($required_spaces_after_open === 0 && $tokens[$open_bracket + 1]['code'] === T_WHITESPACE) {
            // Checking this: $value = my_function([*]...).
            $error = 'Space after opening parenthesis of function call prohibited';
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'SpaceAfterOpenBracket');
            if ($fix === true) {
                $phpcs_file->fixer->replace_token($open_bracket + 1, '');
            }
        } elseif ($required_spaces_after_open > 0) {
            $space_after_open = 0;
            if ($tokens[$open_bracket + 1]['code'] === T_WHITESPACE) {
                $space_after_open = $tokens[$open_bracket + 1]['length'];
            }
            if ($space_after_open !== $required_spaces_after_open) {
                $error = 'Expected %s spaces after opening parenthesis; %s found';
                $data = [$required_spaces_after_open, $space_after_open];
                $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'SpaceAfterOpenBracket', $data);
                if ($fix === true) {
                    $padding = str_repeat(' ', $required_spaces_after_open);
                    if ($space_after_open === 0) {
                        $phpcs_file->fixer->add_content($open_bracket, $padding);
                    } else {
                        $phpcs_file->fixer->replace_token($open_bracket + 1, $padding);
                    }
                }
            }
        }
        //end if
        // Checking this: $value = my_function(...[*]).
        $space_before_close = 0;
        $prev = $phpcs_file->find_previous(T_WHITESPACE, $closer - 1, $open_bracket, true);
        if ($tokens[$prev]['code'] === T_END_HEREDOC || $tokens[$prev]['code'] === T_END_NOWDOC) {
            // Need a newline after these tokens, so ignore this rule.
            return;
        }
        if ($tokens[$prev]['line'] !== $tokens[$closer]['line']) {
            $space_before_close = 'newline';
        } elseif ($tokens[$closer - 1]['code'] === T_WHITESPACE) {
            $space_before_close = $tokens[$closer - 1]['length'];
        }
        if ($space_before_close !== $required_spaces_before_close) {
            $error = 'Expected %s spaces before closing parenthesis; %s found';
            $data = [$required_spaces_before_close, $space_before_close];
            $fix = $phpcs_file->add_fixable_error($error, $closer, 'SpaceBeforeCloseBracket', $data);
            if ($fix === true) {
                $padding = str_repeat(' ', $required_spaces_before_close);
                if ($space_before_close === 0) {
                    $phpcs_file->fixer->add_content_before($closer, $padding);
                } elseif ($space_before_close === 'newline') {
                    $phpcs_file->fixer->begin_changeset();
                    $closing_content = ')';
                    $next = $phpcs_file->find_next(T_WHITESPACE, $closer + 1, null, true);
                    if ($tokens[$next]['code'] === T_SEMICOLON) {
                        $closing_content .= ';';
                        for ($i = $closer + 1; $i <= $next; $i++) {
                            $phpcs_file->fixer->replace_token($i, '');
                        }
                    }
                    // We want to jump over any whitespace or inline comment and
                    // move the closing parenthesis after any other token.
                    $prev = $closer - 1;
                    while (isset(Tokens::$empty_tokens[$tokens[$prev]['code']]) === true) {
                        if ($tokens[$prev]['code'] === T_COMMENT && strpos($tokens[$prev]['content'], '*/') !== false) {
                            break;
                        }
                        $prev--;
                    }
                    $phpcs_file->fixer->add_content($prev, $padding . $closing_content);
                    $prev_non_whitespace = $phpcs_file->find_previous(T_WHITESPACE, $closer - 1, null, true);
                    for ($i = $prev_non_whitespace + 1; $i <= $closer; $i++) {
                        $phpcs_file->fixer->replace_token($i, '');
                    }
                    $phpcs_file->fixer->end_changeset();
                } else {
                    $phpcs_file->fixer->replace_token($closer - 1, $padding);
                }
                //end if
            }
            //end if
        }
        //end if
    }
    //end processSingleLineCall()
    /**
     * Processes multi-line calls.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile   The file being scanned.
     * @param int                         $stackPtr    The position of the current token
     *                                                 in the stack passed in $tokens.
     * @param int                         $openBracket The position of the opening bracket
     *                                                 in the stack passed in $tokens.
     * @param array                       $tokens      The stack of tokens that make up
     *                                                 the file.
     *
     * @return void
     */
    public function process_multi_line_call(File $phpcs_file, $stack_ptr, $open_bracket, array $tokens)
    {
        // We need to work out how far indented the function
        // call itself is, so we can work out how far to
        // indent the arguments.
        $first = $phpcs_file->find_first_on_line(T_WHITESPACE, $stack_ptr, true);
        if ($tokens[$first]['code'] === T_CONSTANT_ENCAPSED_STRING && $tokens[$first - 1]['code'] === T_CONSTANT_ENCAPSED_STRING) {
            // We are in a multi-line string, so find the start and use
            // the indent from there.
            $prev = $phpcs_file->find_previous(T_CONSTANT_ENCAPSED_STRING, $first - 2, null, true);
            $first = $phpcs_file->find_first_on_line(Tokens::$empty_tokens, $prev, true);
            if ($first === false) {
                $first = $prev + 1;
            }
        }
        $found_function_indent = 0;
        if ($first !== false) {
            if ($tokens[$first]['code'] === T_INLINE_HTML || $tokens[$first]['code'] === T_CONSTANT_ENCAPSED_STRING && $tokens[$first - 1]['code'] === T_CONSTANT_ENCAPSED_STRING) {
                $trimmed = ltrim($tokens[$first]['content']);
                if ($trimmed === '') {
                    $found_function_indent = strlen($tokens[$first]['content']);
                } else {
                    $found_function_indent = strlen($tokens[$first]['content']) - strlen($trimmed);
                }
            } else {
                $found_function_indent = $tokens[$first]['column'] - 1;
            }
        }
        // Make sure the function indent is divisible by the indent size.
        // We round down here because this accounts for times when the
        // surrounding code is indented a little too far in, and not correctly
        // at a tab stop. Without this, the function will be indented a further
        // $indent spaces to the right.
        $function_indent = (int) (floor($found_function_indent / $this->indent) * $this->indent);
        $adjustment = 0;
        if ($found_function_indent !== $function_indent) {
            $error = 'Opening statement of multi-line function call not indented correctly; expected %s spaces but found %s';
            $data = [$function_indent, $found_function_indent];
            $fix = $phpcs_file->add_fixable_error($error, $first, 'OpeningIndent', $data);
            if ($fix === true) {
                $adjustment = $function_indent - $found_function_indent;
                $padding = str_repeat(' ', $function_indent);
                if ($found_function_indent === 0) {
                    $phpcs_file->fixer->add_content_before($first, $padding);
                } elseif ($tokens[$first]['code'] === T_INLINE_HTML) {
                    $new_content = $padding . ltrim($tokens[$first]['content']);
                    $phpcs_file->fixer->replace_token($first, $new_content);
                } else {
                    $phpcs_file->fixer->replace_token($first - 1, $padding);
                }
            }
        }
        //end if
        $next = $phpcs_file->find_next(Tokens::$empty_tokens, $open_bracket + 1, null, true);
        if ($tokens[$next]['line'] === $tokens[$open_bracket]['line']) {
            $error = 'Opening parenthesis of a multi-line function call must be the last content on the line';
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'ContentAfterOpenBracket');
            if ($fix === true) {
                $phpcs_file->fixer->add_content($open_bracket, $phpcs_file->eol_char . str_repeat(' ', $found_function_indent + $this->indent));
            }
        }
        $close_bracket = $tokens[$open_bracket]['parenthesis_closer'];
        $prev = $phpcs_file->find_previous(T_WHITESPACE, $close_bracket - 1, null, true);
        if ($tokens[$prev]['line'] === $tokens[$close_bracket]['line']) {
            $error = 'Closing parenthesis of a multi-line function call must be on a line by itself';
            $fix = $phpcs_file->add_fixable_error($error, $close_bracket, 'CloseBracketLine');
            if ($fix === true) {
                $phpcs_file->fixer->add_content_before($close_bracket, $phpcs_file->eol_char . str_repeat(' ', $found_function_indent + $this->indent));
            }
        }
        // Each line between the parenthesis should be indented n spaces.
        $last_line = $tokens[$open_bracket]['line'] - 1;
        $arg_start = null;
        $arg_end = null;
        // Start processing at the first argument.
        $i = $phpcs_file->find_next(T_WHITESPACE, $open_bracket + 1, null, true);
        if ($tokens[$i]['line'] > $tokens[$open_bracket]['line'] + 1) {
            $error = 'The first argument in a multi-line function call must be on the line after the opening parenthesis';
            $fix = $phpcs_file->add_fixable_error($error, $i, 'FirstArgumentPosition');
            if ($fix === true) {
                $phpcs_file->fixer->begin_changeset();
                for ($x = $open_bracket + 1; $x < $i; $x++) {
                    if ($tokens[$x]['line'] === $tokens[$open_bracket]['line']) {
                        continue;
                    }
                    if ($tokens[$x]['line'] === $tokens[$i]['line']) {
                        break;
                    }
                    $phpcs_file->fixer->replace_token($x, '');
                }
                $phpcs_file->fixer->end_changeset();
            }
        }
        //end if
        $i = $phpcs_file->find_next(Tokens::$empty_tokens, $open_bracket + 1, null, true);
        if ($tokens[$i - 1]['code'] === T_WHITESPACE && $tokens[$i - 1]['line'] === $tokens[$i]['line']) {
            // Make sure we check the indent.
            $i--;
        }
        for ($i; $i < $close_bracket; $i++) {
            if ($i > $arg_start && $i < $arg_end) {
                $in_arg = true;
            } else {
                $in_arg = false;
            }
            if ($tokens[$i]['line'] !== $last_line) {
                $last_line = $tokens[$i]['line'];
                // Ignore heredoc indentation.
                if (isset(Tokens::$heredoc_tokens[$tokens[$i]['code']]) === true) {
                    continue;
                }
                // Ignore multi-line string indentation.
                if (isset(Tokens::$string_tokens[$tokens[$i]['code']]) === true && $tokens[$i]['code'] === $tokens[$i - 1]['code']) {
                    continue;
                }
                // Ignore inline HTML.
                if ($tokens[$i]['code'] === T_INLINE_HTML) {
                    continue;
                }
                if ($tokens[$i]['line'] !== $tokens[$open_bracket]['line']) {
                    // We changed lines, so this should be a whitespace indent token, but first make
                    // sure it isn't a blank line because we don't need to check indent unless there
                    // is actually some code to indent.
                    if ($tokens[$i]['code'] === T_WHITESPACE) {
                        $next_code = $phpcs_file->find_next(T_WHITESPACE, $i + 1, $close_bracket + 1, true);
                        if ($tokens[$next_code]['line'] !== $last_line) {
                            if ($in_arg === false) {
                                $error = 'Empty lines are not allowed in multi-line function calls';
                                $fix = $phpcs_file->add_fixable_error($error, $i, 'EmptyLine');
                                if ($fix === true) {
                                    $phpcs_file->fixer->replace_token($i, '');
                                }
                            }
                            continue;
                        }
                    } else {
                        $next_code = $i;
                    }
                    if ($tokens[$next_code]['line'] === $tokens[$close_bracket]['line']) {
                        // Closing brace needs to be indented to the same level
                        // as the function call.
                        $in_arg = false;
                        $expected_indent = $found_function_indent + $adjustment;
                    } else {
                        $expected_indent = $found_function_indent + $this->indent + $adjustment;
                    }
                    if ($tokens[$i]['code'] !== T_WHITESPACE && $tokens[$i]['code'] !== T_DOC_COMMENT_WHITESPACE) {
                        // Just check if it is a multi-line block comment. If so, we can
                        // calculate the indent from the whitespace before the content.
                        if ($tokens[$i]['code'] === T_COMMENT && $tokens[$i - 1]['code'] === T_COMMENT) {
                            $trimmed_length = strlen(ltrim($tokens[$i]['content']));
                            if ($trimmed_length === 0) {
                                // This is a blank comment line, so indenting it is
                                // pointless.
                                continue;
                            }
                            $found_indent = strlen($tokens[$i]['content']) - $trimmed_length;
                        } else {
                            $found_indent = 0;
                        }
                    } else {
                        $found_indent = $tokens[$i]['length'];
                    }
                    if ($found_indent < $expected_indent || $in_arg === false && $expected_indent !== $found_indent) {
                        $error = 'Multi-line function call not indented correctly; expected %s spaces but found %s';
                        $data = [$expected_indent, $found_indent];
                        $fix = $phpcs_file->add_fixable_error($error, $i, 'Indent', $data);
                        if ($fix === true) {
                            $phpcs_file->fixer->begin_changeset();
                            $padding = str_repeat(' ', $expected_indent);
                            if ($found_indent === 0) {
                                $phpcs_file->fixer->add_content_before($i, $padding);
                                if (isset($tokens[$i]['scope_opener']) === true) {
                                    $phpcs_file->fixer->change_code_block_indent($i, $tokens[$i]['scope_closer'], $expected_indent);
                                }
                            } else {
                                if ($tokens[$i]['code'] === T_COMMENT) {
                                    $comment = $padding . ltrim($tokens[$i]['content']);
                                    $phpcs_file->fixer->replace_token($i, $comment);
                                } else {
                                    $phpcs_file->fixer->replace_token($i, $padding);
                                }
                                if (isset($tokens[$i + 1]['scope_opener']) === true) {
                                    $phpcs_file->fixer->change_code_block_indent($i + 1, $tokens[$i + 1]['scope_closer'], $expected_indent - $found_indent);
                                }
                            }
                            $phpcs_file->fixer->end_changeset();
                        }
                        //end if
                    }
                    //end if
                } else {
                    $next_code = $i;
                }
                //end if
                if ($in_arg === false) {
                    $arg_start = $next_code;
                    $arg_end = $phpcs_file->find_end_of_statement($next_code, [T_COLON]);
                }
            }
            //end if
            // If we are within an argument we should be ignoring commas
            // as these are not signalling the end of an argument.
            if ($in_arg === false && $tokens[$i]['code'] === T_COMMA) {
                $next = $phpcs_file->find_next(Tokens::$empty_tokens, $i + 1, $close_bracket, true);
                if ($next === false) {
                    continue;
                }
                if ($this->allow_multiple_arguments === false) {
                    // Comma has to be the last token on the line.
                    if ($tokens[$i]['line'] === $tokens[$next]['line']) {
                        $error = 'Only one argument is allowed per line in a multi-line function call';
                        $fix = $phpcs_file->add_fixable_error($error, $next, 'MultipleArguments');
                        if ($fix === true) {
                            $phpcs_file->fixer->begin_changeset();
                            for ($x = $next - 1; $x > $i; $x--) {
                                if ($tokens[$x]['code'] !== T_WHITESPACE) {
                                    break;
                                }
                                $phpcs_file->fixer->replace_token($x, '');
                            }
                            $phpcs_file->fixer->add_content_before($next, $phpcs_file->eol_char . str_repeat(' ', $found_function_indent + $this->indent));
                            $phpcs_file->fixer->end_changeset();
                        }
                    }
                }
                //end if
                $arg_start = $next;
                $arg_end = $phpcs_file->find_end_of_statement($next, [T_COLON]);
            }
            //end if
        }
        //end for
    }
    //end processMultiLineCall()
}
//end class