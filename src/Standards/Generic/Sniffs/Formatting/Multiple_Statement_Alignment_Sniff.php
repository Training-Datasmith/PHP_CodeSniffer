<?php

declare (strict_types=1);
/**
 * Checks alignment of assignments.
 *
 * If there are multiple adjacent assignments, it will check that the equals signs of
 * each assignment are aligned. It will display a warning to advise that the signs should be aligned.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Generic\Sniffs\Formatting;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Multiple_Statement_Alignment_Sniff implements Sniff
{
    /**
     * A list of tokenizers this sniff supports.
     *
     * @var array
     */
    public $supported_tokenizers = ['PHP', 'JS'];
    /**
     * If true, an error will be thrown; otherwise a warning.
     *
     * @var boolean
     */
    public $error = false;
    /**
     * The maximum amount of padding before the alignment is ignored.
     *
     * If the amount of padding required to align this assignment with the
     * surrounding assignments exceeds this number, the assignment will be
     * ignored and no errors or warnings will be thrown.
     *
     * @var integer
     */
    public $max_padding = 1000;
    /**
     * Controls which side of the assignment token is used for alignment.
     *
     * @var boolean
     */
    public $align_at_end = true;
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        $tokens = Tokens::$assignment_tokens;
        unset($tokens[T_DOUBLE_ARROW]);
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
     * @return int
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $last_assign = $this->check_alignment($phpcs_file, $stack_ptr);
        return $last_assign + 1;
    }
    //end process()
    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token
     *                                               in the stack passed in $tokens.
     * @param int                         $end       The token where checking should end.
     *                                               If NULL, the entire file will be checked.
     *
     * @return int
     */
    public function check_alignment($phpcs_file, $stack_ptr, $end = null)
    {
        $tokens = $phpcs_file->get_tokens();
        // Ignore assignments used in a condition, like an IF or FOR or closure param defaults.
        if (isset($tokens[$stack_ptr]['nested_parenthesis']) === true) {
            // If the parenthesis is on the same line as the assignment,
            // then it should be ignored as it is specifically being grouped.
            $parens = $tokens[$stack_ptr]['nested_parenthesis'];
            $last_paren = array_pop($parens);
            if ($tokens[$last_paren]['line'] === $tokens[$stack_ptr]['line']) {
                return $stack_ptr;
            }
            foreach ($tokens[$stack_ptr]['nested_parenthesis'] as $start => $end) {
                if (isset($tokens[$start]['parenthesis_owner']) === true) {
                    return $stack_ptr;
                }
            }
        }
        $assignments = [];
        $prev_assign = null;
        $last_line = $tokens[$stack_ptr]['line'];
        $max_padding = null;
        $stopped = null;
        $last_code = $stack_ptr;
        $last_semi = null;
        $array_end = null;
        if ($end === null) {
            $end = $phpcs_file->num_tokens;
        }
        $find = Tokens::$assignment_tokens;
        unset($find[T_DOUBLE_ARROW]);
        $scopes = Tokens::$scope_openers;
        unset($scopes[T_CLOSURE]);
        unset($scopes[T_ANON_CLASS]);
        unset($scopes[T_OBJECT]);
        for ($assign = $stack_ptr; $assign < $end; $assign++) {
            if ($tokens[$assign]['level'] < $tokens[$stack_ptr]['level']) {
                // Statement is in a different context, so the block is over.
                break;
            }
            if (isset($tokens[$assign]['scope_opener']) === true && $tokens[$assign]['level'] === $tokens[$stack_ptr]['level']) {
                if (isset($scopes[$tokens[$assign]['code']]) === true) {
                    // This type of scope indicates that the assignment block is over.
                    break;
                }
                // Skip over the scope block because it is seen as part of the assignment block,
                // but also process any assignment blocks that are inside as well.
                $next_assign = $phpcs_file->find_next($find, $assign + 1, $tokens[$assign]['scope_closer'] - 1);
                if ($next_assign !== false) {
                    $assign = $this->check_alignment($phpcs_file, $next_assign);
                } else {
                    $assign = $tokens[$assign]['scope_closer'];
                }
                $last_code = $assign;
                continue;
            }
            if ($assign === $array_end) {
                $array_end = null;
            }
            if (isset($find[$tokens[$assign]['code']]) === false) {
                // A blank line indicates that the assignment block has ended.
                if (isset(Tokens::$empty_tokens[$tokens[$assign]['code']]) === false && $tokens[$assign]['line'] - $tokens[$last_code]['line'] > 1 && $tokens[$assign]['level'] === $tokens[$stack_ptr]['level'] && $array_end === null) {
                    break;
                }
                if ($tokens[$assign]['code'] === T_CLOSE_TAG) {
                    // Breaking out of PHP ends the assignment block.
                    break;
                }
                if ($tokens[$assign]['code'] === T_OPEN_SHORT_ARRAY && isset($tokens[$assign]['bracket_closer']) === true) {
                    $array_end = $tokens[$assign]['bracket_closer'];
                }
                if ($tokens[$assign]['code'] === T_ARRAY && isset($tokens[$assign]['parenthesis_opener']) === true && isset($tokens[$tokens[$assign]['parenthesis_opener']]['parenthesis_closer']) === true) {
                    $array_end = $tokens[$tokens[$assign]['parenthesis_opener']]['parenthesis_closer'];
                }
                if (isset(Tokens::$empty_tokens[$tokens[$assign]['code']]) === false) {
                    $last_code = $assign;
                    if ($tokens[$assign]['code'] === T_SEMICOLON) {
                        if ($tokens[$assign]['conditions'] === $tokens[$stack_ptr]['conditions']) {
                            if ($last_semi !== null && $prev_assign !== null && $last_semi > $prev_assign) {
                                // This statement did not have an assignment operator in it.
                                break;
                            } else {
                                $last_semi = $assign;
                            }
                        } elseif ($tokens[$assign]['level'] < $tokens[$stack_ptr]['level']) {
                            // Statement is in a different context, so the block is over.
                            break;
                        }
                    }
                }
                //end if
                continue;
            }
            if ($assign !== $stack_ptr && $tokens[$assign]['line'] === $last_line) {
                // Skip multiple assignments on the same line. We only need to
                // try and align the first assignment.
                continue;
            }
            //end if
            if ($assign !== $stack_ptr) {
                if ($tokens[$assign]['level'] > $tokens[$stack_ptr]['level']) {
                    // Has to be nested inside the same conditions as the first assignment.
                    // We've gone one level down, so process this new block.
                    $assign = $this->check_alignment($phpcs_file, $assign);
                    $last_code = $assign;
                    continue;
                }
                if ($tokens[$assign]['level'] < $tokens[$stack_ptr]['level']) {
                    // We've gone one level up, so the block we are processing is done.
                    break;
                } elseif ($array_end !== null) {
                    // Assignments inside arrays are not part of
                    // the original block, so process this new block.
                    $assign = $this->check_alignment($phpcs_file, $assign, $array_end) - 1;
                    $array_end = null;
                    $last_code = $assign;
                    continue;
                }
                // Make sure it is not assigned inside a condition (eg. IF, FOR).
                if (isset($tokens[$assign]['nested_parenthesis']) === true) {
                    // If the parenthesis is on the same line as the assignment,
                    // then it should be ignored as it is specifically being grouped.
                    $parens = $tokens[$assign]['nested_parenthesis'];
                    $last_paren = array_pop($parens);
                    if ($tokens[$last_paren]['line'] === $tokens[$assign]['line']) {
                        break;
                    }
                    foreach ($tokens[$assign]['nested_parenthesis'] as $start => $end) {
                        if (isset($tokens[$start]['parenthesis_owner']) === true) {
                            break 2;
                        }
                    }
                }
            }
            //end if
            $var = $phpcs_file->find_previous(Tokens::$empty_tokens, $assign - 1, null, true);
            // Make sure we wouldn't break our max padding length if we
            // aligned with this statement, or they wouldn't break the max
            // padding length if they aligned with us.
            $var_end = $tokens[$var + 1]['column'];
            $assign_len = $tokens[$assign]['length'];
            if ($this->align_at_end !== true) {
                $assign_len = 1;
            }
            if ($assign !== $stack_ptr) {
                if ($prev_assign === null) {
                    // Processing an inner block but no assignments found.
                    break;
                }
                if ($var_end + 1 > $assignments[$prev_assign]['assign_col']) {
                    $padding = 1;
                    $assign_column = $var_end + 1;
                } else {
                    $padding = $assignments[$prev_assign]['assign_col'] - $var_end + $assignments[$prev_assign]['assign_len'] - $assign_len;
                    if ($padding <= 0) {
                        $padding = 1;
                    }
                    if ($padding > $this->max_padding) {
                        $stopped = $assign;
                        break;
                    }
                    $assign_column = $var_end + $padding;
                }
                //end if
                if ($assign_column + $assign_len > $assignments[$max_padding]['assign_col'] + $assignments[$max_padding]['assign_len']) {
                    $new_padding = $var_end - $assignments[$max_padding]['var_end'] + $assign_len - $assignments[$max_padding]['assign_len'] + 1;
                    if ($new_padding > $this->max_padding) {
                        $stopped = $assign;
                        break;
                    } else {
                        // New alignment settings for previous assignments.
                        foreach ($assignments as $i => $data) {
                            if ($i === $assign) {
                                break;
                            }
                            $new_padding = $var_end - $data['var_end'] + $assign_len - $data['assign_len'] + 1;
                            $assignments[$i]['expected'] = $new_padding;
                            $assignments[$i]['assign_col'] = $data['var_end'] + $new_padding;
                        }
                        $padding = 1;
                        $assign_column = $var_end + 1;
                    }
                } elseif ($padding > $assignments[$max_padding]['expected']) {
                    $max_padding = $assign;
                }
                //end if
            } else {
                $padding = 1;
                $assign_column = $var_end + 1;
                $max_padding = $assign;
            }
            //end if
            $found = 0;
            if ($tokens[$var + 1]['code'] === T_WHITESPACE) {
                $found = $tokens[$var + 1]['length'];
                if ($found === 0) {
                    // This means a newline was found.
                    $found = 1;
                }
            }
            $assignments[$assign] = ['var_end' => $var_end, 'assign_len' => $assign_len, 'assign_col' => $assign_column, 'expected' => $padding, 'found' => $found];
            $last_line = $tokens[$assign]['line'];
            $prev_assign = $assign;
        }
        //end for
        if (empty($assignments) === true) {
            return $stack_ptr;
        }
        $num_assignments = count($assignments);
        $error_generated = false;
        foreach ($assignments as $assignment => $data) {
            if ($data['found'] === $data['expected']) {
                continue;
            }
            $expected_text = $data['expected'] . ' space';
            if ($data['expected'] !== 1) {
                $expected_text .= 's';
            }
            if ($data['found'] === null) {
                $found_text = 'a new line';
            } else {
                $found_text = $data['found'] . ' space';
                if ($data['found'] !== 1) {
                    $found_text .= 's';
                }
            }
            if ($num_assignments === 1) {
                $type = 'Incorrect';
                $error = 'Equals sign not aligned correctly; expected %s but found %s';
            } else {
                $type = 'NotSame';
                $error = 'Equals sign not aligned with surrounding assignments; expected %s but found %s';
            }
            $error_data = [$expected_text, $found_text];
            if ($this->error === true) {
                $fix = $phpcs_file->add_fixable_error($error, $assignment, $type, $error_data);
            } else {
                $fix = $phpcs_file->add_fixable_warning($error, $assignment, $type . 'Warning', $error_data);
            }
            $error_generated = true;
            if ($fix === true && $data['found'] !== null) {
                $new_content = str_repeat(' ', $data['expected']);
                if ($data['found'] === 0) {
                    $phpcs_file->fixer->add_content_before($assignment, $new_content);
                } else {
                    $phpcs_file->fixer->replace_token($assignment - 1, $new_content);
                }
            }
        }
        //end foreach
        if ($num_assignments > 1) {
            if ($error_generated === true) {
                $phpcs_file->record_metric($stack_ptr, 'Adjacent assignments aligned', 'no');
            } else {
                $phpcs_file->record_metric($stack_ptr, 'Adjacent assignments aligned', 'yes');
            }
        }
        if ($stopped !== null) {
            return $this->check_alignment($phpcs_file, $stopped);
        }
        return $assign;
    }
    //end checkAlignment()
}
//end class