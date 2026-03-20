<?php

declare (strict_types=1);
/**
 * Ensures method and functions are named correctly.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Generic\Sniffs\Naming_Conventions;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Abstract_Scope_Sniff;
use Php_code_Sniffer\Util\Common;
use Php_code_Sniffer\Util\Tokens;
class Camel_Caps_Function_Name_Sniff extends Abstract_Scope_Sniff
{
    /**
     * A list of all PHP magic methods.
     *
     * @var array
     */
    protected $magic_methods = ['construct' => true, 'destruct' => true, 'call' => true, 'callstatic' => true, 'get' => true, 'set' => true, 'isset' => true, 'unset' => true, 'sleep' => true, 'wakeup' => true, 'serialize' => true, 'unserialize' => true, 'tostring' => true, 'invoke' => true, 'set_state' => true, 'clone' => true, 'debuginfo' => true];
    /**
     * A list of all PHP non-magic methods starting with a double underscore.
     *
     * These come from PHP modules such as SOAPClient.
     *
     * @var array
     */
    protected $methods_double_underscore = ['dorequest' => true, 'getcookies' => true, 'getfunctions' => true, 'getlastrequest' => true, 'getlastrequestheaders' => true, 'getlastresponse' => true, 'getlastresponseheaders' => true, 'gettypes' => true, 'setcookie' => true, 'setlocation' => true, 'setsoapheaders' => true, 'soapcall' => true];
    /**
     * A list of all PHP magic functions.
     *
     * @var array
     */
    protected $magic_functions = ['autoload' => true];
    /**
     * If TRUE, the string must not have two capital letters next to each other.
     *
     * @var boolean
     */
    public $strict = true;
    /**
     * Constructs a Generic_Sniffs_NamingConventions_CamelCapsFunctionNameSniff.
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
            if (isset($this->magic_methods[$magic_part]) === true || isset($this->methods_double_underscore[$magic_part]) === true) {
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
        // Ignore first underscore in methods prefixed with "_".
        $method_name = ltrim($method_name, '_');
        $method_props = $phpcs_file->get_method_properties($stack_ptr);
        if (Common::is_camel_caps($method_name, false, true, $this->strict) === false) {
            if ($method_props['scope_specified'] === true) {
                $error = '%s method name "%s" is not in camel caps format';
                $data = [ucfirst($method_props['scope']), $error_data[0]];
                $phpcs_file->add_error($error, $stack_ptr, 'ScopeNotCamelCaps', $data);
            } else {
                $error = 'Method name "%s" is not in camel caps format';
                $phpcs_file->add_error($error, $stack_ptr, 'NotCamelCaps', $error_data);
            }
            $phpcs_file->record_metric($stack_ptr, 'CamelCase method name', 'no');
            return;
        }
        $phpcs_file->record_metric($stack_ptr, 'CamelCase method name', 'yes');
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
        // Ignore first underscore in functions prefixed with "_".
        $function_name = ltrim($function_name, '_');
        if (Common::is_camel_caps($function_name, false, true, $this->strict) === false) {
            $error = 'Function name "%s" is not in camel caps format';
            $phpcs_file->add_error($error, $stack_ptr, 'NotCamelCaps', $error_data);
            $phpcs_file->record_metric($stack_ptr, 'CamelCase function name', 'no');
        } else {
            $phpcs_file->record_metric($stack_ptr, 'CamelCase method name', 'yes');
        }
    }
    //end processTokenOutsideScope()
}
//end class