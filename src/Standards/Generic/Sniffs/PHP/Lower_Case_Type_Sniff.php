<?php

declare (strict_types=1);
/**
 * Checks that all PHP types are lowercase.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Generic\Sniffs\PHP;

use Php_code_Sniffer\Exceptions\RuntimeException;
use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Lower_Case_Type_Sniff implements Sniff
{
    /**
     * Native types supported by PHP.
     *
     * @var array
     */
    private $php_types = ['self' => true, 'parent' => true, 'array' => true, 'callable' => true, 'bool' => true, 'float' => true, 'int' => true, 'string' => true, 'iterable' => true, 'void' => true, 'object' => true, 'mixed' => true, 'static' => true, 'false' => true, 'null' => true, 'never' => true];
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        $tokens = Tokens::$cast_tokens;
        $tokens[] = T_FUNCTION;
        $tokens[] = T_CLOSURE;
        $tokens[] = T_FN;
        $tokens[] = T_VARIABLE;
        return $tokens;
    }
    //end register()
    /**
     * Processes this sniff, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token in the
     *                                               stack passed in $tokens.
     *
     * @return void
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        if (isset(Tokens::$cast_tokens[$tokens[$stack_ptr]['code']]) === true) {
            // A cast token.
            $this->process_type($phpcs_file, $stack_ptr, $tokens[$stack_ptr]['content'], 'PHP type casts must be lowercase; expected "%s" but found "%s"', 'TypeCastFound');
            return;
        }
        /*
         * Check property types.
         */
        if ($tokens[$stack_ptr]['code'] === T_VARIABLE) {
            try {
                $props = $phpcs_file->get_member_properties($stack_ptr);
            } catch (RuntimeException $e) {
                // Not an OO property.
                return;
            }
            // Strip off potential nullable indication.
            $type = ltrim($props['type'], '?');
            if ($type !== '') {
                $error = 'PHP property type declarations must be lowercase; expected "%s" but found "%s"';
                $error_code = 'PropertyTypeFound';
                if ($props['type_token'] === T_TYPE_INTERSECTION) {
                    // Intersection types don't support simple types.
                } elseif (strpos($type, '|') !== false) {
                    $this->process_union_type($phpcs_file, $props['type_token'], $props['type_end_token'], $error, $error_code);
                } elseif (isset($this->php_types[strtolower($type)]) === true) {
                    $this->process_type($phpcs_file, $props['type_token'], $type, $error, $error_code);
                }
            }
            return;
        }
        //end if
        /*
         * Check function return type.
         */
        $props = $phpcs_file->get_method_properties($stack_ptr);
        // Strip off potential nullable indication.
        $return_type = ltrim($props['return_type'], '?');
        if ($return_type !== '') {
            $error = 'PHP return type declarations must be lowercase; expected "%s" but found "%s"';
            $error_code = 'ReturnTypeFound';
            if ($props['return_type_token'] === T_TYPE_INTERSECTION) {
                // Intersection types don't support simple types.
            } elseif (strpos($return_type, '|') !== false) {
                $this->process_union_type($phpcs_file, $props['return_type_token'], $props['return_type_end_token'], $error, $error_code);
            } elseif (isset($this->php_types[strtolower($return_type)]) === true) {
                $this->process_type($phpcs_file, $props['return_type_token'], $return_type, $error, $error_code);
            }
        }
        /*
         * Check function parameter types.
         */
        $params = $phpcs_file->get_method_parameters($stack_ptr);
        if (empty($params) === true) {
            return;
        }
        foreach ($params as $param) {
            // Strip off potential nullable indication.
            $type_hint = ltrim($param['type_hint'], '?');
            if ($type_hint !== '') {
                $error = 'PHP parameter type declarations must be lowercase; expected "%s" but found "%s"';
                $error_code = 'ParamTypeFound';
                if ($param['type_hint_token'] === T_TYPE_INTERSECTION) {
                    // Intersection types don't support simple types.
                } elseif (strpos($type_hint, '|') !== false) {
                    $this->process_union_type($phpcs_file, $param['type_hint_token'], $param['type_hint_end_token'], $error, $error_code);
                } elseif (isset($this->php_types[strtolower($type_hint)]) === true) {
                    $this->process_type($phpcs_file, $param['type_hint_token'], $type_hint, $error, $error_code);
                }
            }
        }
        //end foreach
    }
    //end process()
    /**
     * Processes a union type declaration.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile     The file being scanned.
     * @param int                         $typeDeclStart The position of the start of the type token.
     * @param int                         $typeDeclEnd   The position of the end of the type token.
     * @param string                      $error         Error message template.
     * @param string                      $errorCode     The error code.
     *
     * @return void
     */
    protected function process_union_type(File $phpcs_file, $type_decl_start, $type_decl_end, $error, $error_code)
    {
        $tokens = $phpcs_file->get_tokens();
        $current = $type_decl_start;
        do {
            $end_of_type = $phpcs_file->find_next(T_TYPE_UNION, $current, $type_decl_end);
            if ($end_of_type === false) {
                // This must be the last type in the union.
                $end_of_type = $type_decl_end + 1;
            }
            $has_ns_sep = $phpcs_file->find_next(T_NS_SEPARATOR, $current, $end_of_type);
            if ($has_ns_sep !== false) {
                // Multi-token class based type. Ignore.
                $current = $end_of_type + 1;
                continue;
            }
            // Type consisting of a single token.
            $start_of_type = $phpcs_file->find_next(Tokens::$empty_tokens, $current, $end_of_type, true);
            if ($start_of_type === false) {
                // Parse error.
                return;
            }
            $type = $tokens[$start_of_type]['content'];
            if (isset($this->php_types[strtolower($type)]) === true) {
                $this->process_type($phpcs_file, $start_of_type, $type, $error, $error_code);
            }
            $current = $end_of_type + 1;
        } while ($current <= $type_decl_end);
    }
    //end processUnionType()
    /**
     * Processes a type cast or a singular type declaration.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the type token.
     * @param string                      $type      The type found.
     * @param string                      $error     Error message template.
     * @param string                      $errorCode The error code.
     *
     * @return void
     */
    protected function process_type(File $phpcs_file, $stack_ptr, $type, $error, $error_code)
    {
        $type_lower = strtolower($type);
        if ($type_lower === $type) {
            $phpcs_file->record_metric($stack_ptr, 'PHP type case', 'lower');
            return;
        }
        if ($type === strtoupper($type)) {
            $phpcs_file->record_metric($stack_ptr, 'PHP type case', 'upper');
        } else {
            $phpcs_file->record_metric($stack_ptr, 'PHP type case', 'mixed');
        }
        $data = [$type_lower, $type];
        $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, $error_code, $data);
        if ($fix === true) {
            $phpcs_file->fixer->replace_token($stack_ptr, $type_lower);
        }
    }
    //end processType()
}
//end class