<?php

declare (strict_types=1);
/**
 * A class to find T_VARIABLE tokens.
 *
 * This class can distinguish between normal T_VARIABLE tokens, and those tokens
 * that represent class members. If a class member is encountered, then the
 * processMemberVar method is called so the extending class can process it. If
 * the token is found to be a normal T_VARIABLE token, then processVariable is
 * called.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Sniffs;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Util\Tokens;
abstract class Abstract_Variable_Sniff extends Abstract_Scope_Sniff
{
    /**
     * List of PHP Reserved variables.
     *
     * Used by various naming convention sniffs.
     *
     * @var array
     */
    protected $php_reserved_vars = ['_SERVER' => true, '_GET' => true, '_POST' => true, '_REQUEST' => true, '_SESSION' => true, '_ENV' => true, '_COOKIE' => true, '_FILES' => true, 'GLOBALS' => true, 'http_response_header' => true, 'HTTP_RAW_POST_DATA' => true, 'php_errormsg' => true];
    /**
     * Constructs an AbstractVariableTest.
     */
    public function __construct()
    {
        $scopes = Tokens::$oo_scope_tokens;
        $listen = [T_VARIABLE, T_DOUBLE_QUOTED_STRING, T_HEREDOC];
        parent::__construct($scopes, $listen, true);
    }
    //end __construct()
    /**
     * Processes the token in the specified PHP_CodeSniffer\Files\File.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The PHP_CodeSniffer file where this
     *                                               token was found.
     * @param int                         $stackPtr  The position where the token was found.
     * @param int                         $currScope The current scope opener token.
     *
     * @return void|int Optionally returns a stack pointer. The sniff will not be
     *                  called again on the current file until the returned stack
     *                  pointer is reached. Return ($phpcsFile->numTokens + 1) to skip
     *                  the rest of the file.
     */
    final protected function process_token_within_scope(File $phpcs_file, $stack_ptr, $curr_scope)
    {
        $tokens = $phpcs_file->get_tokens();
        if ($tokens[$stack_ptr]['code'] === T_DOUBLE_QUOTED_STRING || $tokens[$stack_ptr]['code'] === T_HEREDOC) {
            // Check to see if this string has a variable in it.
            $pattern = '|(?<!\\\\)(?:\\\\{2})*\${?[a-zA-Z0-9_]+}?|';
            if (preg_match($pattern, $tokens[$stack_ptr]['content']) !== 0) {
                return $this->process_variable_in_string($phpcs_file, $stack_ptr);
            }
            return;
        }
        // If this token is nested inside a function at a deeper
        // level than the current OO scope that was found, it's a normal
        // variable and not a member var.
        $conditions = array_reverse($tokens[$stack_ptr]['conditions'], true);
        $in_function = false;
        foreach ($conditions as $scope => $code) {
            if (isset(Tokens::$oo_scope_tokens[$code]) === true) {
                break;
            }
            if ($code === T_FUNCTION || $code === T_CLOSURE) {
                $in_function = true;
            }
        }
        if ($scope !== $curr_scope) {
            // We found a closer scope to this token, so ignore
            // this particular time through the sniff. We will process
            // this token when this closer scope is found to avoid
            // duplicate checks.
            return;
        }
        // Just make sure this isn't a variable in a function declaration.
        if ($in_function === false && isset($tokens[$stack_ptr]['nested_parenthesis']) === true) {
            foreach ($tokens[$stack_ptr]['nested_parenthesis'] as $opener => $closer) {
                if (isset($tokens[$opener]['parenthesis_owner']) === false) {
                    // Check if this is a USE statement for a closure.
                    $prev = $phpcs_file->find_previous(Tokens::$empty_tokens, $opener - 1, null, true);
                    if ($tokens[$prev]['code'] === T_USE) {
                        $in_function = true;
                        break;
                    }
                    continue;
                }
                $owner = $tokens[$opener]['parenthesis_owner'];
                if ($tokens[$owner]['code'] === T_FUNCTION || $tokens[$owner]['code'] === T_CLOSURE) {
                    $in_function = true;
                    break;
                }
            }
        }
        //end if
        if ($in_function === true) {
            return $this->process_variable($phpcs_file, $stack_ptr);
        }
        return $this->process_member_var($phpcs_file, $stack_ptr);
    }
    //end processTokenWithinScope()
    /**
     * Processes the token outside the scope in the file.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The PHP_CodeSniffer file where this
     *                                               token was found.
     * @param int                         $stackPtr  The position where the token was found.
     *
     * @return void|int Optionally returns a stack pointer. The sniff will not be
     *                  called again on the current file until the returned stack
     *                  pointer is reached. Return ($phpcsFile->numTokens + 1) to skip
     *                  the rest of the file.
     */
    final protected function process_token_outside_scope(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        // These variables are not member vars.
        if ($tokens[$stack_ptr]['code'] === T_VARIABLE) {
            return $this->process_variable($phpcs_file, $stack_ptr);
        }
        if ($tokens[$stack_ptr]['code'] === T_DOUBLE_QUOTED_STRING || $tokens[$stack_ptr]['code'] === T_HEREDOC) {
            // Check to see if this string has a variable in it.
            $pattern = '|(?<!\\\\)(?:\\\\{2})*\${?[a-zA-Z0-9_]+}?|';
            if (preg_match($pattern, $tokens[$stack_ptr]['content']) !== 0) {
                return $this->process_variable_in_string($phpcs_file, $stack_ptr);
            }
        }
    }
    //end processTokenOutsideScope()
    /**
     * Called to process class member vars.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The PHP_CodeSniffer file where this
     *                                               token was found.
     * @param int                         $stackPtr  The position where the token was found.
     *
     * @return void|int Optionally returns a stack pointer. The sniff will not be
     *                  called again on the current file until the returned stack
     *                  pointer is reached. Return ($phpcsFile->numTokens + 1) to skip
     *                  the rest of the file.
     */
    abstract protected function process_member_var(File $phpcs_file, $stack_ptr);
    /**
     * Called to process normal member vars.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The PHP_CodeSniffer file where this
     *                                               token was found.
     * @param int                         $stackPtr  The position where the token was found.
     *
     * @return void|int Optionally returns a stack pointer. The sniff will not be
     *                  called again on the current file until the returned stack
     *                  pointer is reached. Return ($phpcsFile->numTokens + 1) to skip
     *                  the rest of the file.
     */
    abstract protected function process_variable(File $phpcs_file, $stack_ptr);
    /**
     * Called to process variables found in double quoted strings or heredocs.
     *
     * Note that there may be more than one variable in the string, which will
     * result only in one call for the string or one call per line for heredocs.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The PHP_CodeSniffer file where this
     *                                               token was found.
     * @param int                         $stackPtr  The position where the double quoted
     *                                               string was found.
     *
     * @return void|int Optionally returns a stack pointer. The sniff will not be
     *                  called again on the current file until the returned stack
     *                  pointer is reached. Return ($phpcsFile->numTokens + 1) to skip
     *                  the rest of the file.
     */
    abstract protected function process_variable_in_string(File $phpcs_file, $stack_ptr);
}
//end class