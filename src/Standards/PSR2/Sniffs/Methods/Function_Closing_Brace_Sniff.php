<?php

declare (strict_types=1);
/**
 * Checks that the closing brace of a function goes directly after the body.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\PSR2\Sniffs\Methods;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Function_Closing_Brace_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_FUNCTION, T_CLOSURE];
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
        $tokens = $phpcs_file->get_tokens();
        if (isset($tokens[$stack_ptr]['scope_closer']) === false) {
            // Probably an interface method.
            return;
        }
        $close_brace = $tokens[$stack_ptr]['scope_closer'];
        $prev_content = $phpcs_file->find_previous(T_WHITESPACE, $close_brace - 1, null, true);
        $found = $tokens[$close_brace]['line'] - $tokens[$prev_content]['line'] - 1;
        if ($found < 0) {
            // Brace isn't on a new line, so not handled by us.
            return;
        }
        if ($found === 0) {
            // All is good.
            return;
        }
        $error = 'Function closing brace must go on the next line following the body; found %s blank lines before brace';
        $data = [$found];
        $fix = $phpcs_file->add_fixable_error($error, $close_brace, 'SpacingBeforeClose', $data);
        if ($fix === true) {
            $phpcs_file->fixer->begin_changeset();
            for ($i = $prev_content + 1; $i < $close_brace; $i++) {
                if ($tokens[$i]['line'] === $tokens[$prev_content]['line']) {
                    continue;
                }
                // Don't remove any indentation before the brace.
                if ($tokens[$i]['line'] === $tokens[$close_brace]['line']) {
                    break;
                }
                $phpcs_file->fixer->replace_token($i, '');
            }
            $phpcs_file->fixer->end_changeset();
        }
    }
    //end process()
}
//end class