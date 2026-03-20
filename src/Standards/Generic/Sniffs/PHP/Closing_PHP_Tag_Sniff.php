<?php

declare (strict_types=1);
/**
 * Checks that open PHP tags are paired with closing tags.
 *
 * @author    Stefano Kowalke <blueduck@gmx.net>
 * @copyright 2010-2014 Stefano Kowalke
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Generic\Sniffs\PHP;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Closing_Php_Tag_Sniff implements Sniff
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
     * @return void
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $close_tag = $phpcs_file->find_next(T_CLOSE_TAG, $stack_ptr);
        if ($close_tag === false) {
            $error = 'The PHP open tag does not have a corresponding PHP close tag';
            $phpcs_file->add_error($error, $stack_ptr, 'NotFound');
        }
    }
    //end process()
}
//end class