<?php

declare (strict_types=1);
/**
 * Ensures the file ends with a newline character.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\PSR2\Sniffs\Files;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class End_File_Newline_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO];
    }
    //end register()
    /**
     * Processes this sniff, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token in
     *                                               the stack passed in $tokens.
     *
     * @return void
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        if ($phpcs_file->find_next(T_INLINE_HTML, $stack_ptr + 1) !== false) {
            return $phpcs_file->num_tokens + 1;
        }
        // Skip to the end of the file.
        $tokens = $phpcs_file->get_tokens();
        $last_token = $phpcs_file->num_tokens - 1;
        if ($tokens[$last_token]['content'] === '') {
            $last_token--;
        }
        // Hard-coding the expected \n in this sniff as it is PSR-2 specific and
        // PSR-2 enforces the use of unix style newlines.
        if (substr($tokens[$last_token]['content'], -1) !== "\n") {
            $error = 'Expected 1 newline at end of file; 0 found';
            $fix = $phpcs_file->add_fixable_error($error, $last_token, 'NoneFound');
            if ($fix === true) {
                $phpcs_file->fixer->add_newline($last_token);
            }
            $phpcs_file->record_metric($stack_ptr, 'Number of newlines at EOF', '0');
            return $phpcs_file->num_tokens + 1;
        }
        // Go looking for the last non-empty line.
        $last_line = $tokens[$last_token]['line'];
        if ($tokens[$last_token]['code'] === T_WHITESPACE || $tokens[$last_token]['code'] === T_DOC_COMMENT_WHITESPACE) {
            $last_code = $phpcs_file->find_previous([T_WHITESPACE, T_DOC_COMMENT_WHITESPACE], $last_token - 1, null, true);
        } else {
            $last_code = $last_token;
        }
        $last_code_line = $tokens[$last_code]['line'];
        $blank_lines = $last_line - $last_code_line + 1;
        $phpcs_file->record_metric($stack_ptr, 'Number of newlines at EOF', $blank_lines);
        if ($blank_lines > 1) {
            $error = 'Expected 1 blank line at end of file; %s found';
            $data = [$blank_lines];
            $fix = $phpcs_file->add_fixable_error($error, $last_code, 'TooMany', $data);
            if ($fix === true) {
                $phpcs_file->fixer->begin_changeset();
                $phpcs_file->fixer->replace_token($last_code, rtrim($tokens[$last_code]['content']));
                for ($i = $last_code + 1; $i < $last_token; $i++) {
                    $phpcs_file->fixer->replace_token($i, '');
                }
                $phpcs_file->fixer->replace_token($last_token, $phpcs_file->eol_char);
                $phpcs_file->fixer->end_changeset();
            }
        }
        // Skip the rest of the file.
        return $phpcs_file->num_tokens + 1;
    }
    //end process()
}
//end class