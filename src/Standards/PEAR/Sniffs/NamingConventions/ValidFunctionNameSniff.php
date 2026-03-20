<?php

declare (strict_types=1);
/**
 * Ensures method and function names are correct.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\PEAR\Sniffs\Naming_Conventions;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Abstract_Scope_Sniff;
use Php_code_Sniffer\Util\Common;
use Php_code_Sniffer\Util\Tokens;
class Valid_Function_Name_Sniff extends Abstract_Scope_Sniff
{
    /**
     * A list of all PHP magic methods.
     *
     * @var array
     */
    protected $magic_methods = ['construct' => true, 'destruct' => true, 'call' => true, 'callstatic' => true, 'get' => true, 'set' => true, 'isset' => true, 'unset' => true, 'sleep' => true, 'wakeup' => true, 'serialize' => true, 'unserialize' => true, 'tostring' => true, 'invoke' => true, 'set_state' => true, 'clone' => true, 'debuginfo' => true];
    /**
     * A list of all PHP magic functions.
     *
     * @var array
     */
    protected $magic_functions = ['autoload' => true];
    /**
     * Constructs a PEAR_Sniffs_NamingConventions_ValidFunctionNameSniff.
     */
    public function __construct()
    {
        parent::__construct(Tokens::$oo_scope_tokens, [T_FUNCTION], true);
    }
    //end __construct()
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
        $class_name = $phpcs_file->get_declaration_name($curr_scope);
        if (isset($class_name) === false) {
            $class_name = '[Anonymous Class]';
        }
        $error_data = [$class_name . '::' . $method_name];
        $method_name_lc = strtolower($method_name);
        $class_name_lc = strtolower($class_name);
        // Is this a magic method. i.e., is prefixed with "__" ?
        if (preg_match('|^__[^_]|', $method_name) !== 0) {
            $magic_part = substr($method_name_lc, 2);
            if (isset($this->magic_methods[$magic_part]) === true) {
                return;
            }
            $error = 'Method name "%s" is invalid; only PHP magic methods should be prefixed with a double underscore';
            $phpcs_file->add_error($error, $stack_ptr, 'MethodDoubleUnderscore', $error_data);
        }
        // PHP4 constructors are allowed to break our rules.
        if ($method_name_lc === $class_name_lc) {
            return;
        }
        // PHP4 destructors are allowed to break our rules.
        if ($method_name_lc === '_' . $class_name_lc) {
            return;
        }
        $method_props = $phpcs_file->get_method_properties($stack_ptr);
        $scope = $method_props['scope'];
        $scope_specified = $method_props['scope_specified'];
        if ($method_props['scope'] === 'private') {
            $is_public = false;
        } else {
            $is_public = true;
        }
        // If it's a private method, it must have an underscore on the front.
        if ($is_public === false) {
            if ($method_name[0] !== '_') {
                $error = 'Private method name "%s" must be prefixed with an underscore';
                $phpcs_file->add_error($error, $stack_ptr, 'PrivateNoUnderscore', $error_data);
                $phpcs_file->record_metric($stack_ptr, 'Private method prefixed with underscore', 'no');
            } else {
                $phpcs_file->record_metric($stack_ptr, 'Private method prefixed with underscore', 'yes');
            }
        }
        // If it's not a private method, it must not have an underscore on the front.
        if ($is_public === true && $scope_specified === true && $method_name[0] === '_') {
            $error = '%s method name "%s" must not be prefixed with an underscore';
            $data = [ucfirst($scope), $error_data[0]];
            $phpcs_file->add_error($error, $stack_ptr, 'PublicUnderscore', $data);
        }
        $test_method_name = ltrim($method_name, '_');
        if (Common::is_camel_caps($test_method_name, false, true, false) === false) {
            if ($scope_specified === true) {
                $error = '%s method name "%s" is not in camel caps format';
                $data = [ucfirst($scope), $error_data[0]];
                $phpcs_file->add_error($error, $stack_ptr, 'ScopeNotCamelCaps', $data);
            } else {
                $error = 'Method name "%s" is not in camel caps format';
                $phpcs_file->add_error($error, $stack_ptr, 'NotCamelCaps', $error_data);
            }
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
        $function_name = $phpcs_file->get_declaration_name($stack_ptr);
        if ($function_name === null) {
            // Ignore closures.
            return;
        }
        if (ltrim($function_name, '_') === '') {
            // Ignore special functions.
            return;
        }
        $error_data = [$function_name];
        // Is this a magic function. i.e., it is prefixed with "__".
        if (preg_match('|^__[^_]|', $function_name) !== 0) {
            $magic_part = strtolower(substr($function_name, 2));
            if (isset($this->magic_functions[$magic_part]) === true) {
                return;
            }
            $error = 'Function name "%s" is invalid; only PHP magic methods should be prefixed with a double underscore';
            $phpcs_file->add_error($error, $stack_ptr, 'FunctionDoubleUnderscore', $error_data);
        }
        // Function names can be in two parts; the package name and
        // the function name.
        $package_part = '';
        $underscore_pos = strrpos($function_name, '_');
        if ($underscore_pos === false) {
            $camel_caps_part = $function_name;
        } else {
            $package_part = substr($function_name, 0, $underscore_pos);
            $camel_caps_part = substr($function_name, $underscore_pos + 1);
            // We don't care about _'s on the front.
            $package_part = ltrim($package_part, '_');
        }
        // If it has a package part, make sure the first letter is a capital.
        if ($package_part !== '') {
            if ($function_name[0] === '_') {
                $error = 'Function name "%s" is invalid; only private methods should be prefixed with an underscore';
                $phpcs_file->add_error($error, $stack_ptr, 'FunctionUnderscore', $error_data);
            }
            if ($function_name[0] !== strtoupper($function_name[0])) {
                $error = 'Function name "%s" is prefixed with a package name but does not begin with a capital letter';
                $phpcs_file->add_error($error, $stack_ptr, 'FunctionNoCapital', $error_data);
            }
        }
        // If it doesn't have a camel caps part, it's not valid.
        if (trim($camel_caps_part) === '') {
            $error = 'Function name "%s" is not valid; name appears incomplete';
            $phpcs_file->add_error($error, $stack_ptr, 'FunctionInvalid', $error_data);
            return;
        }
        $valid_name = true;
        $new_package_part = $package_part;
        $new_camel_caps_part = $camel_caps_part;
        // Every function must have a camel caps part, so check that first.
        if (Common::is_camel_caps($camel_caps_part, false, true, false) === false) {
            $valid_name = false;
            $new_camel_caps_part = strtolower($camel_caps_part[0]) . substr($camel_caps_part, 1);
        }
        if ($package_part !== '') {
            // Check that each new word starts with a capital.
            $name_bits = explode('_', $package_part);
            $name_bits = array_filter($name_bits);
            foreach ($name_bits as $bit) {
                if ($bit[0] !== strtoupper($bit[0])) {
                    $new_package_part = '';
                    foreach ($name_bits as $bit) {
                        $new_package_part .= strtoupper($bit[0]) . substr($bit, 1) . '_';
                    }
                    $valid_name = false;
                    break;
                }
            }
        }
        if ($valid_name === false) {
            if ($new_package_part === '') {
                $new_name = $new_camel_caps_part;
            } else {
                $new_name = rtrim($new_package_part, '_') . '_' . $new_camel_caps_part;
            }
            $error = 'Function name "%s" is invalid; consider "%s" instead';
            $data = $error_data;
            $data[] = $new_name;
            $phpcs_file->add_error($error, $stack_ptr, 'FunctionNameInvalid', $data);
        }
    }
    //end processTokenOutsideScope()
}
//end class