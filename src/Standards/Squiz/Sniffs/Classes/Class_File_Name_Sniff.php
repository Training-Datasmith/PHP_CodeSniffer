<?php

declare (strict_types=1);
/**
 * Tests that the file name and the name of the class contained within the file match.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\Classes;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Class_File_Name_Sniff implements Sniff
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
     * @param int                         $stackPtr  The position of the current token in
     *                                               the stack passed in $tokens.
     *
     * @return void
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $full_path = basename($phpcs_file->get_filename());
        $file_name = substr($full_path, 0, strrpos($full_path, '.'));
        if ($file_name === '') {
            // No filename probably means STDIN, so we can't do this check.
            return;
        }
        $tokens = $phpcs_file->get_tokens();
        $dec_name = $phpcs_file->find_next(T_STRING, $stack_ptr);
        if ($tokens[$dec_name]['content'] !== $file_name) {
            $error = '%s name doesn\'t match filename; expected "%s %s"';
            $data = [ucfirst($tokens[$stack_ptr]['content']), $tokens[$stack_ptr]['content'], $file_name];
            $phpcs_file->add_error($error, $stack_ptr, 'NoMatch', $data);
        }
    }
    //end process()
}
//end class