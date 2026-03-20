<?php

declare (strict_types=1);
/**
 * Ensures method names are defined using camel case.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\PSR1\Sniffs\Methods;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Standards\Generic\Sniffs\Naming_Conventions\Camel_Caps_Function_Name_Sniff as GenericCamelCapsFunctionNameSniff;
use Php_code_Sniffer\Util\Common;
class Camel_Caps_Method_Name_Sniff extends Generic_Camel_Caps_Function_Name_Sniff
{
    /**
     * Processes the tokens within the scope.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being processed.
     * @param int                         $stackPtr  The position where this token was
     *                                               found.
     * @param int                         $currScope The position of the current scope.
     *
     * @return void
     */
    protected function process_token_within_scope(File $phpcs_file, $stack_ptr, $curr_scope)
    {
        $tokens = $phpcs_file->get_tokens();
        // Determine if this is a function which needs to be examined.
        $conditions = $tokens[$stack_ptr]['conditions'];
        end($conditions);
        $deepest_scope = key($conditions);
        if ($deepest_scope !== $curr_scope) {
            return;
        }
        $method_name = $phpcs_file->get_declaration_name($stack_ptr);
        if ($method_name === null) {
            // Ignore closures.
            return;
        }
        // Ignore magic methods.
        if (preg_match('|^__[^_]|', $method_name) !== 0) {
            $magic_part = strtolower(substr($method_name, 2));
            if (isset($this->magic_methods[$magic_part]) === true || isset($this->methods_double_underscore[$magic_part]) === true) {
                return;
            }
        }
        $test_name = ltrim($method_name, '_');
        if ($test_name !== '' && Common::is_camel_caps($test_name, false, true, false) === false) {
            $error = 'Method name "%s" is not in camel caps format';
            $class_name = $phpcs_file->get_declaration_name($curr_scope);
            if (isset($class_name) === false) {
                $class_name = '[Anonymous Class]';
            }
            $error_data = [$class_name . '::' . $method_name];
            $phpcs_file->add_error($error, $stack_ptr, 'NotCamelCaps', $error_data);
            $phpcs_file->record_metric($stack_ptr, 'CamelCase method name', 'no');
        } else {
            $phpcs_file->record_metric($stack_ptr, 'CamelCase method name', 'yes');
        }
    }
    //end processTokenWithinScope()
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
    }
    //end processTokenOutsideScope()
}
//end class