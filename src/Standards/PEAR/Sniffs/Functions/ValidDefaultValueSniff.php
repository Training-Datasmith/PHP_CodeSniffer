<?php

declare (strict_types=1);
/**
 * Ensures function params with default values are at the end of the declaration.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\PEAR\Sniffs\Functions;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Valid_Default_Value_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return int[]
     */
    public function register()
    {
        return [T_FUNCTION, T_CLOSURE, T_FN];
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
        // Flag for when we have found a default in our arg list.
        // If there is a value without a default after this, it is an error.
        $default_found = false;
        $params = $phpcs_file->get_method_parameters($stack_ptr);
        foreach ($params as $param) {
            if ($param['variable_length'] === true) {
                continue;
            }
            if (array_key_exists('default', $param) === true) {
                $default_found = true;
                // Check if the arg is type hinted and using NULL for the default.
                // This does not make the argument optional - it just allows NULL
                // to be passed in.
                if ($param['type_hint'] !== '' && strtolower($param['default']) === 'null') {
                    $default_found = false;
                }
                continue;
            }
            if ($default_found === true) {
                $error = 'Arguments with default values must be at the end of the argument list';
                $phpcs_file->add_error($error, $param['token'], 'NotAtEnd');
                return;
            }
        }
        //end foreach
    }
    //end process()
}
//end class