<?php

declare(strict_types=1);
/**
 * A testable implementation of \Php_code_Sniffer\Sniffs\Abstract_Array_Sniff.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2020 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */

namespace Php_code_Sniffer\Tests\Core\Sniffs;

use Php_code_Sniffer\Sniffs\Abstract_Array_Sniff;

class AbstractArraySniffTestable extends Abstract_Array_Sniff
{
    /**
     * The array indicies that were found during processing.
     *
     * @var array
     */
    public $indicies = [];

    /**
     * Processes a single-line array definition.
     *
     * @param \Php_code_Sniffer\Files\File $phpcsFile  The current file being checked.
     * @param int                         $stackPtr   The position of the current token
     *                                                in the stack passed in $tokens.
     * @param int                         $arrayStart The token that starts the array definition.
     * @param int                         $arrayEnd   The token that ends the array definition.
     * @param array                       $indices    An array of token positions for the array keys,
     *                                                double arrows, and values.
     *
     * @return void
     */
    protected function process_single_line_array($phpcs_file, $stack_ptr, $array_start, $array_end, $indices)
    {
        $this->indicies = $indices;

    }//end process_single_line_array()

    /**
     * Processes a multi-line array definition.
     *
     * @param \Php_code_Sniffer\Files\File $phpcsFile  The current file being checked.
     * @param int                         $stackPtr   The position of the current token
     *                                                in the stack passed in $tokens.
     * @param int                         $arrayStart The token that starts the array definition.
     * @param int                         $arrayEnd   The token that ends the array definition.
     * @param array                       $indices    An array of token positions for the array keys,
     *                                                double arrows, and values.
     *
     * @return void
     */
    protected function process_multi_line_array($phpcs_file, $stack_ptr, $array_start, $array_end, $indices)
    {
        $this->indicies = $indices;

    }//end process_multi_line_array()

}//end class
