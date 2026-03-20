<?php

declare (strict_types=1);
/**
 * Checks the naming of member variables.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\PEAR\Sniffs\Naming_Conventions;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Abstract_Variable_Sniff;
class Valid_Variable_Name_Sniff extends Abstract_Variable_Sniff
{
    /**
     * Processes class member variables.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token
     *                                               in the stack passed in $tokens.
     *
     * @return void
     */
    protected function process_member_var(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        $member_props = $phpcs_file->get_member_properties($stack_ptr);
        if (empty($member_props) === true) {
            return;
        }
        $member_name = ltrim($tokens[$stack_ptr]['content'], '$');
        $scope = $member_props['scope'];
        $scope_specified = $member_props['scope_specified'];
        if ($member_props['scope'] === 'private') {
            $is_public = false;
        } else {
            $is_public = true;
        }
        // If it's a private member, it must have an underscore on the front.
        if ($is_public === false && $member_name[0] !== '_') {
            $error = 'Private member variable "%s" must be prefixed with an underscore';
            $data = [$member_name];
            $phpcs_file->add_error($error, $stack_ptr, 'PrivateNoUnderscore', $data);
            return;
        }
        // If it's not a private member, it must not have an underscore on the front.
        if ($is_public === true && $scope_specified === true && $member_name[0] === '_') {
            $error = '%s member variable "%s" must not be prefixed with an underscore';
            $data = [ucfirst($scope), $member_name];
            $phpcs_file->add_error($error, $stack_ptr, 'PublicUnderscore', $data);
            return;
        }
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