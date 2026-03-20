<?php

declare (strict_types=1);
/**
 * Checks the naming of variables and member variables.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\Naming_Conventions;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Abstract_Variable_Sniff;
use Php_code_Sniffer\Util\Common;
use Php_code_Sniffer\Util\Tokens;
class Valid_Variable_Name_Sniff extends Abstract_Variable_Sniff
{
    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token in the
     *                                               stack passed in $tokens.
     *
     * @return void
     */
    protected function process_variable(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        $var_name = ltrim($tokens[$stack_ptr]['content'], '$');
        // If it's a php reserved var, then its ok.
        if (isset($this->php_reserved_vars[$var_name]) === true) {
            return;
        }
        $obj_operator = $phpcs_file->find_next([T_WHITESPACE], $stack_ptr + 1, null, true);
        if ($tokens[$obj_operator]['code'] === T_OBJECT_OPERATOR || $tokens[$obj_operator]['code'] === T_NULLSAFE_OBJECT_OPERATOR) {
            // Check to see if we are using a variable from an object.
            $var = $phpcs_file->find_next([T_WHITESPACE], $obj_operator + 1, null, true);
            if ($tokens[$var]['code'] === T_STRING) {
                $bracket = $phpcs_file->find_next([T_WHITESPACE], $var + 1, null, true);
                if ($tokens[$bracket]['code'] !== T_OPEN_PARENTHESIS) {
                    $obj_var_name = $tokens[$var]['content'];
                    // There is no way for us to know if the var is public or
                    // private, so we have to ignore a leading underscore if there is
                    // one and just check the main part of the variable name.
                    $original_var_name = $obj_var_name;
                    if (substr($obj_var_name, 0, 1) === '_') {
                        $obj_var_name = substr($obj_var_name, 1);
                    }
                    if (Common::is_camel_caps($obj_var_name, false, true, false) === false) {
                        $error = 'Member variable "%s" is not in valid camel caps format';
                        $data = [$original_var_name];
                        $phpcs_file->add_error($error, $var, 'MemberNotCamelCaps', $data);
                    }
                }
                //end if
            }
            //end if
        }
        //end if
        $obj_operator = $phpcs_file->find_previous(T_WHITESPACE, $stack_ptr - 1, null, true);
        if ($tokens[$obj_operator]['code'] === T_DOUBLE_COLON) {
            // The variable lives within a class, and is referenced like
            // this: MyClass::$_variable, so we don't know its scope.
            $obj_var_name = $var_name;
            if (substr($obj_var_name, 0, 1) === '_') {
                $obj_var_name = substr($obj_var_name, 1);
            }
            if (Common::is_camel_caps($obj_var_name, false, true, false) === false) {
                $error = 'Member variable "%s" is not in valid camel caps format';
                $data = [$tokens[$stack_ptr]['content']];
                $phpcs_file->add_error($error, $stack_ptr, 'MemberNotCamelCaps', $data);
            }
            return;
        }
        // There is no way for us to know if the var is public or private,
        // so we have to ignore a leading underscore if there is one and just
        // check the main part of the variable name.
        $original_var_name = $var_name;
        if (substr($var_name, 0, 1) === '_') {
            $in_class = $phpcs_file->has_condition($stack_ptr, Tokens::$oo_scope_tokens);
            if ($in_class === true) {
                $var_name = substr($var_name, 1);
            }
        }
        if (Common::is_camel_caps($var_name, false, true, false) === false) {
            $error = 'Variable "%s" is not in valid camel caps format';
            $data = [$original_var_name];
            $phpcs_file->add_error($error, $stack_ptr, 'NotCamelCaps', $data);
        }
    }
    //end processVariable()
    /**
     * Processes class member variables.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token in the
     *                                               stack passed in $tokens.
     *
     * @return void
     */
    protected function process_member_var(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        $var_name = ltrim($tokens[$stack_ptr]['content'], '$');
        $member_props = $phpcs_file->get_member_properties($stack_ptr);
        if (empty($member_props) === true) {
            // Couldn't get any info about this variable, which
            // generally means it is invalid or possibly has a parse
            // error. Any errors will be reported by the core, so
            // we can ignore it.
            return;
        }
        $public = $member_props['scope'] !== 'private';
        $error_data = [$var_name];
        if ($public === true) {
            if (substr($var_name, 0, 1) === '_') {
                $error = '%s member variable "%s" must not contain a leading underscore';
                $data = [ucfirst($member_props['scope']), $error_data[0]];
                $phpcs_file->add_error($error, $stack_ptr, 'PublicHasUnderscore', $data);
            }
        } else if (substr($var_name, 0, 1) !== '_') {
            $error = 'Private member variable "%s" must contain a leading underscore';
            $phpcs_file->add_error($error, $stack_ptr, 'PrivateNoUnderscore', $error_data);
        }
        // Remove a potential underscore prefix for testing CamelCaps.
        $var_name = ltrim($var_name, '_');
        if (Common::is_camel_caps($var_name, false, true, false) === false) {
            $error = 'Member variable "%s" is not in valid camel caps format';
            $phpcs_file->add_error($error, $stack_ptr, 'MemberNotCamelCaps', $error_data);
        }
    }
    //end processMemberVar()
    /**
     * Processes the variable found within a double quoted string.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the double quoted
     *                                               string.
     *
     * @return void
     */
    protected function process_variable_in_string(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        if (preg_match_all('|[^\\\\]\${?([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)|', $tokens[$stack_ptr]['content'], $matches) !== 0) {
            foreach ($matches[1] as $var_name) {
                // If it's a php reserved var, then its ok.
                if (isset($this->php_reserved_vars[$var_name]) === true) {
                    continue;
                }
                if (Common::is_camel_caps($var_name, false, true, false) === false) {
                    $error = 'Variable "%s" is not in valid camel caps format';
                    $data = [$var_name];
                    $phpcs_file->add_error($error, $stack_ptr, 'StringNotCamelCaps', $data);
                }
            }
        }
    }
    //end processVariableInString()
}
//end class