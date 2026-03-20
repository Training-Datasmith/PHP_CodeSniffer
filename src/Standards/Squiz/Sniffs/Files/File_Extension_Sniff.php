<?php

declare (strict_types=1);
/**
 * Tests that classes and interfaces are not declared in .php files.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\Files;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class File_Extension_Sniff implements Sniff
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
     * Processes this test, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token in the
     *                                               stack passed in $tokens.
     *
     * @return int
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        $file_name = $phpcs_file->get_filename();
        $extension = substr($file_name, strrpos($file_name, '.'));
        $next_class = $phpcs_file->find_next([T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], $stack_ptr);
        if ($next_class !== false) {
            $phpcs_file->record_metric($stack_ptr, 'File extension for class files', $extension);
            if ($extension === '.php') {
                $error = '%s found in ".php" file; use ".inc" extension instead';
                $data = [ucfirst($tokens[$next_class]['content'])];
                $phpcs_file->add_error($error, $stack_ptr, 'ClassFound', $data);
            }
        } else {
            $phpcs_file->record_metric($stack_ptr, 'File extension for non-class files', $extension);
            if ($extension === '.inc') {
                $error = 'No interface or class found in ".inc" file; use ".php" extension instead';
                $phpcs_file->add_error($error, $stack_ptr, 'NoClass');
            }
        }
        // Ignore the rest of the file.
        return $phpcs_file->num_tokens + 1;
    }
    //end process()
}
//end class