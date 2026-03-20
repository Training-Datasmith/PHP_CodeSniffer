<?php

declare (strict_types=1);
/**
 * Checks that the file does not end with a closing tag.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\PSR2\Sniffs\Files;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Closing_Tag_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_OPEN_TAG];
    }
    //end register()
    /**
     * Processes this sniff, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token in
     *                                               the stack passed in $tokens.
     *
     * @return int
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        // Make sure this file only contains PHP code.
        for ($i = 0; $i < $phpcs_file->num_tokens; $i++) {
            if ($tokens[$i]['code'] === T_INLINE_HTML && trim($tokens[$i]['content']) !== '') {
                return $phpcs_file->num_tokens;
            }
        }
        // Find the last non-empty token.
        for ($last = $phpcs_file->num_tokens - 1; $last > 0; $last--) {
            if (trim($tokens[$last]['content']) !== '') {
                break;
            }
        }
        if ($tokens[$last]['code'] === T_CLOSE_TAG) {
            $error = 'A closing tag is not permitted at the end of a PHP file';
            $fix = $phpcs_file->add_fixable_error($error, $last, 'NotAllowed');
            if ($fix === true) {
                $phpcs_file->fixer->begin_changeset();
                $phpcs_file->fixer->replace_token($last, $phpcs_file->eol_char);
                $prev = $phpcs_file->find_previous(Tokens::$empty_tokens, $last - 1, null, true);
                if ($tokens[$prev]['code'] !== T_SEMICOLON && $tokens[$prev]['code'] !== T_CLOSE_CURLY_BRACKET && $tokens[$prev]['code'] !== T_OPEN_TAG) {
                    $phpcs_file->fixer->add_content($prev, ';');
                }
                $phpcs_file->fixer->end_changeset();
            }
            $phpcs_file->record_metric($stack_ptr, 'PHP closing tag at end of PHP-only file', 'yes');
        } else {
            $phpcs_file->record_metric($stack_ptr, 'PHP closing tag at end of PHP-only file', 'no');
        }
        //end if
        // Ignore the rest of the file.
        return $phpcs_file->num_tokens;
    }
    //end process()
}
//end class