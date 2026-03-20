<?php

declare (strict_types=1);
/**
 * Verifies that class methods have scope modifiers.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\Scope;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Abstract_Scope_Sniff;
use Php_code_Sniffer\Util\Tokens;
class Method_Scope_Sniff extends Abstract_Scope_Sniff
{
    /**
     * Constructs a Squiz_Sniffs_Scope_MethodScopeSniff.
     */
    public function __construct()
    {
        parent::__construct(Tokens::$oo_scope_tokens, [T_FUNCTION]);
    }
    //end __construct()
    /**
     * Processes the function tokens within the class.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file where this token was found.
     * @param int                         $stackPtr  The position where the token was found.
     * @param int                         $currScope The current scope opener token.
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
        $properties = $phpcs_file->get_method_properties($stack_ptr);
        if ($properties['scope_specified'] === false) {
            $error = 'Visibility must be declared on method "%s"';
            $data = [$method_name];
            $phpcs_file->add_error($error, $stack_ptr, 'Missing', $data);
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
}
//end class