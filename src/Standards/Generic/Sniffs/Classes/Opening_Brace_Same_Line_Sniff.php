<?php

declare (strict_types=1);
/**
 * Checks that the opening brace of a class/interface/trait is on the same line as the class declaration.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Generic\Sniffs\Classes;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Opening_Brace_Same_Line_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM];
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
        $scope_identifier = $phpcs_file->find_next(T_STRING, $stack_ptr + 1);
        $error_data = [strtolower($tokens[$stack_ptr]['content']) . ' ' . $tokens[$scope_identifier]['content']];
        if (isset($tokens[$stack_ptr]['scope_opener']) === false) {
            $error = 'Possible parse error: %s missing opening or closing brace';
            $phpcs_file->add_warning($error, $stack_ptr, 'MissingBrace', $error_data);
            return;
        }
        $opening_brace = $tokens[$stack_ptr]['scope_opener'];
        // Is the brace on the same line as the class/interface/trait declaration ?
        $last_class_line_token = $phpcs_file->find_previous(T_WHITESPACE, $opening_brace - 1, $stack_ptr, true);
        $last_class_line = $tokens[$last_class_line_token]['line'];
        $brace_line = $tokens[$opening_brace]['line'];
        $line_difference = $brace_line - $last_class_line;
        if ($line_difference > 0) {
            $phpcs_file->record_metric($stack_ptr, 'Class opening brace placement', 'new line');
            $error = 'Opening brace should be on the same line as the declaration for %s';
            $fix = $phpcs_file->add_fixable_error($error, $opening_brace, 'BraceOnNewLine', $error_data);
            if ($fix === true) {
                $phpcs_file->fixer->begin_changeset();
                $phpcs_file->fixer->add_content($last_class_line_token, ' {');
                $phpcs_file->fixer->replace_token($opening_brace, '');
                $phpcs_file->fixer->end_changeset();
            }
        } else {
            $phpcs_file->record_metric($stack_ptr, 'Class opening brace placement', 'same line');
        }
        // Is the opening brace the last thing on the line ?
        $next = $phpcs_file->find_next(T_WHITESPACE, $opening_brace + 1, null, true);
        if ($tokens[$next]['line'] === $tokens[$opening_brace]['line']) {
            if ($next === $tokens[$stack_ptr]['scope_closer']) {
                // Ignore empty classes.
                return;
            }
            $error = 'Opening brace must be the last content on the line';
            $fix = $phpcs_file->add_fixable_error($error, $opening_brace, 'ContentAfterBrace');
            if ($fix === true) {
                $phpcs_file->fixer->add_newline($opening_brace);
            }
        }
        // Only continue checking if the opening brace looks good.
        if ($line_difference > 0) {
            return;
        }
        // Is there precisely one space before the opening brace ?
        if ($tokens[$opening_brace - 1]['code'] !== T_WHITESPACE) {
            $length = 0;
        } elseif ($tokens[$opening_brace - 1]['content'] === "\t") {
            $length = '\t';
        } else {
            $length = $tokens[$opening_brace - 1]['length'];
        }
        if ($length !== 1) {
            $error = 'Expected 1 space before opening brace; found %s';
            $data = [$length];
            $fix = $phpcs_file->add_fixable_error($error, $opening_brace, 'SpaceBeforeBrace', $data);
            if ($fix === true) {
                if ($length === 0 || $length === '\t') {
                    $phpcs_file->fixer->add_content_before($opening_brace, ' ');
                } else {
                    $phpcs_file->fixer->replace_token($opening_brace - 1, ' ');
                }
            }
        }
    }
    //end process()
}
//end class