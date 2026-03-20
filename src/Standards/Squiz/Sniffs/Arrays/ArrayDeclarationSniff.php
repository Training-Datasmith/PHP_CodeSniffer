<?php

declare (strict_types=1);
/**
 * Ensures that arrays conform to the array coding standard.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\Arrays;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Array_Declaration_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_ARRAY, T_OPEN_SHORT_ARRAY];
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
        if ($tokens[$stack_ptr]['code'] === T_ARRAY) {
            $phpcs_file->record_metric($stack_ptr, 'Short array syntax used', 'no');
            // Array keyword should be lower case.
            if ($tokens[$stack_ptr]['content'] !== strtolower($tokens[$stack_ptr]['content'])) {
                if ($tokens[$stack_ptr]['content'] === strtoupper($tokens[$stack_ptr]['content'])) {
                    $phpcs_file->record_metric($stack_ptr, 'Array keyword case', 'upper');
                } else {
                    $phpcs_file->record_metric($stack_ptr, 'Array keyword case', 'mixed');
                }
                $error = 'Array keyword should be lower case; expected "array" but found "%s"';
                $data = [$tokens[$stack_ptr]['content']];
                $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'NotLowerCase', $data);
                if ($fix === true) {
                    $phpcs_file->fixer->replace_token($stack_ptr, 'array');
                }
            } else {
                $phpcs_file->record_metric($stack_ptr, 'Array keyword case', 'lower');
            }
            $array_start = $tokens[$stack_ptr]['parenthesis_opener'];
            if (isset($tokens[$array_start]['parenthesis_closer']) === false) {
                return;
            }
            $array_end = $tokens[$array_start]['parenthesis_closer'];
            if ($array_start !== $stack_ptr + 1) {
                $error = 'There must be no space between the "array" keyword and the opening parenthesis';
                $next = $phpcs_file->find_next(T_WHITESPACE, $stack_ptr + 1, $array_start, true);
                if (isset(Tokens::$comment_tokens[$tokens[$next]['code']]) === true) {
                    // We don't have anywhere to put the comment, so don't attempt to fix it.
                    $phpcs_file->add_error($error, $stack_ptr, 'SpaceAfterKeyword');
                } else {
                    $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'SpaceAfterKeyword');
                    if ($fix === true) {
                        $phpcs_file->fixer->begin_changeset();
                        for ($i = $stack_ptr + 1; $i < $array_start; $i++) {
                            $phpcs_file->fixer->replace_token($i, '');
                        }
                        $phpcs_file->fixer->end_changeset();
                    }
                }
            }
        } else {
            $phpcs_file->record_metric($stack_ptr, 'Short array syntax used', 'yes');
            $array_start = $stack_ptr;
            $array_end = $tokens[$stack_ptr]['bracket_closer'];
        }
        //end if
        // Check for empty arrays.
        $content = $phpcs_file->find_next(T_WHITESPACE, $array_start + 1, $array_end + 1, true);
        if ($content === $array_end) {
            // Empty array, but if the brackets aren't together, there's a problem.
            if ($array_end - $array_start !== 1) {
                $error = 'Empty array declaration must have no space between the parentheses';
                $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'SpaceInEmptyArray');
                if ($fix === true) {
                    $phpcs_file->fixer->begin_changeset();
                    for ($i = $array_start + 1; $i < $array_end; $i++) {
                        $phpcs_file->fixer->replace_token($i, '');
                    }
                    $phpcs_file->fixer->end_changeset();
                }
            }
            // We can return here because there is nothing else to check. All code
            // below can assume that the array is not empty.
            return;
        }
        if ($tokens[$array_start]['line'] === $tokens[$array_end]['line']) {
            $this->process_single_line_array($phpcs_file, $stack_ptr, $array_start, $array_end);
        } else {
            $this->process_multi_line_array($phpcs_file, $stack_ptr, $array_start, $array_end);
        }
    }
    //end process()
    /**
     * Processes a single-line array definition.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile  The current file being checked.
     * @param int                         $stackPtr   The position of the current token
     *                                                in the stack passed in $tokens.
     * @param int                         $arrayStart The token that starts the array definition.
     * @param int                         $arrayEnd   The token that ends the array definition.
     *
     * @return void
     */
    public function process_single_line_array($phpcs_file, $stack_ptr, $array_start, $array_end)
    {
        $tokens = $phpcs_file->get_tokens();
        // Check if there are multiple values. If so, then it has to be multiple lines
        // unless it is contained inside a function call or condition.
        $value_count = 0;
        $commas = [];
        for ($i = $array_start + 1; $i < $array_end; $i++) {
            // Skip bracketed statements, like function calls.
            if ($tokens[$i]['code'] === T_OPEN_PARENTHESIS) {
                $i = $tokens[$i]['parenthesis_closer'];
                continue;
            }
            if ($tokens[$i]['code'] === T_COMMA) {
                // Before counting this comma, make sure we are not
                // at the end of the array.
                $next = $phpcs_file->find_next(T_WHITESPACE, $i + 1, $array_end, true);
                if ($next !== false) {
                    $value_count++;
                    $commas[] = $i;
                } else {
                    // There is a comma at the end of a single line array.
                    $error = 'Comma not allowed after last value in single-line array declaration';
                    $fix = $phpcs_file->add_fixable_error($error, $i, 'CommaAfterLast');
                    if ($fix === true) {
                        $phpcs_file->fixer->replace_token($i, '');
                    }
                }
            }
        }
        //end for
        // Now check each of the double arrows (if any).
        $next_arrow = $array_start;
        while (($next_arrow = $phpcs_file->find_next(T_DOUBLE_ARROW, $next_arrow + 1, $array_end)) !== false) {
            if ($tokens[$next_arrow - 1]['code'] !== T_WHITESPACE) {
                $content = $tokens[$next_arrow - 1]['content'];
                $error = 'Expected 1 space between "%s" and double arrow; 0 found';
                $data = [$content];
                $fix = $phpcs_file->add_fixable_error($error, $next_arrow, 'NoSpaceBeforeDoubleArrow', $data);
                if ($fix === true) {
                    $phpcs_file->fixer->add_content_before($next_arrow, ' ');
                }
            } else {
                $space_length = $tokens[$next_arrow - 1]['length'];
                if ($space_length !== 1) {
                    $content = $tokens[$next_arrow - 2]['content'];
                    $error = 'Expected 1 space between "%s" and double arrow; %s found';
                    $data = [$content, $space_length];
                    $fix = $phpcs_file->add_fixable_error($error, $next_arrow, 'SpaceBeforeDoubleArrow', $data);
                    if ($fix === true) {
                        $phpcs_file->fixer->replace_token($next_arrow - 1, ' ');
                    }
                }
            }
            //end if
            if ($tokens[$next_arrow + 1]['code'] !== T_WHITESPACE) {
                $content = $tokens[$next_arrow + 1]['content'];
                $error = 'Expected 1 space between double arrow and "%s"; 0 found';
                $data = [$content];
                $fix = $phpcs_file->add_fixable_error($error, $next_arrow, 'NoSpaceAfterDoubleArrow', $data);
                if ($fix === true) {
                    $phpcs_file->fixer->add_content($next_arrow, ' ');
                }
            } else {
                $space_length = $tokens[$next_arrow + 1]['length'];
                if ($space_length !== 1) {
                    $content = $tokens[$next_arrow + 2]['content'];
                    $error = 'Expected 1 space between double arrow and "%s"; %s found';
                    $data = [$content, $space_length];
                    $fix = $phpcs_file->add_fixable_error($error, $next_arrow, 'SpaceAfterDoubleArrow', $data);
                    if ($fix === true) {
                        $phpcs_file->fixer->replace_token($next_arrow + 1, ' ');
                    }
                }
            }
            //end if
        }
        //end while
        if ($value_count > 0) {
            $nested_parenthesis = false;
            if (isset($tokens[$stack_ptr]['nested_parenthesis']) === true) {
                $nested = $tokens[$stack_ptr]['nested_parenthesis'];
                $nested_parenthesis = array_pop($nested);
            }
            if ($nested_parenthesis === false || $tokens[$nested_parenthesis]['line'] !== $tokens[$stack_ptr]['line']) {
                $error = 'Array with multiple values cannot be declared on a single line';
                $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'SingleLineNotAllowed');
                if ($fix === true) {
                    $phpcs_file->fixer->begin_changeset();
                    $phpcs_file->fixer->add_newline($array_start);
                    if ($tokens[$array_end - 1]['code'] === T_WHITESPACE) {
                        $phpcs_file->fixer->replace_token($array_end - 1, $phpcs_file->eol_char);
                    } else {
                        $phpcs_file->fixer->add_newline_before($array_end);
                    }
                    $phpcs_file->fixer->end_changeset();
                }
                return;
            }
            // We have a multiple value array that is inside a condition or
            // function. Check its spacing is correct.
            foreach ($commas as $comma) {
                if ($tokens[$comma + 1]['code'] !== T_WHITESPACE) {
                    $content = $tokens[$comma + 1]['content'];
                    $error = 'Expected 1 space between comma and "%s"; 0 found';
                    $data = [$content];
                    $fix = $phpcs_file->add_fixable_error($error, $comma, 'NoSpaceAfterComma', $data);
                    if ($fix === true) {
                        $phpcs_file->fixer->add_content($comma, ' ');
                    }
                } else {
                    $space_length = $tokens[$comma + 1]['length'];
                    if ($space_length !== 1) {
                        $content = $tokens[$comma + 2]['content'];
                        $error = 'Expected 1 space between comma and "%s"; %s found';
                        $data = [$content, $space_length];
                        $fix = $phpcs_file->add_fixable_error($error, $comma, 'SpaceAfterComma', $data);
                        if ($fix === true) {
                            $phpcs_file->fixer->replace_token($comma + 1, ' ');
                        }
                    }
                }
                //end if
                if ($tokens[$comma - 1]['code'] === T_WHITESPACE) {
                    $content = $tokens[$comma - 2]['content'];
                    $space_length = $tokens[$comma - 1]['length'];
                    $error = 'Expected 0 spaces between "%s" and comma; %s found';
                    $data = [$content, $space_length];
                    $fix = $phpcs_file->add_fixable_error($error, $comma, 'SpaceBeforeComma', $data);
                    if ($fix === true) {
                        $phpcs_file->fixer->replace_token($comma - 1, '');
                    }
                }
            }
            //end foreach
        }
        //end if
    }
    //end processSingleLineArray()
    /**
     * Processes a multi-line array definition.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile  The current file being checked.
     * @param int                         $stackPtr   The position of the current token
     *                                                in the stack passed in $tokens.
     * @param int                         $arrayStart The token that starts the array definition.
     * @param int                         $arrayEnd   The token that ends the array definition.
     *
     * @return void
     */
    public function process_multi_line_array($phpcs_file, $stack_ptr, $array_start, $array_end)
    {
        $tokens = $phpcs_file->get_tokens();
        $keyword_start = $tokens[$stack_ptr]['column'];
        // Check the closing bracket is on a new line.
        $last_content = $phpcs_file->find_previous(T_WHITESPACE, $array_end - 1, $array_start, true);
        if ($tokens[$last_content]['line'] === $tokens[$array_end]['line']) {
            $error = 'Closing parenthesis of array declaration must be on a new line';
            $fix = $phpcs_file->add_fixable_error($error, $array_end, 'CloseBraceNewLine');
            if ($fix === true) {
                $phpcs_file->fixer->add_newline_before($array_end);
            }
        } elseif ($tokens[$array_end]['column'] !== $keyword_start) {
            // Check the closing bracket is lined up under the "a" in array.
            $expected = $keyword_start - 1;
            $found = $tokens[$array_end]['column'] - 1;
            $error = 'Closing parenthesis not aligned correctly; expected %s space(s) but found %s';
            $data = [$expected, $found];
            $fix = $phpcs_file->add_fixable_error($error, $array_end, 'CloseBraceNotAligned', $data);
            if ($fix === true) {
                if ($found === 0) {
                    $phpcs_file->fixer->add_content($array_end - 1, str_repeat(' ', $expected));
                } else {
                    $phpcs_file->fixer->replace_token($array_end - 1, str_repeat(' ', $expected));
                }
            }
        }
        //end if
        $key_used = false;
        $single_used = false;
        $indices = [];
        $max_length = 0;
        if ($tokens[$stack_ptr]['code'] === T_ARRAY) {
            $last_token = $tokens[$stack_ptr]['parenthesis_opener'];
        } else {
            $last_token = $stack_ptr;
        }
        // Find all the double arrows that reside in this scope.
        for ($next_token = $stack_ptr + 1; $next_token < $array_end; $next_token++) {
            // Skip bracketed statements, like function calls.
            if ($tokens[$next_token]['code'] === T_OPEN_PARENTHESIS && (isset($tokens[$next_token]['parenthesis_owner']) === false || $tokens[$next_token]['parenthesis_owner'] !== $stack_ptr)) {
                $next_token = $tokens[$next_token]['parenthesis_closer'];
                continue;
            }
            if ($tokens[$next_token]['code'] === T_ARRAY || $tokens[$next_token]['code'] === T_OPEN_SHORT_ARRAY || $tokens[$next_token]['code'] === T_CLOSURE || $tokens[$next_token]['code'] === T_FN || $tokens[$next_token]['code'] === T_MATCH) {
                // Let subsequent calls of this test handle nested arrays.
                if ($tokens[$last_token]['code'] !== T_DOUBLE_ARROW) {
                    $indices[] = ['value' => $next_token];
                    $last_token = $next_token;
                }
                if ($tokens[$next_token]['code'] === T_ARRAY) {
                    $next_token = $tokens[$tokens[$next_token]['parenthesis_opener']]['parenthesis_closer'];
                } elseif ($tokens[$next_token]['code'] === T_OPEN_SHORT_ARRAY) {
                    $next_token = $tokens[$next_token]['bracket_closer'];
                } else {
                    // T_CLOSURE.
                    $next_token = $tokens[$next_token]['scope_closer'];
                }
                $next_token = $phpcs_file->find_next(T_WHITESPACE, $next_token + 1, null, true);
                if ($tokens[$next_token]['code'] !== T_COMMA) {
                    $next_token--;
                } else {
                    $last_token = $next_token;
                }
                continue;
            }
            //end if
            if ($tokens[$next_token]['code'] !== T_DOUBLE_ARROW && $tokens[$next_token]['code'] !== T_COMMA) {
                continue;
            }
            $current_entry = [];
            if ($tokens[$next_token]['code'] === T_COMMA) {
                $stack_ptr_count = 0;
                if (isset($tokens[$stack_ptr]['nested_parenthesis']) === true) {
                    $stack_ptr_count = count($tokens[$stack_ptr]['nested_parenthesis']);
                }
                $comma_count = 0;
                if (isset($tokens[$next_token]['nested_parenthesis']) === true) {
                    $comma_count = count($tokens[$next_token]['nested_parenthesis']);
                    if ($tokens[$stack_ptr]['code'] === T_ARRAY) {
                        // Remove parenthesis that are used to define the array.
                        $comma_count--;
                    }
                }
                if ($comma_count > $stack_ptr_count) {
                    // This comma is inside more parenthesis than the ARRAY keyword,
                    // then there it is actually a comma used to separate arguments
                    // in a function call.
                    continue;
                }
                if ($key_used === true && $tokens[$last_token]['code'] === T_COMMA) {
                    $next_token = $phpcs_file->find_next(Tokens::$empty_tokens, $last_token + 1, null, true);
                    // Allow for PHP 7.4+ array unpacking within an array declaration.
                    if ($tokens[$next_token]['code'] !== T_ELLIPSIS) {
                        $error = 'No key specified for array entry; first entry specifies key';
                        $phpcs_file->add_error($error, $next_token, 'NoKeySpecified');
                        return;
                    }
                }
                if ($key_used === false) {
                    if ($tokens[$next_token - 1]['code'] === T_WHITESPACE) {
                        $prev = $phpcs_file->find_previous(Tokens::$empty_tokens, $next_token - 1, null, true);
                        if ($tokens[$prev]['code'] !== T_END_HEREDOC && $tokens[$prev]['code'] !== T_END_NOWDOC || $tokens[$next_token - 1]['line'] === $tokens[$next_token]['line']) {
                            if ($tokens[$next_token - 1]['content'] === $phpcs_file->eol_char) {
                                $space_length = 'newline';
                            } else {
                                $space_length = $tokens[$next_token - 1]['length'];
                            }
                            $error = 'Expected 0 spaces before comma; %s found';
                            $data = [$space_length];
                            // The error is only fixable if there is only whitespace between the tokens.
                            if ($prev === $phpcs_file->find_previous(T_WHITESPACE, $next_token - 1, null, true)) {
                                $fix = $phpcs_file->add_fixable_error($error, $next_token, 'SpaceBeforeComma', $data);
                                if ($fix === true) {
                                    $phpcs_file->fixer->replace_token($next_token - 1, '');
                                }
                            } else {
                                $phpcs_file->add_error($error, $next_token, 'SpaceBeforeComma', $data);
                            }
                        }
                    }
                    //end if
                    $value_content = $phpcs_file->find_next(Tokens::$empty_tokens, $last_token + 1, $next_token, true);
                    $indices[] = ['value' => $value_content];
                    $uses_array_unpacking = $phpcs_file->find_previous(Tokens::$empty_tokens, $next_token - 2, null, true);
                    if ($tokens[$uses_array_unpacking]['code'] !== T_ELLIPSIS) {
                        // Don't decide if an array is key => value indexed or not when PHP 7.4+ array unpacking is used.
                        $single_used = true;
                    }
                }
                //end if
                $last_token = $next_token;
                continue;
            }
            //end if
            if ($tokens[$next_token]['code'] === T_DOUBLE_ARROW) {
                if ($single_used === true) {
                    $error = 'Key specified for array entry; first entry has no key';
                    $phpcs_file->add_error($error, $next_token, 'KeySpecified');
                    return;
                }
                $current_entry['arrow'] = $next_token;
                $key_used = true;
                // Find the start of index that uses this double arrow.
                $index_end = $phpcs_file->find_previous(T_WHITESPACE, $next_token - 1, $array_start, true);
                $index_start = $phpcs_file->find_start_of_statement($index_end);
                if ($index_start === $index_end) {
                    $current_entry['index'] = $index_end;
                    $current_entry['index_content'] = $tokens[$index_end]['content'];
                    $current_entry['index_length'] = $tokens[$index_end]['length'];
                } else {
                    $current_entry['index'] = $index_start;
                    $current_entry['index_content'] = '';
                    $current_entry['index_length'] = 0;
                    for ($i = $index_start; $i <= $index_end; $i++) {
                        $current_entry['index_content'] .= $tokens[$i]['content'];
                        $current_entry['index_length'] += $tokens[$i]['length'];
                    }
                }
                if ($max_length < $current_entry['index_length']) {
                    $max_length = $current_entry['index_length'];
                }
                // Find the value of this index.
                $next_content = $phpcs_file->find_next(Tokens::$empty_tokens, $next_token + 1, $array_end, true);
                $current_entry['value'] = $next_content;
                $indices[] = $current_entry;
                $last_token = $next_token;
            }
            //end if
        }
        //end for
        // Check for multi-line arrays that should be single-line.
        $single_value = false;
        if (empty($indices) === true) {
            $single_value = true;
        } elseif (count($indices) === 1 && $tokens[$last_token]['code'] === T_COMMA) {
            // There may be another array value without a comma.
            $exclude = Tokens::$empty_tokens;
            $exclude[] = T_COMMA;
            $next_content = $phpcs_file->find_next($exclude, $indices[0]['value'] + 1, $array_end, true);
            if ($next_content === false) {
                $single_value = true;
            }
        }
        if ($single_value === true) {
            // Before we complain, make sure the single value isn't a here/nowdoc.
            $next = $phpcs_file->find_next(Tokens::$heredoc_tokens, $array_start + 1, $array_end - 1);
            if ($next === false) {
                // Array cannot be empty, so this is a multi-line array with
                // a single value. It should be defined on single line.
                $error = 'Multi-line array contains a single value; use single-line array instead';
                $error_code = 'MultiLineNotAllowed';
                $find = Tokens::$phpcs_comment_tokens;
                $find[] = T_COMMENT;
                $comment = $phpcs_file->find_next($find, $array_start + 1, $array_end);
                if ($comment === false) {
                    $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, $error_code);
                } else {
                    $fix = false;
                    $phpcs_file->add_error($error, $stack_ptr, $error_code);
                }
                if ($fix === true) {
                    $phpcs_file->fixer->begin_changeset();
                    for ($i = $array_start + 1; $i < $array_end; $i++) {
                        if ($tokens[$i]['code'] !== T_WHITESPACE) {
                            break;
                        }
                        $phpcs_file->fixer->replace_token($i, '');
                    }
                    for ($i = $array_end - 1; $i > $array_start; $i--) {
                        if ($tokens[$i]['code'] !== T_WHITESPACE) {
                            break;
                        }
                        $phpcs_file->fixer->replace_token($i, '');
                    }
                    $phpcs_file->fixer->end_changeset();
                }
                return;
            }
            //end if
        }
        //end if
        /*
            This section checks for arrays that don't specify keys.
        
            Arrays such as:
               array(
                'aaa',
                'bbb',
                'd',
               );
        */
        if ($key_used === false && empty($indices) === false) {
            $count = count($indices);
            $last_index = $indices[$count - 1]['value'];
            $trailing_content = $phpcs_file->find_previous(Tokens::$empty_tokens, $array_end - 1, $last_index, true);
            if ($tokens[$trailing_content]['code'] !== T_COMMA) {
                $phpcs_file->record_metric($stack_ptr, 'Array end comma', 'no');
                $error = 'Comma required after last value in array declaration';
                $fix = $phpcs_file->add_fixable_error($error, $trailing_content, 'NoCommaAfterLast');
                if ($fix === true) {
                    $phpcs_file->fixer->add_content($trailing_content, ',');
                }
            } else {
                $phpcs_file->record_metric($stack_ptr, 'Array end comma', 'yes');
            }
            foreach ($indices as $value_position => $value) {
                if (empty($value['value']) === true) {
                    // Array was malformed and we couldn't figure out
                    // the array value correctly, so we have to ignore it.
                    // Other parts of this sniff will correct the error.
                    continue;
                }
                $value_pointer = $value['value'];
                $ignore_tokens = [T_WHITESPACE => T_WHITESPACE, T_COMMA => T_COMMA];
                $ignore_tokens += Tokens::$cast_tokens;
                if ($tokens[$value_pointer]['code'] === T_CLOSURE || $tokens[$value_pointer]['code'] === T_FN) {
                    $ignore_tokens += [T_STATIC => T_STATIC];
                }
                $previous = $phpcs_file->find_previous($ignore_tokens, $value_pointer - 1, $array_start + 1, true);
                if ($previous === false) {
                    $previous = $stack_ptr;
                }
                $previous_is_whitespace = $tokens[$value_pointer - 1]['code'] === T_WHITESPACE;
                if ($tokens[$previous]['line'] === $tokens[$value_pointer]['line']) {
                    $error = 'Each value in a multi-line array must be on a new line';
                    if ($value_position === 0) {
                        $error = 'The first value in a multi-value array must be on a new line';
                    }
                    $fix = $phpcs_file->add_fixable_error($error, $value_pointer, 'ValueNoNewline');
                    if ($fix === true) {
                        if ($previous_is_whitespace === true) {
                            $phpcs_file->fixer->replace_token($value_pointer - 1, $phpcs_file->eol_char);
                        } else {
                            $phpcs_file->fixer->add_newline_before($value_pointer);
                        }
                    }
                } elseif ($previous_is_whitespace === true) {
                    $expected = $keyword_start;
                    $first = $phpcs_file->find_first_on_line(T_WHITESPACE, $value_pointer, true);
                    $found = $tokens[$first]['column'] - 1;
                    if ($found !== $expected) {
                        $error = 'Array value not aligned correctly; expected %s spaces but found %s';
                        $data = [$expected, $found];
                        $fix = $phpcs_file->add_fixable_error($error, $first, 'ValueNotAligned', $data);
                        if ($fix === true) {
                            if ($found === 0) {
                                $phpcs_file->fixer->add_content($first - 1, str_repeat(' ', $expected));
                            } else {
                                $phpcs_file->fixer->replace_token($first - 1, str_repeat(' ', $expected));
                            }
                        }
                    }
                }
                //end if
            }
            //end foreach
        }
        //end if
        /*
            Below the actual indentation of the array is checked.
            Errors will be thrown when a key is not aligned, when
            a double arrow is not aligned, and when a value is not
            aligned correctly.
            If an error is found in one of the above areas, then errors
            are not reported for the rest of the line to avoid reporting
            spaces and columns incorrectly. Often fixing the first
            problem will fix the other 2 anyway.
        
            For example:
        
            $a = array(
                  'index'  => '2',
                 );
        
            or
        
            $a = [
                  'index'  => '2',
                 ];
        
            In this array, the double arrow is indented too far, but this
            will also cause an error in the value's alignment. If the arrow were
            to be moved back one space however, then both errors would be fixed.
        */
        $indices_start = $keyword_start + 1;
        foreach ($indices as $value_position => $index) {
            $value_pointer = $index['value'];
            if ($value_pointer === false) {
                // Syntax error or live coding.
                continue;
            }
            if (isset($index['index']) === false) {
                // Array value only.
                continue;
            }
            $index_pointer = $index['index'];
            $index_line = $tokens[$index_pointer]['line'];
            $previous = $phpcs_file->find_previous([T_WHITESPACE, T_COMMA], $index_pointer - 1, $array_start + 1, true);
            if ($previous === false) {
                $previous = $stack_ptr;
            }
            if ($tokens[$previous]['line'] === $index_line) {
                $error = 'Each index in a multi-line array must be on a new line';
                if ($value_position === 0) {
                    $error = 'The first index in a multi-value array must be on a new line';
                }
                $fix = $phpcs_file->add_fixable_error($error, $index_pointer, 'IndexNoNewline');
                if ($fix === true) {
                    if ($tokens[$index_pointer - 1]['code'] === T_WHITESPACE) {
                        $phpcs_file->fixer->replace_token($index_pointer - 1, $phpcs_file->eol_char);
                    } else {
                        $phpcs_file->fixer->add_newline_before($index_pointer);
                    }
                }
                continue;
            }
            if ($tokens[$index_pointer]['column'] !== $indices_start && $index_pointer - 1 !== $array_start) {
                $expected = $indices_start - 1;
                $found = $tokens[$index_pointer]['column'] - 1;
                $error = 'Array key not aligned correctly; expected %s spaces but found %s';
                $data = [$expected, $found];
                $fix = $phpcs_file->add_fixable_error($error, $index_pointer, 'KeyNotAligned', $data);
                if ($fix === true) {
                    if ($found === 0 || $tokens[$index_pointer - 1]['code'] !== T_WHITESPACE) {
                        $phpcs_file->fixer->add_content($index_pointer - 1, str_repeat(' ', $expected));
                    } else {
                        $phpcs_file->fixer->replace_token($index_pointer - 1, str_repeat(' ', $expected));
                    }
                }
            }
            $arrow_start = $tokens[$index_pointer]['column'] + $max_length + 1;
            if ($tokens[$index['arrow']]['column'] !== $arrow_start) {
                $expected = $arrow_start - ($index['index_length'] + $tokens[$index_pointer]['column']);
                $found = $tokens[$index['arrow']]['column'] - ($index['index_length'] + $tokens[$index_pointer]['column']);
                $error = 'Array double arrow not aligned correctly; expected %s space(s) but found %s';
                $data = [$expected, $found];
                $fix = $phpcs_file->add_fixable_error($error, $index['arrow'], 'DoubleArrowNotAligned', $data);
                if ($fix === true) {
                    if ($found === 0) {
                        $phpcs_file->fixer->add_content($index['arrow'] - 1, str_repeat(' ', $expected));
                    } else {
                        $phpcs_file->fixer->replace_token($index['arrow'] - 1, str_repeat(' ', $expected));
                    }
                }
                continue;
            }
            $value_start = $arrow_start + 3;
            if ($tokens[$value_pointer]['column'] !== $value_start) {
                $expected = $value_start - ($tokens[$index['arrow']]['length'] + $tokens[$index['arrow']]['column']);
                $found = $tokens[$value_pointer]['column'] - ($tokens[$index['arrow']]['length'] + $tokens[$index['arrow']]['column']);
                if ($found < 0) {
                    $found = 'newline';
                }
                $error = 'Array value not aligned correctly; expected %s space(s) but found %s';
                $data = [$expected, $found];
                $fix = $phpcs_file->add_fixable_error($error, $index['arrow'], 'ValueNotAligned', $data);
                if ($fix === true) {
                    if ($found === 'newline') {
                        $prev = $phpcs_file->find_previous(T_WHITESPACE, $value_pointer - 1, null, true);
                        $phpcs_file->fixer->begin_changeset();
                        for ($i = $prev + 1; $i < $value_pointer; $i++) {
                            $phpcs_file->fixer->replace_token($i, '');
                        }
                        $phpcs_file->fixer->replace_token($value_pointer - 1, str_repeat(' ', $expected));
                        $phpcs_file->fixer->end_changeset();
                    } elseif ($found === 0) {
                        $phpcs_file->fixer->add_content($value_pointer - 1, str_repeat(' ', $expected));
                    } else {
                        $phpcs_file->fixer->replace_token($value_pointer - 1, str_repeat(' ', $expected));
                    }
                }
            }
            //end if
            // Check each line ends in a comma.
            $value_start = $value_pointer;
            $next_comma = false;
            $end = $phpcs_file->find_end_of_statement($value_start);
            if ($end === false) {
                $value_end = $value_start;
            } elseif ($tokens[$end]['code'] === T_COMMA) {
                $value_end = $phpcs_file->find_previous(Tokens::$empty_tokens, $end - 1, $value_start, true);
                $next_comma = $end;
            } else {
                $value_end = $end;
                $next = $phpcs_file->find_next(Tokens::$empty_tokens, $end + 1, $array_end, true);
                if ($next !== false && $tokens[$next]['code'] === T_COMMA) {
                    $next_comma = $next;
                }
            }
            $value_line = $tokens[$value_end]['line'];
            if ($tokens[$value_end]['code'] === T_END_HEREDOC || $tokens[$value_end]['code'] === T_END_NOWDOC) {
                $value_line++;
            }
            if ($next_comma === false || $tokens[$next_comma]['line'] !== $value_line) {
                $error = 'Each line in an array declaration must end in a comma';
                $fix = $phpcs_file->add_fixable_error($error, $value_pointer, 'NoComma');
                if ($fix === true) {
                    // Find the end of the line and put a comma there.
                    for ($i = $value_pointer + 1; $i <= $array_end; $i++) {
                        if ($tokens[$i]['line'] > $value_line) {
                            break;
                        }
                    }
                    $phpcs_file->fixer->begin_changeset();
                    $phpcs_file->fixer->add_content_before($i - 1, ',');
                    if ($next_comma !== false) {
                        $phpcs_file->fixer->replace_token($next_comma, '');
                    }
                    $phpcs_file->fixer->end_changeset();
                }
            }
            //end if
            // Check that there is no space before the comma.
            if ($next_comma !== false && $tokens[$next_comma - 1]['code'] === T_WHITESPACE) {
                // Here/nowdoc closing tags must have the comma on the next line.
                $prev = $phpcs_file->find_previous(Tokens::$empty_tokens, $next_comma - 1, null, true);
                if ($tokens[$prev]['code'] !== T_END_HEREDOC && $tokens[$prev]['code'] !== T_END_NOWDOC) {
                    $content = $tokens[$next_comma - 2]['content'];
                    $space_length = $tokens[$next_comma - 1]['length'];
                    $error = 'Expected 0 spaces between "%s" and comma; %s found';
                    $data = [$content, $space_length];
                    $fix = $phpcs_file->add_fixable_error($error, $next_comma, 'SpaceBeforeComma', $data);
                    if ($fix === true) {
                        $phpcs_file->fixer->replace_token($next_comma - 1, '');
                    }
                }
            }
        }
        //end foreach
    }
    //end processMultiLineArray()
}
//end class