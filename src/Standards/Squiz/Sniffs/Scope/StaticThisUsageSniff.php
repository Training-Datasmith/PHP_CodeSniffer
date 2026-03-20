<?php

declare (strict_types=1);
/**
 * Checks for usage of $this in static methods, which will cause runtime errors.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\Scope;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Abstract_Scope_Sniff;
use Php_code_Sniffer\Util\Tokens;
class Static_This_Usage_Sniff extends Abstract_Scope_Sniff
{
    /**
     * Constructs the test with the tokens it wishes to listen for.
     */
    public function __construct()
    {
        parent::__construct([T_CLASS, T_TRAIT, T_ENUM, T_ANON_CLASS], [T_FUNCTION]);
    }
    //end __construct()
    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The current file being scanned.
     * @param int                         $stackPtr  The position of the current token in the
     *                                               stack passed in $tokens.
     * @param int                         $currScope A pointer to the start of the scope.
     *
     * @return void
     */
    public function process_token_within_scope(File $phpcs_file, $stack_ptr, $curr_scope)
    {
        $tokens = $phpcs_file->get_tokens();
        // Determine if this is a function which needs to be examined.
        $conditions = $tokens[$stack_ptr]['conditions'];
        end($conditions);
        $deepest_scope = key($conditions);
        if ($deepest_scope !== $curr_scope) {
            return;
        }
        // Ignore abstract functions.
        if (isset($tokens[$stack_ptr]['scope_closer']) === false) {
            return;
        }
        $next = $phpcs_file->find_next(Tokens::$empty_tokens, $stack_ptr + 1, null, true);
        if ($next === false || $tokens[$next]['code'] !== T_STRING) {
            // Not a function declaration, or incomplete.
            return;
        }
        $method_props = $phpcs_file->get_method_properties($stack_ptr);
        if ($method_props['is_static'] === false) {
            return;
        }
        $next = $stack_ptr;
        $end = $tokens[$stack_ptr]['scope_closer'];
        $this->check_this_usage($phpcs_file, $next, $end);
    }
    //end processTokenWithinScope()
    /**
     * Check for $this variable usage between $next and $end tokens.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The current file being scanned.
     * @param int                         $next      The position of the next token to check.
     * @param int                         $end       The position of the last token to check.
     *
     * @return void
     */
    private function check_this_usage(File $phpcs_file, $next, $end)
    {
        $tokens = $phpcs_file->get_tokens();
        do {
            $next = $phpcs_file->find_next([T_VARIABLE, T_ANON_CLASS], $next + 1, $end);
            if ($next === false) {
                continue;
            }
            if ($tokens[$next]['code'] === T_ANON_CLASS) {
                $this->check_this_usage($phpcs_file, $next, $tokens[$next]['scope_opener']);
                $next = $tokens[$next]['scope_closer'];
                continue;
            }
            if ($tokens[$next]['content'] !== '$this') {
                continue;
            }
            $error = 'Usage of "$this" in static methods will cause runtime errors';
            $phpcs_file->add_error($error, $next, 'Found');
        } while ($next !== false);
    }
    //end checkThisUsage()
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