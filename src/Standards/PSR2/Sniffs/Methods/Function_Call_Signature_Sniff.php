<?php

declare (strict_types=1);
/**
 * Checks that the function call format is correct.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\PSR2\Sniffs\Methods;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Standards\PEAR\Sniffs\Functions\Function_Call_Signature_Sniff as PEARFunctionCallSignatureSniff;
use Php_code_Sniffer\Util\Tokens;
class Function_Call_Signature_Sniff extends Pear_Function_Call_Signature_Sniff
{
    /**
     * If TRUE, multiple arguments can be defined per line in a multi-line call.
     *
     * @var boolean
     */
    public $allow_multiple_arguments = false;
    /**
     * Processes single-line calls.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile   The file being scanned.
     * @param int                         $stackPtr    The position of the current token
     *                                                 in the stack passed in $tokens.
     * @param int                         $openBracket The position of the opening bracket
     *                                                 in the stack passed in $tokens.
     * @param array                       $tokens      The stack of tokens that make up
     *                                                 the file.
     *
     * @return void
     */
    public function is_multi_line_call(File $phpcs_file, $stack_ptr, $open_bracket, $tokens)
    {
        // If the first argument is on a new line, this is a multi-line
        // function call, even if there is only one argument.
        $next = $phpcs_file->find_next(Tokens::$empty_tokens, $open_bracket + 1, null, true);
        if ($tokens[$next]['line'] !== $tokens[$stack_ptr]['line']) {
            return true;
        }
        $close_bracket = $tokens[$open_bracket]['parenthesis_closer'];
        $end = $phpcs_file->find_end_of_statement($open_bracket + 1, [T_COLON]);
        while ($tokens[$end]['code'] === T_COMMA) {
            // If the next bit of code is not on the same line, this is a
            // multi-line function call.
            $next = $phpcs_file->find_next(Tokens::$empty_tokens, $end + 1, $close_bracket, true);
            if ($next === false) {
                return false;
            }
            if ($tokens[$next]['line'] !== $tokens[$end]['line']) {
                return true;
            }
            $end = $phpcs_file->find_end_of_statement($next, [T_COLON]);
        }
        // We've reached the last argument, so see if the next content
        // (should be the close bracket) is also on the same line.
        $next = $phpcs_file->find_next(Tokens::$empty_tokens, $end + 1, $close_bracket, true);
        if ($next !== false && $tokens[$next]['line'] !== $tokens[$end]['line']) {
            return true;
        }
        return false;
    }
    //end isMultiLineCall()
}
//end class