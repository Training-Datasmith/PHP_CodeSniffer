<?php

declare (strict_types=1);
/**
 * Tests that the stars in a doc comment align correctly.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\Commenting;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Doc_Comment_Alignment_Sniff implements Sniff
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
        return [T_DOC_COMMENT_OPEN_TAG];
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
        // We are only interested in function/class/interface doc block comments.
        $ignore = Tokens::$empty_tokens;
        if ($phpcs_file->tokenizer_type === 'JS') {
            $ignore[] = T_EQUAL;
            $ignore[] = T_STRING;
            $ignore[] = T_OBJECT_OPERATOR;
        }
        $next_token = $phpcs_file->find_next($ignore, $stack_ptr + 1, null, true);
        $ignore = [T_CLASS => true, T_INTERFACE => true, T_ENUM => true, T_ENUM_CASE => true, T_FUNCTION => true, T_PUBLIC => true, T_PRIVATE => true, T_PROTECTED => true, T_STATIC => true, T_ABSTRACT => true, T_PROPERTY => true, T_OBJECT => true, T_PROTOTYPE => true, T_VAR => true, T_READONLY => true];
        if ($next_token === false || isset($ignore[$tokens[$next_token]['code']]) === false) {
            // Could be a file comment.
            $prev_token = $phpcs_file->find_previous(Tokens::$empty_tokens, $stack_ptr - 1, null, true);
            if ($tokens[$prev_token]['code'] !== T_OPEN_TAG) {
                return;
            }
        }
        // There must be one space after each star (unless it is an empty comment line)
        // and all the stars must be aligned correctly.
        $required_column = $tokens[$stack_ptr]['column'] + 1;
        $end_comment = $tokens[$stack_ptr]['comment_closer'];
        for ($i = $stack_ptr + 1; $i <= $end_comment; $i++) {
            if ($tokens[$i]['code'] !== T_DOC_COMMENT_STAR && $tokens[$i]['code'] !== T_DOC_COMMENT_CLOSE_TAG) {
                continue;
            }
            if ($tokens[$i]['code'] === T_DOC_COMMENT_CLOSE_TAG) {
                if (trim($tokens[$i]['content']) === '') {
                    // Don't process an unfinished docblock close tag during live coding.
                    continue;
                }
                // Can't process the close tag if it is not the first thing on the line.
                $prev = $phpcs_file->find_previous(T_DOC_COMMENT_WHITESPACE, $i - 1, $stack_ptr, true);
                if ($tokens[$prev]['line'] === $tokens[$i]['line']) {
                    continue;
                }
            }
            if ($tokens[$i]['column'] !== $required_column) {
                $error = 'Expected %s space(s) before asterisk; %s found';
                $data = [$required_column - 1, $tokens[$i]['column'] - 1];
                $fix = $phpcs_file->add_fixable_error($error, $i, 'SpaceBeforeStar', $data);
                if ($fix === true) {
                    $padding = str_repeat(' ', $required_column - 1);
                    if ($tokens[$i]['column'] === 1) {
                        $phpcs_file->fixer->add_content_before($i, $padding);
                    } else {
                        $phpcs_file->fixer->replace_token($i - 1, $padding);
                    }
                }
            }
            if ($tokens[$i]['code'] !== T_DOC_COMMENT_STAR) {
                continue;
            }
            if ($tokens[$i + 2]['line'] !== $tokens[$i]['line']) {
                // Line is empty.
                continue;
            }
            if ($tokens[$i + 1]['code'] !== T_DOC_COMMENT_WHITESPACE) {
                $error = 'Expected 1 space after asterisk; 0 found';
                $fix = $phpcs_file->add_fixable_error($error, $i, 'NoSpaceAfterStar');
                if ($fix === true) {
                    $phpcs_file->fixer->add_content($i, ' ');
                }
            } elseif ($tokens[$i + 2]['code'] === T_DOC_COMMENT_TAG && $tokens[$i + 1]['content'] !== ' ') {
                $error = 'Expected 1 space after asterisk; %s found';
                $data = [$tokens[$i + 1]['length']];
                $fix = $phpcs_file->add_fixable_error($error, $i, 'SpaceAfterStar', $data);
                if ($fix === true) {
                    $phpcs_file->fixer->replace_token($i + 1, ' ');
                }
            }
        }
        //end for
    }
    //end process()
}
//end class