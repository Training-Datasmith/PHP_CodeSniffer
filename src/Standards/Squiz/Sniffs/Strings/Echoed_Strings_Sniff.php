<?php

declare (strict_types=1);
/**
 * Makes sure that any strings that are "echoed" are not enclosed in brackets.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\Strings;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Echoed_Strings_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_ECHO];
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
        $first_content = $phpcs_file->find_next(T_WHITESPACE, $stack_ptr + 1, null, true);
        // If the first non-whitespace token is not an opening parenthesis, then we are not concerned.
        if ($tokens[$first_content]['code'] !== T_OPEN_PARENTHESIS) {
            $phpcs_file->record_metric($stack_ptr, 'Brackets around echoed strings', 'no');
            return;
        }
        $end = $phpcs_file->find_next([T_SEMICOLON, T_CLOSE_TAG], $stack_ptr, null, false);
        // If the token before the semi-colon is not a closing parenthesis, then we are not concerned.
        $prev = $phpcs_file->find_previous(T_WHITESPACE, $end - 1, null, true);
        if ($tokens[$prev]['code'] !== T_CLOSE_PARENTHESIS) {
            $phpcs_file->record_metric($stack_ptr, 'Brackets around echoed strings', 'no');
            return;
        }
        // If the parenthesis don't match, then we are not concerned.
        if ($tokens[$first_content]['parenthesis_closer'] !== $prev) {
            $phpcs_file->record_metric($stack_ptr, 'Brackets around echoed strings', 'no');
            return;
        }
        $phpcs_file->record_metric($stack_ptr, 'Brackets around echoed strings', 'yes');
        if ($phpcs_file->find_next(Tokens::$operators, $stack_ptr, $end, false) === false) {
            // There are no arithmetic operators in this.
            $error = 'Echoed strings should not be bracketed';
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'HasBracket');
            if ($fix === true) {
                $phpcs_file->fixer->begin_changeset();
                $phpcs_file->fixer->replace_token($first_content, '');
                if ($tokens[$first_content - 1]['code'] !== T_WHITESPACE) {
                    $phpcs_file->fixer->add_content($first_content - 1, ' ');
                }
                $phpcs_file->fixer->replace_token($prev, '');
                $phpcs_file->fixer->end_changeset();
            }
        }
    }
    //end process()
}
//end class