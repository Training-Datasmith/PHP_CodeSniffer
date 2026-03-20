<?php

declare (strict_types=1);
/**
 * Verifies that class members have scope modifiers.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\Scope;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Abstract_Variable_Sniff;
class Member_Var_Scope_Sniff extends Abstract_Variable_Sniff
{
    /**
     * Processes the function tokens within the class.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file where this token was found.
     * @param int                         $stackPtr  The position where the token was found.
     *
     * @return void
     */
    protected function process_member_var(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        $properties = $phpcs_file->get_member_properties($stack_ptr);
        if ($properties === [] || $properties['scope_specified'] !== false) {
            return;
        }
        $error = 'Scope modifier not specified for member variable "%s"';
        $data = [$tokens[$stack_ptr]['content']];
        $phpcs_file->add_error($error, $stack_ptr, 'Missing', $data);
    }
    //end processMemberVar()
    /**
     * Processes normal variables.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file where this token was found.
     * @param int                         $stackPtr  The position where the token was found.
     *
     * @return void
     */
    protected function process_variable(File $phpcs_file, $stack_ptr)
    {
        /*
            We don't care about normal variables.
        */
    }
    //end processVariable()
    /**
     * Processes variables in double quoted strings.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file where this token was found.
     * @param int                         $stackPtr  The position where the token was found.
     *
     * @return void
     */
    protected function process_variable_in_string(File $phpcs_file, $stack_ptr)
    {
        /*
            We don't care about normal variables.
        */
    }
    //end processVariableInString()
}
//end class