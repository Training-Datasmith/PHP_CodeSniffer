<?php

declare (strict_types=1);
/**
 * Checks that there is adequate spacing between comments.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\Commenting;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Inline_Comment_Sniff implements Sniff
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
        return [T_COMMENT, T_DOC_COMMENT_OPEN_TAG];
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
        // If this is a function/class/interface doc block comment, skip it.
        // We are only interested in inline doc block comments, which are
        // not allowed.
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
            $ignore = [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM, T_FUNCTION, T_CLOSURE, T_PUBLIC, T_PRIVATE, T_PROTECTED, T_FINAL, T_STATIC, T_ABSTRACT, T_READONLY, T_CONST, T_PROPERTY, T_INCLUDE, T_INCLUDE_ONCE, T_REQUIRE, T_REQUIRE_ONCE];
            if (in_array($tokens[$next_token]['code'], $ignore, true) === true) {
                return;
            }
            if ($phpcs_file->tokenizer_type === 'JS') {
                // We allow block comments if a function or object
                // is being assigned to a variable.
                $ignore = Tokens::$empty_tokens;
                $ignore[] = T_EQUAL;
                $ignore[] = T_STRING;
                $ignore[] = T_OBJECT_OPERATOR;
                $next_token = $phpcs_file->find_next($ignore, $next_token + 1, null, true);
                if ($tokens[$next_token]['code'] === T_FUNCTION || $tokens[$next_token]['code'] === T_CLOSURE || $tokens[$next_token]['code'] === T_OBJECT || $tokens[$next_token]['code'] === T_PROTOTYPE) {
                    return;
                }
            }
            $prev_token = $phpcs_file->find_previous(Tokens::$empty_tokens, $stack_ptr - 1, null, true);
            if ($tokens[$prev_token]['code'] === T_OPEN_TAG) {
                return;
            }
            if ($tokens[$stack_ptr]['content'] === '/**') {
                $error = 'Inline doc block comments are not allowed; use "/* Comment */" or "// Comment" instead';
                $phpcs_file->add_error($error, $stack_ptr, 'DocBlock');
            }
        }
        //end if
        if ($tokens[$stack_ptr]['content'][0] === '#') {
            $error = 'Perl-style comments are not allowed; use "// Comment" instead';
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'WrongStyle');
            if ($fix === true) {
                $comment = ltrim($tokens[$stack_ptr]['content'], "# \t");
                $phpcs_file->fixer->replace_token($stack_ptr, "// {$comment}");
            }
        }
        // We don't want end of block comments. Check if the last token before the
        // comment is a closing curly brace.
        $previous_content = $phpcs_file->find_previous(T_WHITESPACE, $stack_ptr - 1, null, true);
        if ($tokens[$previous_content]['line'] === $tokens[$stack_ptr]['line']) {
            if ($tokens[$previous_content]['code'] === T_CLOSE_CURLY_BRACKET) {
                return;
            }
            // Special case for JS files.
            if ($tokens[$previous_content]['code'] === T_COMMA || $tokens[$previous_content]['code'] === T_SEMICOLON) {
                $last_content = $phpcs_file->find_previous(T_WHITESPACE, $previous_content - 1, null, true);
                if ($tokens[$last_content]['code'] === T_CLOSE_CURLY_BRACKET) {
                    return;
                }
            }
        }
        // Only want inline comments.
        if (substr($tokens[$stack_ptr]['content'], 0, 2) !== '//') {
            return;
        }
        $comment_tokens = [$stack_ptr];
        $next_comment = $stack_ptr;
        $last_comment = $stack_ptr;
        while (($next_comment = $phpcs_file->find_next(T_COMMENT, $next_comment + 1, null, false)) !== false) {
            if ($tokens[$next_comment]['line'] !== $tokens[$last_comment]['line'] + 1) {
                break;
            }
            // Only want inline comments.
            if (substr($tokens[$next_comment]['content'], 0, 2) !== '//') {
                break;
            }
            // There is a comment on the very next line. If there is
            // no code between the comments, they are part of the same
            // comment block.
            $prev_non_whitespace = $phpcs_file->find_previous(T_WHITESPACE, $next_comment - 1, $last_comment, true);
            if ($prev_non_whitespace !== $last_comment) {
                break;
            }
            $comment_tokens[] = $next_comment;
            $last_comment = $next_comment;
        }
        //end while
        $comment_text = '';
        foreach ($comment_tokens as $last_comment_token) {
            $comment = rtrim($tokens[$last_comment_token]['content']);
            if (trim(substr($comment, 2)) === '') {
                continue;
            }
            $space_count = 0;
            $tab_found = false;
            $comment_length = strlen($comment);
            for ($i = 2; $i < $comment_length; $i++) {
                if ($comment[$i] === "\t") {
                    $tab_found = true;
                    break;
                }
                if ($comment[$i] !== ' ') {
                    break;
                }
                $space_count++;
            }
            $fix = false;
            if ($tab_found === true) {
                $error = 'Tab found before comment text; expected "// %s" but found "%s"';
                $data = [ltrim(substr($comment, 2)), $comment];
                $fix = $phpcs_file->add_fixable_error($error, $last_comment_token, 'TabBefore', $data);
            } elseif ($space_count === 0) {
                $error = 'No space found before comment text; expected "// %s" but found "%s"';
                $data = [substr($comment, 2), $comment];
                $fix = $phpcs_file->add_fixable_error($error, $last_comment_token, 'NoSpaceBefore', $data);
            } elseif ($space_count > 1) {
                $error = 'Expected 1 space before comment text but found %s; use block comment if you need indentation';
                $data = [$space_count, substr($comment, 2 + $space_count), $comment];
                $fix = $phpcs_file->add_fixable_error($error, $last_comment_token, 'SpacingBefore', $data);
            }
            //end if
            if ($fix === true) {
                $new_comment = '// ' . ltrim($tokens[$last_comment_token]['content'], "/\t ");
                $phpcs_file->fixer->replace_token($last_comment_token, $new_comment);
            }
            $comment_text .= trim(substr($tokens[$last_comment_token]['content'], 2));
        }
        //end foreach
        if ($comment_text === '') {
            $error = 'Blank comments are not allowed';
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'Empty');
            if ($fix === true) {
                $phpcs_file->fixer->replace_token($stack_ptr, '');
            }
            return $last_comment_token + 1;
        }
        if (preg_match('/^\p{Ll}/u', $comment_text) === 1) {
            $error = 'Inline comments must start with a capital letter';
            $phpcs_file->add_error($error, $stack_ptr, 'NotCapital');
        }
        // Only check the end of comment character if the start of the comment
        // is a letter, indicating that the comment is just standard text.
        if (preg_match('/^\p{L}/u', $comment_text) === 1) {
            $comment_closer = $comment_text[strlen($comment_text) - 1];
            $accepted_closers = ['full-stops' => '.', 'exclamation marks' => '!', 'or question marks' => '?'];
            if (in_array($comment_closer, $accepted_closers, true) === false) {
                $error = 'Inline comments must end in %s';
                $ender = '';
                foreach ($accepted_closers as $closer_name => $symbol) {
                    $ender .= ' ' . $closer_name . ',';
                }
                $ender = trim($ender, ' ,');
                $data = [$ender];
                $phpcs_file->add_error($error, $last_comment_token, 'InvalidEndChar', $data);
            }
        }
        // Finally, the line below the last comment cannot be empty if this inline
        // comment is on a line by itself.
        if ($tokens[$previous_content]['line'] < $tokens[$stack_ptr]['line']) {
            $next = $phpcs_file->find_next(T_WHITESPACE, $last_comment_token + 1, null, true);
            if ($next === false) {
                // Ignore if the comment is the last non-whitespace token in a file.
                return $last_comment_token + 1;
            }
            if ($tokens[$next]['code'] === T_DOC_COMMENT_OPEN_TAG) {
                // If this inline comment is followed by a docblock,
                // ignore spacing as docblock/function etc spacing rules
                // are likely to conflict with our rules.
                return $last_comment_token + 1;
            }
            $error_code = 'SpacingAfter';
            if (isset($tokens[$stack_ptr]['conditions']) === true) {
                $conditions = $tokens[$stack_ptr]['conditions'];
                $type = end($conditions);
                $condition_ptr = key($conditions);
                if (($type === T_FUNCTION || $type === T_CLOSURE) && $tokens[$condition_ptr]['scope_closer'] === $next) {
                    $error_code = 'SpacingAfterAtFunctionEnd';
                }
            }
            for ($i = $last_comment_token + 1; $i < $phpcs_file->num_tokens; $i++) {
                if ($tokens[$i]['line'] === $tokens[$last_comment_token]['line'] + 1) {
                    if ($tokens[$i]['code'] !== T_WHITESPACE) {
                        return $last_comment_token + 1;
                    }
                } elseif ($tokens[$i]['line'] > $tokens[$last_comment_token]['line'] + 1) {
                    break;
                }
            }
            $error = 'There must be no blank line following an inline comment';
            $fix = $phpcs_file->add_fixable_error($error, $last_comment_token, $error_code);
            if ($fix === true) {
                $phpcs_file->fixer->begin_changeset();
                for ($i = $last_comment_token + 1; $i < $next; $i++) {
                    if ($tokens[$i]['line'] === $tokens[$next]['line']) {
                        break;
                    }
                    $phpcs_file->fixer->replace_token($i, '');
                }
                $phpcs_file->fixer->end_changeset();
            }
        }
        //end if
        return $last_comment_token + 1;
    }
    //end process()
}
//end class