<?php

declare (strict_types=1);
/**
 * Ensure multi-line IF conditions are defined correctly.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\PEAR\Sniffs\Control_Structures;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Multi_Line_Condition_Sniff implements Sniff
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
        return [T_IF, T_ELSEIF];
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
        if (isset($tokens[$stack_ptr]['parenthesis_opener']) === false) {
            return;
        }
        $open_bracket = $tokens[$stack_ptr]['parenthesis_opener'];
        $close_bracket = $tokens[$stack_ptr]['parenthesis_closer'];
        $space_after_open = 0;
        if ($tokens[$open_bracket + 1]['code'] === T_WHITESPACE) {
            if (strpos($tokens[$open_bracket + 1]['content'], $phpcs_file->eol_char) !== false) {
                $space_after_open = 'newline';
            } else {
                $space_after_open = $tokens[$open_bracket + 1]['length'];
            }
        }
        if ($space_after_open !== 0) {
            $error = 'First condition of a multi-line IF statement must directly follow the opening parenthesis';
            $fix = $phpcs_file->add_fixable_error($error, $open_bracket + 1, 'SpacingAfterOpenBrace');
            if ($fix === true) {
                $phpcs_file->fixer->replace_token($open_bracket + 1, '');
            }
        }
        // We need to work out how far indented the if statement
        // itself is, so we can work out how far to indent conditions.
        $statement_indent = 0;
        for ($i = $stack_ptr - 1; $i >= 0; $i--) {
            if ($tokens[$i]['line'] !== $tokens[$stack_ptr]['line']) {
                $i++;
                break;
            }
        }
        if ($i >= 0 && $tokens[$i]['code'] === T_WHITESPACE) {
            $statement_indent = $tokens[$i]['length'];
        }
        // Each line between the parenthesis should be indented 4 spaces
        // and start with an operator, unless the line is inside a
        // function call, in which case it is ignored.
        $prev_line = $tokens[$open_bracket]['line'];
        for ($i = $open_bracket + 1; $i <= $close_bracket; $i++) {
            if ($i === $close_bracket && $tokens[$open_bracket]['line'] !== $tokens[$i]['line']) {
                $prev = $phpcs_file->find_previous(T_WHITESPACE, $i - 1, null, true);
                if ($tokens[$prev]['line'] === $tokens[$i]['line']) {
                    // Closing bracket is on the same line as a condition.
                    $error = 'Closing parenthesis of a multi-line IF statement must be on a new line';
                    $fix = $phpcs_file->add_fixable_error($error, $close_bracket, 'CloseBracketNewLine');
                    if ($fix === true) {
                        // Account for a comment at the end of the line.
                        $next = $phpcs_file->find_next(T_WHITESPACE, $close_bracket + 1, null, true);
                        if ($tokens[$next]['code'] !== T_COMMENT && isset(Tokens::$phpcs_comment_tokens[$tokens[$next]['code']]) === false) {
                            $phpcs_file->fixer->add_newline_before($close_bracket);
                        } else {
                            $next = $phpcs_file->find_next(Tokens::$empty_tokens, $next + 1, null, true);
                            $phpcs_file->fixer->begin_changeset();
                            $phpcs_file->fixer->replace_token($close_bracket, '');
                            $phpcs_file->fixer->add_content_before($next, ')');
                            $phpcs_file->fixer->end_changeset();
                        }
                    }
                }
            }
            //end if
            if ($tokens[$i]['line'] !== $prev_line) {
                if ($tokens[$i]['line'] === $tokens[$close_bracket]['line']) {
                    $next = $phpcs_file->find_next(T_WHITESPACE, $i, null, true);
                    if ($next !== $close_bracket) {
                        $expected_indent = $statement_indent + $this->indent;
                    } else {
                        // Closing brace needs to be indented to the same level
                        // as the statement.
                        $expected_indent = $statement_indent;
                    }
                    //end if
                } else {
                    $expected_indent = $statement_indent + $this->indent;
                }
                //end if
                if ($tokens[$i]['code'] === T_COMMENT || isset(Tokens::$phpcs_comment_tokens[$tokens[$i]['code']]) === true) {
                    $prev_line = $tokens[$i]['line'];
                    continue;
                }
                // We changed lines, so this should be a whitespace indent token.
                if ($tokens[$i]['code'] !== T_WHITESPACE) {
                    $found_indent = 0;
                } else {
                    $found_indent = $tokens[$i]['length'];
                }
                if ($expected_indent !== $found_indent) {
                    $error = 'Multi-line IF statement not indented correctly; expected %s spaces but found %s';
                    $data = [$expected_indent, $found_indent];
                    $fix = $phpcs_file->add_fixable_error($error, $i, 'Alignment', $data);
                    if ($fix === true) {
                        $spaces = str_repeat(' ', $expected_indent);
                        if ($found_indent === 0) {
                            $phpcs_file->fixer->add_content_before($i, $spaces);
                        } else {
                            $phpcs_file->fixer->replace_token($i, $spaces);
                        }
                    }
                }
                $next = $phpcs_file->find_next(Tokens::$empty_tokens, $i, null, true);
                if ($next !== $close_bracket && $tokens[$next]['line'] === $tokens[$i]['line']) {
                    if (isset(Tokens::$boolean_operators[$tokens[$next]['code']]) === false) {
                        $prev = $phpcs_file->find_previous(Tokens::$empty_tokens, $i - 1, $open_bracket, true);
                        $fixable = true;
                        if (isset(Tokens::$boolean_operators[$tokens[$prev]['code']]) === false && $phpcs_file->find_next(T_WHITESPACE, $prev + 1, $next, true) !== false) {
                            // Condition spread over multi-lines interspersed with comments.
                            $fixable = false;
                        }
                        $error = 'Each line in a multi-line IF statement must begin with a boolean operator';
                        if ($fixable === false) {
                            $phpcs_file->add_error($error, $next, 'StartWithBoolean');
                        } else {
                            $fix = $phpcs_file->add_fixable_error($error, $next, 'StartWithBoolean');
                            if ($fix === true) {
                                if (isset(Tokens::$boolean_operators[$tokens[$prev]['code']]) === true) {
                                    $phpcs_file->fixer->begin_changeset();
                                    $phpcs_file->fixer->replace_token($prev, '');
                                    $phpcs_file->fixer->add_content_before($next, $tokens[$prev]['content'] . ' ');
                                    $phpcs_file->fixer->end_changeset();
                                } else {
                                    for ($x = $prev + 1; $x < $next; $x++) {
                                        $phpcs_file->fixer->replace_token($x, '');
                                    }
                                }
                            }
                        }
                    }
                    //end if
                }
                //end if
                $prev_line = $tokens[$i]['line'];
            }
            //end if
            if ($tokens[$i]['code'] === T_STRING) {
                $next = $phpcs_file->find_next(T_WHITESPACE, $i + 1, null, true);
                if ($tokens[$next]['code'] === T_OPEN_PARENTHESIS) {
                    // This is a function call, so skip to the end as they
                    // have their own indentation rules.
                    $i = $tokens[$next]['parenthesis_closer'];
                    $prev_line = $tokens[$i]['line'];
                    continue;
                }
            }
        }
        //end for
        // From here on, we are checking the spacing of the opening and closing
        // braces. If this IF statement does not use braces, we end here.
        if (isset($tokens[$stack_ptr]['scope_opener']) === false) {
            return;
        }
        // The opening brace needs to be one space away from the closing parenthesis.
        $open_brace = $tokens[$stack_ptr]['scope_opener'];
        $next = $phpcs_file->find_next(T_WHITESPACE, $close_bracket + 1, $open_brace, true);
        if ($next !== false) {
            // Probably comments in between tokens, so don't check.
            return;
        }
        if ($tokens[$open_brace]['line'] > $tokens[$close_bracket]['line']) {
            $length = -1;
        } elseif ($open_brace === $close_bracket + 1) {
            $length = 0;
        } elseif ($open_brace === $close_bracket + 2 && $tokens[$close_bracket + 1]['code'] === T_WHITESPACE) {
            $length = $tokens[$close_bracket + 1]['length'];
        } else {
            // Confused, so don't check.
            $length = 1;
        }
        if ($length === 1) {
            return;
        }
        $data = [$length];
        $code = 'SpaceBeforeOpenBrace';
        $error = 'There must be a single space between the closing parenthesis and the opening brace of a multi-line IF statement; found ';
        if ($length === -1) {
            $error .= 'newline';
            $code = 'NewlineBeforeOpenBrace';
        } else {
            $error .= '%s spaces';
        }
        $fix = $phpcs_file->add_fixable_error($error, $close_bracket + 1, $code, $data);
        if ($fix === true) {
            if ($length === 0) {
                $phpcs_file->fixer->add_content($close_bracket, ' ');
            } else {
                $phpcs_file->fixer->replace_token($close_bracket + 1, ' ');
            }
        }
    }
    //end process()
}
//end class