<?php

declare (strict_types=1);
/**
 * Verifies that block comments are used appropriately.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\Commenting;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Block_Comment_Sniff implements Sniff
{
    /**
     * The --tab-width CLI value that is being used.
     *
     * @var integer
     */
    private $tab_width;
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_COMMENT, T_DOC_COMMENT_OPEN_TAG];
    }
    //end register()
    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The current file being scanned.
     * @param int                         $stackPtr  The position of the current token in the
     *                                               stack passed in $tokens.
     *
     * @return void
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        if ($this->tab_width === null) {
            if (isset($phpcs_file->config->tab_width) === false || $phpcs_file->config->tab_width === 0) {
                // We have no idea how wide tabs are, so assume 4 spaces for fixing.
                $this->tab_width = 4;
            } else {
                $this->tab_width = $phpcs_file->config->tab_width;
            }
        }
        $tokens = $phpcs_file->get_tokens();
        // If it's an inline comment, return.
        if (substr($tokens[$stack_ptr]['content'], 0, 2) !== '/*') {
            return;
        }
        // If this is a function/class/interface doc block comment, skip it.
        // We are only interested in inline doc block comments.
        if ($tokens[$stack_ptr]['code'] === T_DOC_COMMENT_OPEN_TAG) {
            $next_token = $stack_ptr;
            do {
                $next_token = $phpcs_file->find_next(Tokens::$empty_tokens, $next_token + 1, null, true);
                if ($tokens[$next_token]['code'] === T_ATTRIBUTE) {
                    $next_token = $tokens[$next_token]['attribute_closer'];
                    continue;
                }
                break;
            } while (true);
            $ignore = [T_CLASS => true, T_INTERFACE => true, T_TRAIT => true, T_ENUM => true, T_FUNCTION => true, T_PUBLIC => true, T_PRIVATE => true, T_FINAL => true, T_PROTECTED => true, T_STATIC => true, T_ABSTRACT => true, T_CONST => true, T_VAR => true, T_READONLY => true];
            if (isset($ignore[$tokens[$next_token]['code']]) === true) {
                return;
            }
            $prev_token = $phpcs_file->find_previous(Tokens::$empty_tokens, $stack_ptr - 1, null, true);
            if ($tokens[$prev_token]['code'] === T_OPEN_TAG) {
                return;
            }
            $error = 'Block comments must be started with /*';
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'WrongStart');
            if ($fix === true) {
                $phpcs_file->fixer->replace_token($stack_ptr, '/*');
            }
            $end = $tokens[$stack_ptr]['comment_closer'];
            if ($tokens[$end]['content'] !== '*/') {
                $error = 'Block comments must be ended with */';
                $fix = $phpcs_file->add_fixable_error($error, $end, 'WrongEnd');
                if ($fix === true) {
                    $phpcs_file->fixer->replace_token($end, '*/');
                }
            }
            return;
        }
        //end if
        $comment_lines = [$stack_ptr];
        $next_comment = $stack_ptr;
        $last_line = $tokens[$stack_ptr]['line'];
        $comment_string = $tokens[$stack_ptr]['content'];
        // Construct the comment into an array.
        while (($next_comment = $phpcs_file->find_next(T_WHITESPACE, $next_comment + 1, null, true)) !== false) {
            if ($tokens[$next_comment]['code'] !== $tokens[$stack_ptr]['code'] && isset(Tokens::$phpcs_comment_tokens[$tokens[$next_comment]['code']]) === false) {
                // Found the next bit of code.
                break;
            }
            if ($tokens[$next_comment]['line'] - 1 !== $last_line) {
                // Not part of the block.
                break;
            }
            $last_line = $tokens[$next_comment]['line'];
            $comment_lines[] = $next_comment;
            $comment_string .= $tokens[$next_comment]['content'];
            if ($tokens[$next_comment]['code'] === T_DOC_COMMENT_CLOSE_TAG || substr($tokens[$next_comment]['content'], -2) === '*/') {
                break;
            }
        }
        //end while
        $comment_text = str_replace($phpcs_file->eol_char, '', $comment_string);
        $comment_text = trim($comment_text, "/* \t");
        if ($comment_text === '') {
            $error = 'Empty block comment not allowed';
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'Empty');
            if ($fix === true) {
                $phpcs_file->fixer->begin_changeset();
                $phpcs_file->fixer->replace_token($stack_ptr, '');
                $last_token = array_pop($comment_lines);
                for ($i = $stack_ptr + 1; $i <= $last_token; $i++) {
                    $phpcs_file->fixer->replace_token($i, '');
                }
                $phpcs_file->fixer->end_changeset();
            }
            return;
        }
        if (count($comment_lines) === 1) {
            $error = 'Single line block comment not allowed; use inline ("// text") comment instead';
            // Only fix comments when they are the last token on a line.
            $next_non_empty = $phpcs_file->find_next(Tokens::$empty_tokens, $stack_ptr + 1, null, true);
            if ($tokens[$stack_ptr]['line'] !== $tokens[$next_non_empty]['line']) {
                $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'SingleLine');
                if ($fix === true) {
                    $comment = '// ' . $comment_text . $phpcs_file->eol_char;
                    $phpcs_file->fixer->replace_token($stack_ptr, $comment);
                }
            } else {
                $phpcs_file->add_error($error, $stack_ptr, 'SingleLine');
            }
            return;
        }
        $content = trim($tokens[$stack_ptr]['content']);
        if ($content !== '/*' && $content !== '/**') {
            $error = 'Block comment text must start on a new line';
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'NoNewLine');
            if ($fix === true) {
                $indent = '';
                if ($tokens[$stack_ptr - 1]['code'] === T_WHITESPACE) {
                    if (isset($tokens[$stack_ptr - 1]['orig_content']) === true) {
                        $indent = $tokens[$stack_ptr - 1]['orig_content'];
                    } else {
                        $indent = $tokens[$stack_ptr - 1]['content'];
                    }
                }
                $comment = preg_replace('/^(\s*\/\*\*?)/', '$1' . $phpcs_file->eol_char . $indent, $tokens[$stack_ptr]['content'], 1);
                $phpcs_file->fixer->replace_token($stack_ptr, $comment);
            }
            return;
        }
        //end if
        $star_column = $tokens[$stack_ptr]['column'];
        $has_stars = false;
        // Make sure first line isn't blank.
        if (trim($tokens[$comment_lines[1]]['content']) === '') {
            $error = 'Empty line not allowed at start of comment';
            $fix = $phpcs_file->add_fixable_error($error, $comment_lines[1], 'HasEmptyLine');
            if ($fix === true) {
                $phpcs_file->fixer->replace_token($comment_lines[1], '');
            }
        } else {
            // Check indentation of first line.
            $content = $tokens[$comment_lines[1]]['content'];
            $comment_text = ltrim($content);
            $leading_space = strlen($content) - strlen($comment_text);
            $expected = $star_column + 3;
            if ($comment_text[0] === '*') {
                $expected = $star_column;
                $has_stars = true;
            }
            if ($leading_space !== $expected) {
                $expected_txt = $expected . ' space';
                if ($expected !== 1) {
                    $expected_txt .= 's';
                }
                $data = [$expected_txt, $leading_space];
                $error = 'First line of comment not aligned correctly; expected %s but found %s';
                $fix = $phpcs_file->add_fixable_error($error, $comment_lines[1], 'FirstLineIndent', $data);
                if ($fix === true) {
                    if (isset($tokens[$comment_lines[1]]['orig_content']) === true && $tokens[$comment_lines[1]]['orig_content'][0] === "\t") {
                        // Line is indented using tabs.
                        $padding = str_repeat("\t", floor($expected / $this->tab_width));
                        $padding .= str_repeat(' ', $expected % $this->tab_width);
                    } else {
                        $padding = str_repeat(' ', $expected);
                    }
                    $phpcs_file->fixer->replace_token($comment_lines[1], $padding . $comment_text);
                }
            }
            //end if
            if (preg_match('/^\p{Ll}/u', $comment_text) === 1) {
                $error = 'Block comments must start with a capital letter';
                $phpcs_file->add_error($error, $comment_lines[1], 'NoCapital');
            }
        }
        //end if
        // Check that each line of the comment is indented past the star.
        foreach ($comment_lines as $line) {
            // First and last lines (comment opener and closer) are handled separately.
            if ($line === $comment_lines[count($comment_lines) - 1]) {
                continue;
            }
            if ($line === $comment_lines[0]) {
                continue;
            }
            // First comment line was handled above.
            if ($line === $comment_lines[1]) {
                continue;
            }
            // If it's empty, continue.
            if (trim($tokens[$line]['content']) === '') {
                continue;
            }
            $comment_text = ltrim($tokens[$line]['content']);
            $leading_space = strlen($tokens[$line]['content']) - strlen($comment_text);
            $expected = $star_column + 3;
            if ($comment_text[0] === '*') {
                $expected = $star_column;
                $has_stars = true;
            }
            if ($leading_space < $expected) {
                $expected_txt = $expected . ' space';
                if ($expected !== 1) {
                    $expected_txt .= 's';
                }
                $data = [$expected_txt, $leading_space];
                $error = 'Comment line indented incorrectly; expected at least %s but found %s';
                $fix = $phpcs_file->add_fixable_error($error, $line, 'LineIndent', $data);
                if ($fix === true) {
                    if (isset($tokens[$line]['orig_content']) === true && $tokens[$line]['orig_content'][0] === "\t") {
                        // Line is indented using tabs.
                        $padding = str_repeat("\t", floor($expected / $this->tab_width));
                        $padding .= str_repeat(' ', $expected % $this->tab_width);
                    } else {
                        $padding = str_repeat(' ', $expected);
                    }
                    $phpcs_file->fixer->replace_token($line, $padding . $comment_text);
                }
            }
            //end if
        }
        //end foreach
        // Finally, test the last line is correct.
        $last_index = count($comment_lines) - 1;
        $content = $tokens[$comment_lines[$last_index]]['content'];
        $comment_text = ltrim($content);
        if ($comment_text !== '*/' && $comment_text !== '**/') {
            $error = 'Comment closer must be on a new line';
            $phpcs_file->add_error($error, $comment_lines[$last_index], 'CloserSameLine');
        } else {
            $leading_space = strlen($content) - strlen($comment_text);
            $expected = $star_column - 1;
            if ($has_stars === true) {
                $expected = $star_column;
            }
            if ($leading_space !== $expected) {
                $expected_txt = $expected . ' space';
                if ($expected !== 1) {
                    $expected_txt .= 's';
                }
                $data = [$expected_txt, $leading_space];
                $error = 'Last line of comment aligned incorrectly; expected %s but found %s';
                $fix = $phpcs_file->add_fixable_error($error, $comment_lines[$last_index], 'LastLineIndent', $data);
                if ($fix === true) {
                    if (isset($tokens[$line]['orig_content']) === true && $tokens[$line]['orig_content'][0] === "\t") {
                        // Line is indented using tabs.
                        $padding = str_repeat("\t", floor($expected / $this->tab_width));
                        $padding .= str_repeat(' ', $expected % $this->tab_width);
                    } else {
                        $padding = str_repeat(' ', $expected);
                    }
                    $phpcs_file->fixer->replace_token($comment_lines[$last_index], $padding . $comment_text);
                }
            }
            //end if
        }
        //end if
        // Check that the lines before and after this comment are blank.
        $content_before = $phpcs_file->find_previous(T_WHITESPACE, $stack_ptr - 1, null, true);
        if (isset($tokens[$content_before]['scope_closer']) === true && $tokens[$content_before]['scope_opener'] === $content_before || $tokens[$content_before]['code'] === T_OPEN_TAG || $tokens[$content_before]['code'] === T_OPEN_TAG_WITH_ECHO) {
            if ($tokens[$stack_ptr]['line'] - $tokens[$content_before]['line'] !== 1) {
                $error = 'Empty line not required before block comment';
                $phpcs_file->add_error($error, $stack_ptr, 'HasEmptyLineBefore');
            }
        } else if ($tokens[$stack_ptr]['line'] - $tokens[$content_before]['line'] < 2) {
            $error = 'Empty line required before block comment';
            $phpcs_file->add_error($error, $stack_ptr, 'NoEmptyLineBefore');
        }
        $comment_closer = $comment_lines[$last_index];
        $content_after = $phpcs_file->find_next(T_WHITESPACE, $comment_closer + 1, null, true);
        if ($content_after !== false && $tokens[$content_after]['line'] - $tokens[$comment_closer]['line'] < 2) {
            $error = 'Empty line required after block comment';
            $phpcs_file->add_error($error, $comment_closer, 'NoEmptyLineAfter');
        }
    }
    //end process()
}
//end class