<?php

declare (strict_types=1);
/**
 * Ensures method names are correct.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\Naming_Conventions;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Standards\PEAR\Sniffs\Naming_Conventions\Valid_Function_Name_Sniff as PEARValidFunctionNameSniff;
use Php_code_Sniffer\Util\Common;
class Valid_Function_Name_Sniff extends Pear_Valid_Function_Name_Sniff
{
    /**
     * Processes the tokens outside the scope.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being processed.
     * @param int                         $stackPtr  The position where this token was
     *                                               found.
     *
     * @return void
     */
    protected function process_token_outside_scope(File $phpcs_file, $stack_ptr)
    {
        $function_name = $phpcs_file->get_declaration_name($stack_ptr);
        if ($function_name === null) {
            return;
        }
        $error_data = [$function_name];
        // Does this function claim to be magical?
        if (preg_match('|^__[^_]|', $function_name) !== 0) {
            $error = 'Function name "%s" is invalid; only PHP magic methods should be prefixed with a double underscore';
            $phpcs_file->add_error($error, $stack_ptr, 'DoubleUnderscore', $error_data);
            $function_name = ltrim($function_name, '_');
        }
        if (Common::is_camel_caps($function_name, false, true, false) === false) {
            $error = 'Function name "%s" is not in camel caps format';
            $phpcs_file->add_error($error, $stack_ptr, 'NotCamelCaps', $error_data);
        }
    }
    //end processTokenOutsideScope()
}
//end class