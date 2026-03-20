<?php

declare (strict_types=1);
/**
 * Checks that all file names are lowercased.
 *
 * @author    Andy Grunwald <andygrunwald@gmail.com>
 * @copyright 2010-2014 Andy Grunwald
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Generic\Sniffs\Files;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Lowercased_Filename_Sniff implements Sniff
{
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
        $filename = $phpcs_file->get_filename();
        if ($filename === 'STDIN') {
            return;
        }
        $filename = basename($filename);
        $lowercase_filename = strtolower($filename);
        if ($filename !== $lowercase_filename) {
            $data = [$filename, $lowercase_filename];
            $error = 'Filename "%s" doesn\'t match the expected filename "%s"';
            $phpcs_file->add_error($error, $stack_ptr, 'NotFound', $data);
            $phpcs_file->record_metric($stack_ptr, 'Lowercase filename', 'no');
        } else {
            $phpcs_file->record_metric($stack_ptr, 'Lowercase filename', 'yes');
        }
        // Ignore the rest of the file.
        return $phpcs_file->num_tokens + 1;
    }
    //end process()
}
//end class