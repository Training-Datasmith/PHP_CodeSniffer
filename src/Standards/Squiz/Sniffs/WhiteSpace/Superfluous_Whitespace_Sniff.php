<?php

declare (strict_types=1);
/**
 * Checks for unneeded whitespace.
 *
 * Checks that no whitespace precedes the first content of the file, exists
 * after the last content of the file, resides after content on any line, or
 * are two empty lines in functions.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\White_Space;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Superfluous_Whitespace_Sniff implements Sniff
{
    /**
     * A list of tokenizers this sniff supports.
     *
     * @var array
     */
    public $supported_tokenizers = ['PHP', 'JS', 'CSS'];
    /**
     * If TRUE, whitespace rules are not checked for blank lines.
     *
     * Blank lines are those that contain only whitespace.
     *
     * @var boolean
     */
    public $ignore_blank_lines = false;
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO, T_CLOSE_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT_WHITESPACE, T_CLOSURE];
    }
    //end register()
    /**
     * Processes this sniff, when one of its tokens is encountered.
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
        if ($tokens[$stack_ptr]['code'] === T_OPEN_TAG) {
            /*
                Check for start of file whitespace.
            */
            if ($phpcs_file->tokenizer_type !== 'PHP') {
                // The first token is always the open tag inserted when tokenized
                // and the second token is always the first piece of content in
                // the file. If the second token is whitespace, there was
                // whitespace at the start of the file.
                if ($tokens[$stack_ptr + 1]['code'] !== T_WHITESPACE) {
                    return;
                }
                if ($phpcs_file->fixer->enabled === true) {
                    $stack_ptr = $phpcs_file->find_next(T_WHITESPACE, $stack_ptr + 1, null, true);
                }
            } else {
                // If it's the first token, then there is no space.
                if ($stack_ptr === 0) {
                    return;
                }
                $before_open = '';
                for ($i = $stack_ptr - 1; $i >= 0; $i--) {
                    // If we find something that isn't inline html then there is something previous in the file.
                    if ($tokens[$i]['type'] !== 'T_INLINE_HTML') {
                        return;
                    }
                    $before_open .= $tokens[$i]['content'];
                }
                // If we have ended up with inline html make sure it isn't just whitespace.
                if (preg_match('`^[\pZ\s]+$`u', $before_open) !== 1) {
                    return;
                }
            }
            //end if
            $fix = $phpcs_file->add_fixable_error('Additional whitespace found at start of file', $stack_ptr, 'StartFile');
            if ($fix === true) {
                $phpcs_file->fixer->begin_changeset();
                for ($i = 0; $i < $stack_ptr; $i++) {
                    $phpcs_file->fixer->replace_token($i, '');
                }
                $phpcs_file->fixer->end_changeset();
            }
        } elseif ($tokens[$stack_ptr]['code'] === T_CLOSE_TAG) {
            /*
                Check for end of file whitespace.
            */
            if ($phpcs_file->tokenizer_type === 'PHP') {
                if (isset($tokens[$stack_ptr + 1]) === false) {
                    // The close PHP token is the last in the file.
                    return;
                }
                $after_close = '';
                for ($i = $stack_ptr + 1; $i < $phpcs_file->num_tokens; $i++) {
                    // If we find something that isn't inline HTML then there
                    // is more to the file.
                    if ($tokens[$i]['type'] !== 'T_INLINE_HTML') {
                        return;
                    }
                    $after_close .= $tokens[$i]['content'];
                }
                // If we have ended up with inline html make sure it isn't just whitespace.
                if (preg_match('`^[\pZ\s]+$`u', $after_close) !== 1) {
                    return;
                }
            } else {
                // The last token is always the close tag inserted when tokenized
                // and the second last token is always the last piece of content in
                // the file. If the second last token is whitespace, there was
                // whitespace at the end of the file.
                $stack_ptr--;
                // The pointer is now looking at the last content in the file and
                // not the fake PHP end tag the tokenizer inserted.
                if ($tokens[$stack_ptr]['code'] !== T_WHITESPACE) {
                    return;
                }
                // Allow a single newline at the end of the last line in the file.
                if ($tokens[$stack_ptr - 1]['code'] !== T_WHITESPACE && $tokens[$stack_ptr]['content'] === $phpcs_file->eol_char) {
                    return;
                }
            }
            //end if
            $fix = $phpcs_file->add_fixable_error('Additional whitespace found at end of file', $stack_ptr, 'EndFile');
            if ($fix === true) {
                if ($phpcs_file->tokenizer_type !== 'PHP') {
                    $prev = $phpcs_file->find_previous(T_WHITESPACE, $stack_ptr - 1, null, true);
                    $stack_ptr = $prev + 1;
                }
                $phpcs_file->fixer->begin_changeset();
                for ($i = $stack_ptr + 1; $i < $phpcs_file->num_tokens; $i++) {
                    $phpcs_file->fixer->replace_token($i, '');
                }
                $phpcs_file->fixer->end_changeset();
            }
        } else {
            /*
                Check for end of line whitespace.
            */
            // Ignore whitespace that is not at the end of a line.
            if (isset($tokens[$stack_ptr + 1]['line']) === true && $tokens[$stack_ptr + 1]['line'] === $tokens[$stack_ptr]['line']) {
                return;
            }
            // Ignore blank lines if required.
            if ($this->ignore_blank_lines === true && $tokens[$stack_ptr]['code'] === T_WHITESPACE && $tokens[$stack_ptr - 1]['line'] !== $tokens[$stack_ptr]['line']) {
                return;
            }
            $token_content = rtrim($tokens[$stack_ptr]['content'], $phpcs_file->eol_char);
            if (empty($token_content) === false) {
                if ($token_content !== rtrim($token_content)) {
                    $fix = $phpcs_file->add_fixable_error('Whitespace found at end of line', $stack_ptr, 'EndLine');
                    if ($fix === true) {
                        $phpcs_file->fixer->replace_token($stack_ptr, rtrim($token_content) . $phpcs_file->eol_char);
                    }
                }
            } elseif ($tokens[$stack_ptr - 1]['content'] !== rtrim($tokens[$stack_ptr - 1]['content']) && $tokens[$stack_ptr - 1]['line'] === $tokens[$stack_ptr]['line']) {
                $fix = $phpcs_file->add_fixable_error('Whitespace found at end of line', $stack_ptr - 1, 'EndLine');
                if ($fix === true) {
                    $phpcs_file->fixer->replace_token($stack_ptr - 1, rtrim($tokens[$stack_ptr - 1]['content']));
                }
            }
            /*
                Check for multiple blank lines in a function.
            */
            if ($phpcs_file->has_condition($stack_ptr, [T_FUNCTION, T_CLOSURE]) === true && $tokens[$stack_ptr - 1]['line'] < $tokens[$stack_ptr]['line'] && $tokens[$stack_ptr - 2]['line'] === $tokens[$stack_ptr - 1]['line']) {
                // Properties and functions in nested classes have their own rules for spacing.
                $conditions = $tokens[$stack_ptr]['conditions'];
                $deepest_scope = end($conditions);
                if ($deepest_scope === T_ANON_CLASS) {
                    return;
                }
                // This is an empty line and the line before this one is not
                // empty, so this could be the start of a multiple empty
                // line block.
                $next = $phpcs_file->find_next(T_WHITESPACE, $stack_ptr, null, true);
                $lines = $tokens[$next]['line'] - $tokens[$stack_ptr]['line'];
                if ($lines > 1) {
                    $error = 'Functions must not contain multiple empty lines in a row; found %s empty lines';
                    $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'EmptyLines', [$lines]);
                    if ($fix === true) {
                        $phpcs_file->fixer->begin_changeset();
                        $i = $stack_ptr;
                        while ($tokens[$i]['line'] !== $tokens[$next]['line']) {
                            $phpcs_file->fixer->replace_token($i, '');
                            $i++;
                        }
                        $phpcs_file->fixer->add_newline_before($i);
                        $phpcs_file->fixer->end_changeset();
                    }
                }
            }
            //end if
        }
        //end if
    }
    //end process()
}
//end class