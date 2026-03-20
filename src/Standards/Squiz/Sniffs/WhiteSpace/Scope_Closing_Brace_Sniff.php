<?php

declare (strict_types=1);
/**
 * Checks that the closing braces of scopes are aligned correctly.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\White_Space;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Scope_Closing_Brace_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
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
     * @param int                         $stackPtr  The position of the current token in the
     *                                               stack passed in $tokens.
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
        // We need to actually find the first piece of content on this line,
        // as if this is a method with tokens before it (public, static etc)
        // or an if with an else before it, then we need to start the scope
        // checking from there, rather than the current token.
        $line_start = $phpcs_file->find_first_on_line([T_WHITESPACE, T_INLINE_HTML], $stack_ptr, true);
        while ($tokens[$line_start]['code'] === T_CONSTANT_ENCAPSED_STRING && $tokens[$line_start - 1]['code'] === T_CONSTANT_ENCAPSED_STRING) {
            $line_start = $phpcs_file->find_first_on_line([T_WHITESPACE, T_INLINE_HTML], $line_start - 1, true);
        }
        $start_column = $tokens[$line_start]['column'];
        $scope_start = $tokens[$stack_ptr]['scope_opener'];
        $scope_end = $tokens[$stack_ptr]['scope_closer'];
        // Check that the closing brace is on it's own line.
        $last_content = $phpcs_file->find_previous([T_INLINE_HTML, T_WHITESPACE, T_OPEN_TAG], $scope_end - 1, $scope_start, true);
        if ($tokens[$last_content]['line'] === $tokens[$scope_end]['line'] || $tokens[$line_start]['code'] === T_INLINE_HTML && trim($tokens[$line_start]['content']) !== '') {
            $error = 'Closing brace must be on a line by itself';
            $fix = $phpcs_file->add_fixable_error($error, $scope_end, 'ContentBefore');
            if ($fix === true) {
                if ($tokens[$last_content]['line'] === $tokens[$scope_end]['line']) {
                    $phpcs_file->fixer->add_newline_before($scope_end);
                } else {
                    $phpcs_file->fixer->add_newline_before($line_start + 1);
                }
            }
            return;
        }
        // Check now that the closing brace is lined up correctly.
        $line_start = $phpcs_file->find_first_on_line([T_WHITESPACE, T_INLINE_HTML], $scope_end, true);
        $brace_indent = $tokens[$line_start]['column'];
        if ($tokens[$stack_ptr]['code'] !== T_DEFAULT && $tokens[$stack_ptr]['code'] !== T_CASE && $brace_indent !== $start_column) {
            $error = 'Closing brace indented incorrectly; expected %s spaces, found %s';
            $data = [$start_column - 1, $brace_indent - 1];
            $fix = $phpcs_file->add_fixable_error($error, $scope_end, 'Indent', $data);
            if ($fix === true) {
                $diff = $start_column - $brace_indent;
                if ($diff > 0) {
                    $phpcs_file->fixer->add_content_before($line_start, str_repeat(' ', $diff));
                } else {
                    $phpcs_file->fixer->substr_token($line_start - 1, 0, $diff);
                }
            }
        }
        //end if
    }
    //end process()
}
//end class