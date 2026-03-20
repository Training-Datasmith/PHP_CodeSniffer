<?php

declare (strict_types=1);
/**
 * Checks that only one class is declared per file.
 *
 * @author    Andy Grunwald <andygrunwald@gmail.com>
 * @copyright 2010-2014 Andy Grunwald
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Generic\Sniffs\Files;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class One_Class_Per_File_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_CLASS];
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
        $start = $stack_ptr + 1;
        if (isset($tokens[$stack_ptr]['scope_closer']) === true) {
            $start = $tokens[$stack_ptr]['scope_closer'] + 1;
        }
        $next_class = $phpcs_file->find_next($this->register(), $start);
        if ($next_class !== false) {
            $error = 'Only one class is allowed in a file';
            $phpcs_file->add_error($error, $next_class, 'MultipleFound');
        }
    }
    //end process()
}
//end class