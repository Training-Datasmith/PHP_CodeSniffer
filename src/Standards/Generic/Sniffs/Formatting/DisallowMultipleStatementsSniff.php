<?php

declare (strict_types=1);
/**
 * Ensures each statement is on a line by itself.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Generic\Sniffs\Formatting;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Disallow_Multiple_Statements_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_SEMICOLON];
    }
    //end register()
    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token in
     *                                               the stack passed in $tokens.
     *
     * @return void
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        $fixable = true;
        $prev = $stack_ptr;
        do {
            $prev = $phpcs_file->find_previous([T_SEMICOLON, T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO, T_PHPCS_IGNORE], $prev - 1);
            if ($prev === false || $tokens[$prev]['code'] === T_OPEN_TAG || $tokens[$prev]['code'] === T_OPEN_TAG_WITH_ECHO) {
                $phpcs_file->record_metric($stack_ptr, 'Multiple statements on same line', 'no');
                return;
            }
            if ($tokens[$prev]['code'] === T_PHPCS_IGNORE) {
                $fixable = false;
            }
        } while ($tokens[$prev]['code'] === T_PHPCS_IGNORE);
        // Ignore multiple statements in a FOR condition.
        foreach ([$stack_ptr, $prev] as $check_token) {
            if (isset($tokens[$check_token]['nested_parenthesis']) === true) {
                foreach ($tokens[$check_token]['nested_parenthesis'] as $bracket) {
                    if (isset($tokens[$bracket]['parenthesis_owner']) === false) {
                        // Probably a closure sitting inside a function call.
                        continue;
                    }
                    $owner = $tokens[$bracket]['parenthesis_owner'];
                    if ($tokens[$owner]['code'] === T_FOR) {
                        return;
                    }
                }
            }
        }
        if ($tokens[$prev]['line'] === $tokens[$stack_ptr]['line']) {
            $phpcs_file->record_metric($stack_ptr, 'Multiple statements on same line', 'yes');
            $error = 'Each PHP statement must be on a line by itself';
            $code = 'SameLine';
            if ($fixable === false) {
                $phpcs_file->add_error($error, $stack_ptr, $code);
                return;
            }
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, $code);
            if ($fix === true) {
                $phpcs_file->fixer->begin_changeset();
                $phpcs_file->fixer->add_newline($prev);
                if ($tokens[$prev + 1]['code'] === T_WHITESPACE) {
                    $phpcs_file->fixer->replace_token($prev + 1, '');
                }
                $phpcs_file->fixer->end_changeset();
            }
        } else {
            $phpcs_file->record_metric($stack_ptr, 'Multiple statements on same line', 'no');
        }
        //end if
    }
    //end process()
}
//end class