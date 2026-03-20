<?php

declare (strict_types=1);
/**
 * Verifies that there are no else if statements (elseif should be used instead).
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\PSR2\Sniffs\Control_Structures;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Else_If_Declaration_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_ELSE, T_ELSEIF];
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
        if ($tokens[$stack_ptr]['code'] === T_ELSEIF) {
            $phpcs_file->record_metric($stack_ptr, 'Use of ELSE IF or ELSEIF', 'elseif');
            return;
        }
        $next = $phpcs_file->find_next(T_WHITESPACE, $stack_ptr + 1, null, true);
        if ($tokens[$next]['code'] === T_IF) {
            $phpcs_file->record_metric($stack_ptr, 'Use of ELSE IF or ELSEIF', 'else if');
            $error = 'Usage of ELSE IF is discouraged; use ELSEIF instead';
            $fix = $phpcs_file->add_fixable_warning($error, $stack_ptr, 'NotAllowed');
            if ($fix === true) {
                $phpcs_file->fixer->begin_changeset();
                $phpcs_file->fixer->replace_token($stack_ptr, 'elseif');
                for ($i = $stack_ptr + 1; $i <= $next; $i++) {
                    $phpcs_file->fixer->replace_token($i, '');
                }
                $phpcs_file->fixer->end_changeset();
            }
        }
    }
    //end process()
}
//end class