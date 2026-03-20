<?php

declare (strict_types=1);
/**
 * Ensures the file ends with a newline character.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Generic\Sniffs\Files;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class End_File_Newline_Sniff implements Sniff
{
    /**
     * A list of tokenizers this sniff supports.
     *
     * @var array
     */
    public $supported_tokenizers = ['PHP', 'JS', 'CSS'];
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
     * @return int
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        // Skip to the end of the file.
        $tokens = $phpcs_file->get_tokens();
        $stack_ptr = $phpcs_file->num_tokens - 1;
        if ($tokens[$stack_ptr]['content'] === '') {
            $stack_ptr--;
        }
        $eol_char_len = strlen($phpcs_file->eol_char);
        $last_chars = substr($tokens[$stack_ptr]['content'], $eol_char_len * -1);
        if ($last_chars !== $phpcs_file->eol_char) {
            $phpcs_file->record_metric($stack_ptr, 'Newline at EOF', 'no');
            $error = 'File must end with a newline character';
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'NotFound');
            if ($fix === true) {
                $phpcs_file->fixer->add_newline($stack_ptr);
            }
        } else {
            $phpcs_file->record_metric($stack_ptr, 'Newline at EOF', 'yes');
        }
        // Ignore the rest of the file.
        return $phpcs_file->num_tokens + 1;
    }
    //end process()
}
//end class