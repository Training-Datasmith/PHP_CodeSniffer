<?php

declare (strict_types=1);
/**
 * Checks the indentation of embedded PHP code segments.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\PHP;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Embedded_Php_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_OPEN_TAG];
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
        // If the close php tag is on the same line as the opening
        // then we have an inline embedded PHP block.
        $close_tag = $phpcs_file->find_next(T_CLOSE_TAG, $stack_ptr);
        if ($close_tag === false || $tokens[$stack_ptr]['line'] !== $tokens[$close_tag]['line']) {
            $this->validate_multiline_embedded_php($phpcs_file, $stack_ptr);
        } else {
            $this->validate_inline_embedded_php($phpcs_file, $stack_ptr);
        }
    }
    //end process()
    /**
     * Validates embedded PHP that exists on multiple lines.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token in the
     *                                               stack passed in $tokens.
     *
     * @return void
     */
    private function validate_multiline_embedded_php(\Php_code_Sniffer\Files\File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        $prev_tag = $phpcs_file->find_previous(T_OPEN_TAG, $stack_ptr - 1);
        if ($prev_tag === false) {
            // This is the first open tag.
            return;
        }
        $first_content = $phpcs_file->find_next(T_WHITESPACE, $stack_ptr + 1, null, true);
        $closing_tag = $phpcs_file->find_next(T_CLOSE_TAG, $stack_ptr);
        if ($closing_tag !== false) {
            $next_content = $phpcs_file->find_next(T_WHITESPACE, $closing_tag + 1, $phpcs_file->num_tokens, true);
            if ($next_content === false) {
                // Final closing tag. It will be handled elsewhere.
                return;
            }
            // We have an opening and a closing tag, that lie within other content.
            if ($first_content === $closing_tag) {
                $error = 'Empty embedded PHP tag found';
                $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'Empty');
                if ($fix === true) {
                    $phpcs_file->fixer->begin_changeset();
                    for ($i = $stack_ptr; $i <= $closing_tag; $i++) {
                        $phpcs_file->fixer->replace_token($i, '');
                    }
                    $phpcs_file->fixer->end_changeset();
                }
                return;
            }
        }
        //end if
        if ($tokens[$first_content]['line'] === $tokens[$stack_ptr]['line']) {
            $error = 'Opening PHP tag must be on a line by itself';
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'ContentAfterOpen');
            if ($fix === true) {
                $first = $phpcs_file->find_first_on_line(T_WHITESPACE, $stack_ptr, true);
                $padding = strlen($tokens[$first]['content']) - strlen(ltrim($tokens[$first]['content']));
                $phpcs_file->fixer->begin_changeset();
                $phpcs_file->fixer->add_newline($stack_ptr);
                $phpcs_file->fixer->add_content($stack_ptr, str_repeat(' ', $padding));
                $phpcs_file->fixer->end_changeset();
            }
        } else {
            // Check the indent of the first line, except if it is a scope closer.
            if (isset($tokens[$first_content]['scope_closer']) === false || $tokens[$first_content]['scope_closer'] !== $first_content) {
                // Check for a blank line at the top.
                if ($tokens[$first_content]['line'] > $tokens[$stack_ptr]['line'] + 1) {
                    // Find a token on the blank line to throw the error on.
                    $i = $stack_ptr;
                    do {
                        $i++;
                    } while ($tokens[$i]['line'] !== $tokens[$stack_ptr]['line'] + 1);
                    $error = 'Blank line found at start of embedded PHP content';
                    $fix = $phpcs_file->add_fixable_error($error, $i, 'SpacingBefore');
                    if ($fix === true) {
                        $phpcs_file->fixer->begin_changeset();
                        for ($i = $stack_ptr + 1; $i < $first_content; $i++) {
                            if ($tokens[$i]['line'] === $tokens[$first_content]['line']) {
                                continue;
                            }
                            if ($tokens[$i]['line'] === $tokens[$stack_ptr]['line']) {
                                continue;
                            }
                            $phpcs_file->fixer->replace_token($i, '');
                        }
                        $phpcs_file->fixer->end_changeset();
                    }
                }
                //end if
                $first = $phpcs_file->find_first_on_line(T_WHITESPACE, $stack_ptr);
                if ($first === false) {
                    $first = $phpcs_file->find_first_on_line(T_INLINE_HTML, $stack_ptr);
                    $indent = strlen($tokens[$first]['content']) - strlen(ltrim($tokens[$first]['content']));
                } else {
                    $indent = $tokens[$first + 1]['column'] - 1;
                }
                $content_column = $tokens[$first_content]['column'] - 1;
                if ($content_column !== $indent) {
                    $error = 'First line of embedded PHP code must be indented %s spaces; %s found';
                    $data = [$indent, $content_column];
                    $fix = $phpcs_file->add_fixable_error($error, $first_content, 'Indent', $data);
                    if ($fix === true) {
                        $padding = str_repeat(' ', $indent);
                        if ($content_column === 0) {
                            $phpcs_file->fixer->add_content_before($first_content, $padding);
                        } else {
                            $phpcs_file->fixer->replace_token($first_content - 1, $padding);
                        }
                    }
                }
            }
            //end if
        }
        //end if
        $last_content = $phpcs_file->find_previous(T_WHITESPACE, $stack_ptr - 1, null, true);
        if ($tokens[$last_content]['line'] === $tokens[$stack_ptr]['line'] && trim($tokens[$last_content]['content']) !== '') {
            $error = 'Opening PHP tag must be on a line by itself';
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'ContentBeforeOpen');
            if ($fix === true) {
                $first = $phpcs_file->find_first_on_line(T_WHITESPACE, $stack_ptr);
                if ($first === false) {
                    $first = $phpcs_file->find_first_on_line(T_INLINE_HTML, $stack_ptr);
                    $padding = strlen($tokens[$first]['content']) - strlen(ltrim($tokens[$first]['content']));
                } else {
                    $padding = $tokens[$first + 1]['column'] - 1;
                }
                $phpcs_file->fixer->add_content_before($stack_ptr, $phpcs_file->eol_char . str_repeat(' ', $padding));
            }
        } else {
            // Find the first token on the first non-empty line we find.
            for ($first = $stack_ptr - 1; $first > 0; $first--) {
                if ($tokens[$first]['line'] === $tokens[$stack_ptr]['line']) {
                    continue;
                }
                if (trim($tokens[$first]['content']) !== '') {
                    $first = $phpcs_file->find_first_on_line([], $first, true);
                    break;
                }
            }
            $expected = 0;
            if ($tokens[$first]['code'] === T_INLINE_HTML && trim($tokens[$first]['content']) !== '') {
                $expected = strlen($tokens[$first]['content']) - strlen(ltrim($tokens[$first]['content']));
            } elseif ($tokens[$first]['code'] === T_WHITESPACE) {
                $expected = $tokens[$first + 1]['column'] - 1;
            }
            $expected += 4;
            $found = $tokens[$stack_ptr]['column'] - 1;
            if ($found > $expected) {
                $error = 'Opening PHP tag indent incorrect; expected no more than %s spaces but found %s';
                $data = [$expected, $found];
                $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'OpenTagIndent', $data);
                if ($fix === true) {
                    $phpcs_file->fixer->replace_token($stack_ptr - 1, str_repeat(' ', $expected));
                }
            }
        }
        //end if
        if ($closing_tag === false) {
            return;
        }
        $last_content = $phpcs_file->find_previous(T_WHITESPACE, $closing_tag - 1, $stack_ptr + 1, true);
        $next_content = $phpcs_file->find_next(T_WHITESPACE, $closing_tag + 1, null, true);
        if ($tokens[$last_content]['line'] === $tokens[$closing_tag]['line']) {
            $error = 'Closing PHP tag must be on a line by itself';
            $fix = $phpcs_file->add_fixable_error($error, $closing_tag, 'ContentBeforeEnd');
            if ($fix === true) {
                $first = $phpcs_file->find_first_on_line(T_WHITESPACE, $closing_tag, true);
                $phpcs_file->fixer->begin_changeset();
                $phpcs_file->fixer->add_content_before($closing_tag, str_repeat(' ', $tokens[$first]['column'] - 1));
                $phpcs_file->fixer->add_newline_before($closing_tag);
                $phpcs_file->fixer->end_changeset();
            }
        } elseif ($tokens[$next_content]['line'] === $tokens[$closing_tag]['line']) {
            $error = 'Closing PHP tag must be on a line by itself';
            $fix = $phpcs_file->add_fixable_error($error, $closing_tag, 'ContentAfterEnd');
            if ($fix === true) {
                $first = $phpcs_file->find_first_on_line(T_WHITESPACE, $closing_tag, true);
                $phpcs_file->fixer->begin_changeset();
                $phpcs_file->fixer->add_newline($closing_tag);
                $phpcs_file->fixer->add_content($closing_tag, str_repeat(' ', $tokens[$first]['column'] - 1));
                $phpcs_file->fixer->end_changeset();
            }
        }
        //end if
        $next = $phpcs_file->find_next(T_OPEN_TAG, $closing_tag + 1);
        if ($next === false) {
            return;
        }
        // Check for a blank line at the bottom.
        if ((isset($tokens[$last_content]['scope_closer']) === false || $tokens[$last_content]['scope_closer'] !== $last_content) && $tokens[$last_content]['line'] < $tokens[$closing_tag]['line'] - 1) {
            // Find a token on the blank line to throw the error on.
            $i = $closing_tag;
            do {
                $i--;
            } while ($tokens[$i]['line'] !== $tokens[$closing_tag]['line'] - 1);
            $error = 'Blank line found at end of embedded PHP content';
            $fix = $phpcs_file->add_fixable_error($error, $i, 'SpacingAfter');
            if ($fix === true) {
                $phpcs_file->fixer->begin_changeset();
                for ($i = $last_content + 1; $i < $closing_tag; $i++) {
                    if ($tokens[$i]['line'] === $tokens[$last_content]['line']) {
                        continue;
                    }
                    if ($tokens[$i]['line'] === $tokens[$closing_tag]['line']) {
                        continue;
                    }
                    $phpcs_file->fixer->replace_token($i, '');
                }
                $phpcs_file->fixer->end_changeset();
            }
        }
        //end if
    }
    //end validateMultilineEmbeddedPhp()
    /**
     * Validates embedded PHP that exists on one line.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token in the
     *                                               stack passed in $tokens.
     *
     * @return void
     */
    private function validate_inline_embedded_php(\Php_code_Sniffer\Files\File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        // We only want one line PHP sections, so return if the closing tag is
        // on the next line.
        $close_tag = $phpcs_file->find_next(T_CLOSE_TAG, $stack_ptr, null, false);
        if ($tokens[$stack_ptr]['line'] !== $tokens[$close_tag]['line']) {
            return;
        }
        // Check that there is one, and only one space at the start of the statement.
        $first_content = $phpcs_file->find_next(T_WHITESPACE, $stack_ptr + 1, $close_tag, true);
        if ($first_content === false) {
            $error = 'Empty embedded PHP tag found';
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'Empty');
            if ($fix === true) {
                $phpcs_file->fixer->begin_changeset();
                for ($i = $stack_ptr; $i <= $close_tag; $i++) {
                    $phpcs_file->fixer->replace_token($i, '');
                }
                $phpcs_file->fixer->end_changeset();
            }
            return;
        }
        // The open tag token always contains a single space after it.
        $leading_space = 1;
        if ($tokens[$stack_ptr + 1]['code'] === T_WHITESPACE) {
            $leading_space = $tokens[$stack_ptr + 1]['length'] + 1;
        }
        if ($leading_space !== 1) {
            $error = 'Expected 1 space after opening PHP tag; %s found';
            $data = [$leading_space];
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'SpacingAfterOpen', $data);
            if ($fix === true) {
                $phpcs_file->fixer->replace_token($stack_ptr + 1, '');
            }
        }
        $prev = $phpcs_file->find_previous(Tokens::$empty_tokens, $close_tag - 1, $stack_ptr, true);
        if ($prev !== $stack_ptr) {
            if ((isset($tokens[$prev]['scope_opener']) === false || $tokens[$prev]['scope_opener'] !== $prev) && (isset($tokens[$prev]['scope_closer']) === false || $tokens[$prev]['scope_closer'] !== $prev) && $tokens[$prev]['code'] !== T_SEMICOLON) {
                $error = 'Inline PHP statement must end with a semicolon';
                $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'NoSemicolon');
                if ($fix === true) {
                    $phpcs_file->fixer->add_content($prev, ';');
                }
            } elseif ($tokens[$prev]['code'] === T_SEMICOLON) {
                $statement_count = 1;
                for ($i = $stack_ptr + 1; $i < $prev; $i++) {
                    if ($tokens[$i]['code'] === T_SEMICOLON) {
                        $statement_count++;
                    }
                }
                if ($statement_count > 1) {
                    $error = 'Inline PHP statement must contain a single statement; %s found';
                    $data = [$statement_count];
                    $phpcs_file->add_error($error, $stack_ptr, 'MultipleStatements', $data);
                }
            }
        }
        //end if
        $trailing_space = 0;
        if ($tokens[$close_tag - 1]['code'] === T_WHITESPACE) {
            $trailing_space = $tokens[$close_tag - 1]['length'];
        } elseif (($tokens[$close_tag - 1]['code'] === T_COMMENT || isset(Tokens::$phpcs_comment_tokens[$tokens[$close_tag - 1]['code']]) === true) && substr($tokens[$close_tag - 1]['content'], -1) === ' ') {
            $trailing_space = strlen($tokens[$close_tag - 1]['content']) - strlen(rtrim($tokens[$close_tag - 1]['content']));
        }
        if ($trailing_space !== 1) {
            $error = 'Expected 1 space before closing PHP tag; %s found';
            $data = [$trailing_space];
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'SpacingBeforeClose', $data);
            if ($fix === true) {
                if ($trailing_space === 0) {
                    $phpcs_file->fixer->add_content_before($close_tag, ' ');
                } elseif ($tokens[$close_tag - 1]['code'] === T_COMMENT || isset(Tokens::$phpcs_comment_tokens[$tokens[$close_tag - 1]['code']]) === true) {
                    $phpcs_file->fixer->replace_token($close_tag - 1, rtrim($tokens[$close_tag - 1]['content']) . ' ');
                } else {
                    $phpcs_file->fixer->replace_token($close_tag - 1, ' ');
                }
            }
        }
    }
    //end validateInlineEmbeddedPhp()
}
//end class