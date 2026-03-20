<?php

declare (strict_types=1);
/**
 * Ensures all switch statements are defined correctly.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\PSR2\Sniffs\Control_Structures;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Switch_Declaration_Sniff implements Sniff
{
    /**
     * The number of spaces code should be indented.
     *
     * @var integer
     */
    public $indent = 4;
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_SWITCH];
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
        // We can't process SWITCH statements unless we know where they start and end.
        if (isset($tokens[$stack_ptr]['scope_opener']) === false || isset($tokens[$stack_ptr]['scope_closer']) === false) {
            return;
        }
        $switch = $tokens[$stack_ptr];
        $next_case = $stack_ptr;
        $case_alignment = $switch['column'] + $this->indent;
        while (($next_case = $this->find_next_case($phpcs_file, $next_case + 1, $switch['scope_closer'])) !== false) {
            if ($tokens[$next_case]['code'] === T_DEFAULT) {
                $type = 'default';
            } else {
                $type = 'case';
            }
            if ($tokens[$next_case]['content'] !== strtolower($tokens[$next_case]['content'])) {
                $expected = strtolower($tokens[$next_case]['content']);
                $error = strtoupper($type) . ' keyword must be lowercase; expected "%s" but found "%s"';
                $data = [$expected, $tokens[$next_case]['content']];
                $fix = $phpcs_file->add_fixable_error($error, $next_case, $type . 'NotLower', $data);
                if ($fix === true) {
                    $phpcs_file->fixer->replace_token($next_case, $expected);
                }
            }
            if ($type === 'case' && ($tokens[$next_case + 1]['code'] !== T_WHITESPACE || $tokens[$next_case + 1]['content'] !== ' ')) {
                $error = 'CASE keyword must be followed by a single space';
                $fix = $phpcs_file->add_fixable_error($error, $next_case, 'SpacingAfterCase');
                if ($fix === true) {
                    if ($tokens[$next_case + 1]['code'] !== T_WHITESPACE) {
                        $phpcs_file->fixer->add_content($next_case, ' ');
                    } else {
                        $phpcs_file->fixer->replace_token($next_case + 1, ' ');
                    }
                }
            }
            $opener = $tokens[$next_case]['scope_opener'];
            $next_closer = $tokens[$next_case]['scope_closer'];
            if ($tokens[$opener]['code'] === T_COLON) {
                if ($tokens[$opener - 1]['code'] === T_WHITESPACE) {
                    $error = 'There must be no space before the colon in a ' . strtoupper($type) . ' statement';
                    $fix = $phpcs_file->add_fixable_error($error, $next_case, 'SpaceBeforeColon' . strtoupper($type));
                    if ($fix === true) {
                        $phpcs_file->fixer->replace_token($opener - 1, '');
                    }
                }
                for ($next = $opener + 1; $next < $next_closer; $next++) {
                    if (isset(Tokens::$empty_tokens[$tokens[$next]['code']]) === false || isset(Tokens::$comment_tokens[$tokens[$next]['code']]) === true && $tokens[$next]['line'] !== $tokens[$opener]['line']) {
                        break;
                    }
                }
                if ($tokens[$next]['line'] !== $tokens[$opener]['line'] + 1) {
                    $error = 'The ' . strtoupper($type) . ' body must start on the line following the statement';
                    $fix = $phpcs_file->add_fixable_error($error, $next_case, 'BodyOnNextLine' . strtoupper($type));
                    if ($fix === true) {
                        if ($tokens[$next]['line'] === $tokens[$opener]['line']) {
                            $padding = str_repeat(' ', $case_alignment + $this->indent - 1);
                            $phpcs_file->fixer->add_content_before($next, $phpcs_file->eol_char . $padding);
                        } else {
                            $phpcs_file->fixer->begin_changeset();
                            for ($i = $opener + 1; $i < $next; $i++) {
                                if ($tokens[$i]['line'] === $tokens[$opener]['line']) {
                                    // Ignore trailing comments.
                                    continue;
                                }
                                if ($tokens[$i]['line'] === $tokens[$next]['line']) {
                                    break;
                                }
                                $phpcs_file->fixer->replace_token($i, '');
                            }
                            $phpcs_file->fixer->end_changeset();
                        }
                    }
                    //end if
                }
                //end if
                if ($tokens[$next_closer]['scope_condition'] === $next_case) {
                    // Only need to check some things once, even if the
                    // closer is shared between multiple case statements, or even
                    // the default case.
                    $prev = $phpcs_file->find_previous(T_WHITESPACE, $next_closer - 1, $next_case, true);
                    if ($tokens[$prev]['line'] === $tokens[$next_closer]['line']) {
                        $error = 'Terminating statement must be on a line by itself';
                        $fix = $phpcs_file->add_fixable_error($error, $next_closer, 'BreakNotNewLine');
                        if ($fix === true) {
                            $phpcs_file->fixer->add_new_line($prev);
                            $phpcs_file->fixer->replace_token($next_closer, trim($tokens[$next_closer]['content']));
                        }
                    } else {
                        $diff = $tokens[$next_case]['column'] + $this->indent - $tokens[$next_closer]['column'];
                        if ($diff !== 0) {
                            $error = 'Terminating statement must be indented to the same level as the CASE body';
                            $fix = $phpcs_file->add_fixable_error($error, $next_closer, 'BreakIndent');
                            if ($fix === true) {
                                if ($diff > 0) {
                                    $phpcs_file->fixer->add_content_before($next_closer, str_repeat(' ', $diff));
                                } else {
                                    $phpcs_file->fixer->substr_token($next_closer - 1, 0, $diff);
                                }
                            }
                        }
                    }
                    //end if
                }
                //end if
            } else {
                $error = strtoupper($type) . ' statements must be defined using a colon';
                $phpcs_file->add_error($error, $next_case, 'WrongOpener' . $type);
            }
            //end if
            // We only want cases from here on in.
            if ($type !== 'case') {
                continue;
            }
            $next_code = $phpcs_file->find_next(T_WHITESPACE, $opener + 1, $next_closer, true);
            if ($tokens[$next_code]['code'] !== T_CASE && $tokens[$next_code]['code'] !== T_DEFAULT) {
                // This case statement has content. If the next case or default comes
                // before the closer, it means we don't have an obvious terminating
                // statement and need to make some more effort to find one. If we
                // don't, we do need a comment.
                $next_code = $this->find_next_case($phpcs_file, $opener + 1, $next_closer);
                if ($next_code !== false) {
                    $prev_code = $phpcs_file->find_previous(T_WHITESPACE, $next_code - 1, $next_case, true);
                    if (isset(Tokens::$comment_tokens[$tokens[$prev_code]['code']]) === false && $this->find_nested_terminator($phpcs_file, $opener + 1, $next_code) === false) {
                        $error = 'There must be a comment when fall-through is intentional in a non-empty case body';
                        $phpcs_file->add_error($error, $next_case, 'TerminatingComment');
                    }
                }
            }
        }
        //end while
    }
    //end process()
    /**
     * Find the next CASE or DEFAULT statement from a point in the file.
     *
     * Note that nested switches are ignored.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position to start looking at.
     * @param int                         $end       The position to stop looking at.
     *
     * @return int|false
     */
    private function find_next_case($phpcs_file, $stack_ptr, $end)
    {
        $tokens = $phpcs_file->get_tokens();
        while (($stack_ptr = $phpcs_file->find_next([T_CASE, T_DEFAULT, T_SWITCH], $stack_ptr, $end)) !== false) {
            // Skip nested SWITCH statements; they are handled on their own.
            if ($tokens[$stack_ptr]['code'] === T_SWITCH) {
                $stack_ptr = $tokens[$stack_ptr]['scope_closer'];
                continue;
            }
            break;
        }
        return $stack_ptr;
    }
    //end findNextCase()
    /**
     * Returns the position of the nested terminating statement.
     *
     * Returns false if no terminating statement was found.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position to start looking at.
     * @param int                         $end       The position to stop looking at.
     *
     * @return int|false
     */
    private function find_nested_terminator($phpcs_file, $stack_ptr, $end)
    {
        $tokens = $phpcs_file->get_tokens();
        $last_token = $phpcs_file->find_previous(Tokens::$empty_tokens, $end - 1, $stack_ptr, true);
        if ($last_token === false) {
            return false;
        }
        if ($tokens[$last_token]['code'] === T_CLOSE_CURLY_BRACKET) {
            // We found a closing curly bracket and want to check if its block
            // belongs to a SWITCH, IF, ELSEIF or ELSE, TRY, CATCH OR FINALLY clause.
            // If yes, we continue searching for a terminating statement within that
            // block. Note that we have to make sure that every block of
            // the entire if/else/switch statement has a terminating statement.
            // For a try/catch/finally statement, either the finally block has
            // to have a terminating statement or every try/catch block has to have one.
            $current_closer = $last_token;
            $has_else_block = false;
            $has_catch_without_terminator = false;
            do {
                $scope_opener = $tokens[$current_closer]['scope_opener'];
                $scope_closer = $tokens[$current_closer]['scope_closer'];
                $prev_token = $phpcs_file->find_previous(Tokens::$empty_tokens, $scope_opener - 1, $stack_ptr, true);
                if ($prev_token === false) {
                    return false;
                }
                // SWITCH, IF, ELSEIF, CATCH clauses possess a condition we have to account for.
                if ($tokens[$prev_token]['code'] === T_CLOSE_PARENTHESIS) {
                    $prev_token = $tokens[$prev_token]['parenthesis_owner'];
                }
                if ($tokens[$prev_token]['code'] === T_IF) {
                    // If we have not encountered an ELSE clause by now, we cannot
                    // be sure that the whole statement terminates in every case.
                    if ($has_else_block === false) {
                        return false;
                    }
                    return $this->find_nested_terminator($phpcs_file, $scope_opener + 1, $scope_closer);
                }
                if ($tokens[$prev_token]['code'] === T_ELSEIF || $tokens[$prev_token]['code'] === T_ELSE) {
                    // If we find a terminating statement within this block,
                    // we continue with the previous ELSEIF or IF clause.
                    $has_terminator = $this->find_nested_terminator($phpcs_file, $scope_opener + 1, $scope_closer);
                    if ($has_terminator === false) {
                        return false;
                    }
                    $current_closer = $phpcs_file->find_previous(Tokens::$empty_tokens, $prev_token - 1, $stack_ptr, true);
                    if ($tokens[$prev_token]['code'] === T_ELSE) {
                        $has_else_block = true;
                    }
                } elseif ($tokens[$prev_token]['code'] === T_FINALLY) {
                    // If we find a terminating statement within this block,
                    // the whole try/catch/finally statement is covered.
                    $has_terminator = $this->find_nested_terminator($phpcs_file, $scope_opener + 1, $scope_closer);
                    if ($has_terminator !== false) {
                        return $has_terminator;
                    }
                    // Otherwise, we continue with the previous TRY or CATCH clause.
                    $current_closer = $phpcs_file->find_previous(Tokens::$empty_tokens, $prev_token - 1, $stack_ptr, true);
                } else {
                    if ($tokens[$prev_token]['code'] === T_TRY) {
                        // If we've seen CATCH blocks without terminator statement and
                        // have not seen a FINALLY *with* a terminator statement, we
                        // don't even need to bother checking the TRY.
                        if ($has_catch_without_terminator === true) {
                            return false;
                        }
                        return $this->find_nested_terminator($phpcs_file, $scope_opener + 1, $scope_closer);
                    }
                    if ($tokens[$prev_token]['code'] === T_CATCH) {
                        // Keep track of seen catch statements without terminating statement,
                        // but don't bow out yet as there may still be a FINALLY clause
                        // with a terminating statement before the CATCH.
                        $has_terminator = $this->find_nested_terminator($phpcs_file, $scope_opener + 1, $scope_closer);
                        if ($has_terminator === false) {
                            $has_catch_without_terminator = true;
                        }
                        $current_closer = $phpcs_file->find_previous(Tokens::$empty_tokens, $prev_token - 1, $stack_ptr, true);
                    } else {
                        if ($tokens[$prev_token]['code'] === T_SWITCH) {
                            $has_default_block = false;
                            $end_of_switch = $tokens[$prev_token]['scope_closer'];
                            $next_case = $prev_token;
                            // We look for a terminating statement within every blocks.
                            while (($next_case = $this->find_next_case($phpcs_file, $next_case + 1, $end_of_switch)) !== false) {
                                if ($tokens[$next_case]['code'] === T_DEFAULT) {
                                    $has_default_block = true;
                                }
                                $opener = $tokens[$next_case]['scope_opener'];
                                $next_code = $phpcs_file->find_next(Tokens::$empty_tokens, $opener + 1, $end_of_switch, true);
                                if ($tokens[$next_code]['code'] === T_CASE) {
                                    // This case statement has no content, so skip it.
                                    continue;
                                }
                                if ($tokens[$next_code]['code'] === T_DEFAULT) {
                                    // This case statement has no content, so skip it.
                                    continue;
                                }
                                $end_of_case = $this->find_next_case($phpcs_file, $opener + 1, $end_of_switch);
                                if ($end_of_case === false) {
                                    $end_of_case = $end_of_switch;
                                }
                                $has_terminator = $this->find_nested_terminator($phpcs_file, $opener + 1, $end_of_case);
                                if ($has_terminator === false) {
                                    return false;
                                }
                            }
                            //end while
                            // If we have not encountered a DEFAULT block by now, we cannot
                            // be sure that the whole statement terminates in every case.
                            if ($has_default_block === false) {
                                return false;
                            }
                            return $has_terminator;
                        }
                        return false;
                    }
                }
                //end if
            } while ($current_closer !== false && $tokens[$current_closer]['code'] === T_CLOSE_CURLY_BRACKET);
            return true;
        }
        if ($tokens[$last_token]['code'] === T_SEMICOLON) {
            // We found the last statement of the CASE. Now we want to
            // check whether it is a terminating one.
            $terminators = [T_RETURN => T_RETURN, T_BREAK => T_BREAK, T_CONTINUE => T_CONTINUE, T_THROW => T_THROW, T_EXIT => T_EXIT];
            $terminator = $phpcs_file->find_start_of_statement($last_token - 1);
            if (isset($terminators[$tokens[$terminator]['code']]) === true) {
                return $terminator;
            }
        }
        //end if
        return false;
    }
    //end findNestedTerminator()
}
//end class