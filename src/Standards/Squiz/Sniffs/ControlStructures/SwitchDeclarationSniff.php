<?php

declare (strict_types=1);
/**
 * Enforces switch statement formatting.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\Control_Structures;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Switch_Declaration_Sniff implements Sniff
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
        $case_count = 0;
        $found_default = false;
        while (($next_case = $phpcs_file->find_next([T_CASE, T_DEFAULT, T_SWITCH], $next_case + 1, $switch['scope_closer'])) !== false) {
            // Skip nested SWITCH statements; they are handled on their own.
            if ($tokens[$next_case]['code'] === T_SWITCH) {
                $next_case = $tokens[$next_case]['scope_closer'];
                continue;
            }
            if ($tokens[$next_case]['code'] === T_DEFAULT) {
                $type = 'Default';
                $found_default = true;
            } else {
                $type = 'Case';
                $case_count++;
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
            if ($tokens[$next_case]['column'] !== $case_alignment) {
                $error = strtoupper($type) . ' keyword must be indented ' . $this->indent . ' spaces from SWITCH keyword';
                $fix = $phpcs_file->add_fixable_error($error, $next_case, $type . 'Indent');
                if ($fix === true) {
                    $padding = str_repeat(' ', $case_alignment - 1);
                    if ($tokens[$next_case]['column'] === 1 || $tokens[$next_case - 1]['code'] !== T_WHITESPACE) {
                        $phpcs_file->fixer->add_content_before($next_case, $padding);
                    } else {
                        $phpcs_file->fixer->replace_token($next_case - 1, $padding);
                    }
                }
            }
            if ($type === 'Case' && ($tokens[$next_case + 1]['type'] !== 'T_WHITESPACE' || $tokens[$next_case + 1]['content'] !== ' ')) {
                $error = 'CASE keyword must be followed by a single space';
                $fix = $phpcs_file->add_fixable_error($error, $next_case, 'SpacingAfterCase');
                if ($fix === true) {
                    if ($tokens[$next_case + 1]['type'] !== 'T_WHITESPACE') {
                        $phpcs_file->fixer->add_content($next_case, ' ');
                    } else {
                        $phpcs_file->fixer->replace_token($next_case + 1, ' ');
                    }
                }
            }
            if (isset($tokens[$next_case]['scope_opener']) === false) {
                $error = 'Possible parse error: CASE missing opening colon';
                $phpcs_file->add_warning($error, $next_case, 'MissingColon');
                continue;
            }
            $opener = $tokens[$next_case]['scope_opener'];
            if ($tokens[$opener - 1]['type'] === 'T_WHITESPACE') {
                $error = 'There must be no space before the colon in a ' . strtoupper($type) . ' statement';
                $fix = $phpcs_file->add_fixable_error($error, $next_case, 'SpaceBeforeColon' . $type);
                if ($fix === true) {
                    $phpcs_file->fixer->replace_token($opener - 1, '');
                }
            }
            $next_break = $tokens[$next_case]['scope_closer'];
            if ($tokens[$next_break]['code'] === T_BREAK || $tokens[$next_break]['code'] === T_RETURN || $tokens[$next_break]['code'] === T_CONTINUE || $tokens[$next_break]['code'] === T_THROW || $tokens[$next_break]['code'] === T_EXIT) {
                if ($tokens[$next_break]['scope_condition'] === $next_case) {
                    // Only need to check a couple of things once, even if the
                    // break is shared between multiple case statements, or even
                    // the default case.
                    if ($tokens[$next_break]['column'] !== $case_alignment) {
                        $error = 'Case breaking statement must be indented ' . $this->indent . ' spaces from SWITCH keyword';
                        $fix = $phpcs_file->add_fixable_error($error, $next_break, 'BreakIndent');
                        if ($fix === true) {
                            $padding = str_repeat(' ', $case_alignment - 1);
                            if ($tokens[$next_break]['column'] === 1 || $tokens[$next_break - 1]['code'] !== T_WHITESPACE) {
                                $phpcs_file->fixer->add_content_before($next_break, $padding);
                            } else {
                                $phpcs_file->fixer->replace_token($next_break - 1, $padding);
                            }
                        }
                    }
                    $prev = $phpcs_file->find_previous(T_WHITESPACE, $next_break - 1, $stack_ptr, true);
                    if ($tokens[$prev]['line'] !== $tokens[$next_break]['line'] - 1) {
                        $error = 'Blank lines are not allowed before case breaking statements';
                        $phpcs_file->add_error($error, $next_break, 'SpacingBeforeBreak');
                    }
                    $next_line = $tokens[$tokens[$stack_ptr]['scope_closer']]['line'];
                    $semicolon = $phpcs_file->find_end_of_statement($next_break);
                    for ($i = $semicolon + 1; $i < $tokens[$stack_ptr]['scope_closer']; $i++) {
                        if ($tokens[$i]['type'] !== 'T_WHITESPACE') {
                            $next_line = $tokens[$i]['line'];
                            break;
                        }
                    }
                    if ($type === 'Case') {
                        // Ensure the BREAK statement is followed by
                        // a single blank line, or the end switch brace.
                        if ($next_line !== $tokens[$semicolon]['line'] + 2 && $i !== $tokens[$stack_ptr]['scope_closer']) {
                            $error = 'Case breaking statements must be followed by a single blank line';
                            $fix = $phpcs_file->add_fixable_error($error, $next_break, 'SpacingAfterBreak');
                            if ($fix === true) {
                                $phpcs_file->fixer->begin_changeset();
                                for ($i = $semicolon + 1; $i <= $tokens[$stack_ptr]['scope_closer']; $i++) {
                                    if ($tokens[$i]['line'] === $next_line) {
                                        $phpcs_file->fixer->add_newline_before($i);
                                        break;
                                    }
                                    if ($tokens[$i]['line'] === $tokens[$semicolon]['line']) {
                                        continue;
                                    }
                                    $phpcs_file->fixer->replace_token($i, '');
                                }
                                $phpcs_file->fixer->end_changeset();
                            }
                        }
                        //end if
                    } else if ($next_line !== $tokens[$semicolon]['line'] + 1) {
                        $error = 'Blank lines are not allowed after the DEFAULT case\'s breaking statement';
                        $phpcs_file->add_error($error, $next_break, 'SpacingAfterDefaultBreak');
                    }
                    //end if
                    $case_line = $tokens[$next_case]['line'];
                    $next_line = $tokens[$next_break]['line'];
                    for ($i = $opener + 1; $i < $next_break; $i++) {
                        if ($tokens[$i]['type'] !== 'T_WHITESPACE') {
                            $next_line = $tokens[$i]['line'];
                            break;
                        }
                    }
                    if ($next_line !== $case_line + 1) {
                        $error = 'Blank lines are not allowed after ' . strtoupper($type) . ' statements';
                        $phpcs_file->add_error($error, $next_case, 'SpacingAfter' . $type);
                    }
                }
                //end if
                if ($tokens[$next_break]['code'] === T_BREAK) {
                    if ($type === 'Case') {
                        // Ensure empty CASE statements are not allowed.
                        // They must have some code content in them. A comment is not enough.
                        // But count RETURN statements as valid content if they also
                        // happen to close the CASE statement.
                        $found_content = false;
                        for ($i = $tokens[$next_case]['scope_opener'] + 1; $i < $next_break; $i++) {
                            if ($tokens[$i]['code'] === T_CASE) {
                                $i = $tokens[$i]['scope_opener'];
                                continue;
                            }
                            if (isset(Tokens::$empty_tokens[$tokens[$i]['code']]) === false) {
                                $found_content = true;
                                break;
                            }
                        }
                        if ($found_content === false) {
                            $error = 'Empty CASE statements are not allowed';
                            $phpcs_file->add_error($error, $next_case, 'EmptyCase');
                        }
                    } else {
                        // Ensure empty DEFAULT statements are not allowed.
                        // They must (at least) have a comment describing why
                        // the default case is being ignored.
                        $found_content = false;
                        for ($i = $tokens[$next_case]['scope_opener'] + 1; $i < $next_break; $i++) {
                            if ($tokens[$i]['type'] !== 'T_WHITESPACE') {
                                $found_content = true;
                                break;
                            }
                        }
                        if ($found_content === false) {
                            $error = 'Comment required for empty DEFAULT case';
                            $phpcs_file->add_error($error, $next_case, 'EmptyDefault');
                        }
                    }
                    //end if
                }
                //end if
            } elseif ($type === 'Default') {
                $error = 'DEFAULT case must have a breaking statement';
                $phpcs_file->add_error($error, $next_case, 'DefaultNoBreak');
            }
            //end if
        }
        //end while
        if ($found_default === false) {
            $error = 'All SWITCH statements must contain a DEFAULT case';
            $phpcs_file->add_error($error, $stack_ptr, 'MissingDefault');
        }
        if ($tokens[$switch['scope_closer']]['column'] !== $switch['column']) {
            $error = 'Closing brace of SWITCH statement must be aligned with SWITCH keyword';
            $phpcs_file->add_error($error, $switch['scope_closer'], 'CloseBraceAlign');
        }
        if ($case_count === 0) {
            $error = 'SWITCH statements must contain at least one CASE statement';
            $phpcs_file->add_error($error, $stack_ptr, 'MissingCase');
        }
    }
    //end process()
}
//end class