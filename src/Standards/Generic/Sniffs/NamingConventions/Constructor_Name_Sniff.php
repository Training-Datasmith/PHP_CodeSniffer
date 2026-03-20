<?php

declare (strict_types=1);
/**
 * Bans PHP 4 style constructors.
 *
 * Favour PHP 5 constructor syntax, which uses "function __construct()".
 * Avoid PHP 4 constructor syntax, which uses "function ClassName()".
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @author    Leif Wickland <lwickland@rightnow.com>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Generic\Sniffs\Naming_Conventions;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Abstract_Scope_Sniff;
class Constructor_Name_Sniff extends Abstract_Scope_Sniff
{
    /**
     * The name of the class we are currently checking.
     *
     * @var string
     */
    private $current_class = '';
    /**
     * A list of functions in the current class.
     *
     * @var string[]
     */
    private $function_list = [];
    /**
     * Constructs the test with the tokens it wishes to listen for.
     */
    public function __construct()
    {
        parent::__construct([T_CLASS, T_ANON_CLASS], [T_FUNCTION], true);
    }
    //end __construct()
    /**
     * Processes this test when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The current file being scanned.
     * @param int                         $stackPtr  The position of the current token
     *                                               in the stack passed in $tokens.
     * @param int                         $currScope A pointer to the start of the scope.
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
        $class_name = $phpcs_file->get_declaration_name($curr_scope);
        if (empty($class_name) === false) {
            // Not an anonymous class.
            $class_name = strtolower($class_name);
        }
        if ($class_name !== $this->current_class) {
            $this->load_function_names_in_scope($phpcs_file, $curr_scope);
            $this->current_class = $class_name;
        }
        $method_name = strtolower($phpcs_file->get_declaration_name($stack_ptr));
        if ($method_name === $class_name) {
            if (in_array('__construct', $this->function_list, true) === false) {
                $error = 'PHP4 style constructors are not allowed; use "__construct()" instead';
                $phpcs_file->add_error($error, $stack_ptr, 'OldStyle');
            }
        } elseif ($method_name !== '__construct') {
            // Not a constructor.
            return;
        }
        // Stop if the constructor doesn't have a body, like when it is abstract.
        if (isset($tokens[$stack_ptr]['scope_closer']) === false) {
            return;
        }
        $parent_class_name = strtolower($phpcs_file->find_extended_class_name($curr_scope));
        if ($parent_class_name === false) {
            return;
        }
        $end_function_index = $tokens[$stack_ptr]['scope_closer'];
        $start_index = $stack_ptr;
        while (($double_colon_index = $phpcs_file->find_next(T_DOUBLE_COLON, $start_index, $end_function_index)) !== false) {
            if ($tokens[$double_colon_index + 1]['code'] === T_STRING && strtolower($tokens[$double_colon_index + 1]['content']) === $parent_class_name) {
                $error = 'PHP4 style calls to parent constructors are not allowed; use "parent::__construct()" instead';
                $phpcs_file->add_error($error, $double_colon_index + 1, 'OldStyleCall');
            }
            $start_index = $double_colon_index + 1;
        }
    }
    //end processTokenWithinScope()
    /**
     * Processes a token that is found within the scope that this test is
     * listening to.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file where this token was found.
     * @param int                         $stackPtr  The position in the stack where this
     *                                               token was found.
     *
     * @return void
     */
    protected function process_token_outside_scope(File $phpcs_file, $stack_ptr)
    {
    }
    //end processTokenOutsideScope()
    /**
     * Extracts all the function names found in the given scope.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The current file being scanned.
     * @param int                         $currScope A pointer to the start of the scope.
     *
     * @return void
     */
    protected function load_function_names_in_scope(File $phpcs_file, $curr_scope)
    {
        $this->function_list = [];
        $tokens = $phpcs_file->get_tokens();
        for ($i = $tokens[$curr_scope]['scope_opener'] + 1; $i < $tokens[$curr_scope]['scope_closer']; $i++) {
            if ($tokens[$i]['code'] !== T_FUNCTION) {
                continue;
            }
            $this->function_list[] = trim(strtolower($phpcs_file->get_declaration_name($i)));
            if (isset($tokens[$i]['scope_closer']) !== false) {
                // Skip past nested functions and such.
                $i = $tokens[$i]['scope_closer'];
            }
        }
    }
    //end loadFunctionNamesInScope()
}
//end class