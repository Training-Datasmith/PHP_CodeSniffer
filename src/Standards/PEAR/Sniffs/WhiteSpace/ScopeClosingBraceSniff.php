<?php

declare (strict_types=1);
/**
 * Checks that the closing braces of scopes are aligned correctly.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\PEAR\Sniffs\White_Space;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Scope_Closing_Brace_Sniff implements Sniff
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
     * @return int[]
     */
    public function register()
    {
        return Tokens::$scope_openers;
    }
    //end register()
    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile All the tokens found in the document.
     * @param int                         $stackPtr  The position of the current token
     *                                               in the stack passed in $tokens.
     *
     * @return void
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        // If this is an inline condition (ie. there is no scope opener), then
        // return, as this is not a new scope.
        if (isset($tokens[$stack_ptr]['scope_closer']) === false) {
            return;
        }
        $scope_start = $tokens[$stack_ptr]['scope_opener'];
        $scope_end = $tokens[$stack_ptr]['scope_closer'];
        // If the scope closer doesn't think it belongs to this scope opener
        // then the opener is sharing its closer with other tokens. We only
        // want to process the closer once, so skip this one.
        if (isset($tokens[$scope_end]['scope_condition']) === false || $tokens[$scope_end]['scope_condition'] !== $stack_ptr) {
            return;
        }
        // We need to actually find the first piece of content on this line,
        // because if this is a method with tokens before it (public, static etc)
        // or an if with an else before it, then we need to start the scope
        // checking from there, rather than the current token.
        $line_start = $stack_ptr - 1;
        for ($line_start; $line_start > 0; $line_start--) {
            if (strpos($tokens[$line_start]['content'], $phpcs_file->eol_char) !== false) {
                break;
            }
        }
        $line_start++;
        $start_column = 1;
        if ($tokens[$line_start]['code'] === T_WHITESPACE) {
            $start_column = $tokens[$line_start + 1]['column'];
        } elseif ($tokens[$line_start]['code'] === T_INLINE_HTML) {
            $trimmed = ltrim($tokens[$line_start]['content']);
            if ($trimmed === '') {
                $start_column = $tokens[$line_start + 1]['column'];
            } else {
                $start_column = strlen($tokens[$line_start]['content']) - strlen($trimmed);
            }
        }
        // Check that the closing brace is on it's own line.
        $last_content = $phpcs_file->find_previous([T_WHITESPACE, T_INLINE_HTML, T_OPEN_TAG], $scope_end - 1, $scope_start, true);
        if ($tokens[$last_content]['line'] === $tokens[$scope_end]['line']) {
            $error = 'Closing brace must be on a line by itself';
            $fix = $phpcs_file->add_fixable_error($error, $scope_end, 'Line');
            if ($fix === true) {
                $phpcs_file->fixer->add_newline_before($scope_end);
            }
            return;
        }
        // Check now that the closing brace is lined up correctly.
        $line_start = $scope_end - 1;
        for ($line_start; $line_start > 0; $line_start--) {
            if (strpos($tokens[$line_start]['content'], $phpcs_file->eol_char) !== false) {
                break;
            }
        }
        $line_start++;
        $brace_indent = 0;
        if ($tokens[$line_start]['code'] === T_WHITESPACE) {
            $brace_indent = $tokens[$line_start + 1]['column'] - 1;
        } elseif ($tokens[$line_start]['code'] === T_INLINE_HTML) {
            $trimmed = ltrim($tokens[$line_start]['content']);
            if ($trimmed === '') {
                $brace_indent = $tokens[$line_start + 1]['column'] - 1;
            } else {
                $brace_indent = strlen($tokens[$line_start]['content']) - strlen($trimmed) - 1;
            }
        }
        $fix = false;
        if ($tokens[$stack_ptr]['code'] === T_CASE || $tokens[$stack_ptr]['code'] === T_DEFAULT) {
            // BREAK statements should be indented n spaces from the
            // CASE or DEFAULT statement.
            $expected_indent = $start_column + $this->indent - 1;
            if ($brace_indent !== $expected_indent) {
                $error = 'Case breaking statement indented incorrectly; expected %s spaces, found %s';
                $data = [$expected_indent, $brace_indent];
                $fix = $phpcs_file->add_fixable_error($error, $scope_end, 'BreakIndent', $data);
            }
        } else {
            $expected_indent = max(0, $start_column - 1);
            if ($brace_indent !== $expected_indent) {
                $error = 'Closing brace indented incorrectly; expected %s spaces, found %s';
                $data = [$expected_indent, $brace_indent];
                $fix = $phpcs_file->add_fixable_error($error, $scope_end, 'Indent', $data);
            }
        }
        //end if
        if ($fix === true) {
            $spaces = str_repeat(' ', $expected_indent);
            if ($brace_indent === 0) {
                $phpcs_file->fixer->add_content_before($line_start, $spaces);
            } else {
                $phpcs_file->fixer->replace_token($line_start, ltrim($tokens[$line_start]['content']));
                $phpcs_file->fixer->add_content_before($line_start, $spaces);
            }
        }
    }
    //end process()
}
//end class