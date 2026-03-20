<?php

declare (strict_types=1);
/**
 * Ensures there is only one assignment on a line, and that it is the first thing on the line.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\PHP;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Disallow_Multiple_Assignments_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_EQUAL];
    }
    //end register()
    /**
     * Processes this test, when one of its tokens is encountered.
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
        // Ignore default value assignments in function definitions.
        $function = $phpcs_file->find_previous([T_FUNCTION, T_CLOSURE, T_FN], $stack_ptr - 1, null, false, null, true);
        if ($function !== false) {
            $opener = $tokens[$function]['parenthesis_opener'];
            $closer = $tokens[$function]['parenthesis_closer'];
            if ($opener < $stack_ptr && $closer > $stack_ptr) {
                return;
            }
        }
        // Ignore assignments in WHILE loop conditions.
        if (isset($tokens[$stack_ptr]['nested_parenthesis']) === true) {
            $nested = $tokens[$stack_ptr]['nested_parenthesis'];
            foreach ($nested as $opener => $closer) {
                if (isset($tokens[$opener]['parenthesis_owner']) === true && $tokens[$tokens[$opener]['parenthesis_owner']]['code'] === T_WHILE) {
                    return;
                }
            }
        }
        // Ignore member var definitions.
        if (empty($tokens[$stack_ptr]['conditions']) === false) {
            $conditions = $tokens[$stack_ptr]['conditions'];
            end($conditions);
            $deepest_scope = key($conditions);
            if (isset(Tokens::$oo_scope_tokens[$tokens[$deepest_scope]['code']]) === true) {
                return;
            }
        }
        /*
            The general rule is:
            Find an equal sign and go backwards along the line. If you hit an
            end bracket, skip to the opening bracket. When you find a variable,
            stop. That variable must be the first non-empty token on the line
            or in the statement. If not, throw an error.
        */
        for ($var_token = $stack_ptr - 1; $var_token >= 0; $var_token--) {
            if (in_array($tokens[$var_token]['code'], [T_SEMICOLON, T_OPEN_CURLY_BRACKET], true) === true) {
                // We've reached the next statement, so we
                // didn't find a variable.
                return;
            }
            // Skip brackets.
            if (isset($tokens[$var_token]['parenthesis_opener']) === true && $tokens[$var_token]['parenthesis_opener'] < $var_token) {
                $var_token = $tokens[$var_token]['parenthesis_opener'];
                continue;
            }
            if (isset($tokens[$var_token]['bracket_opener']) === true) {
                $var_token = $tokens[$var_token]['bracket_opener'];
                continue;
            }
            if ($tokens[$var_token]['code'] === T_VARIABLE) {
                // We found our variable.
                break;
            }
        }
        //end for
        if ($var_token <= 0) {
            // Didn't find a variable.
            return;
        }
        $start = $phpcs_file->find_start_of_statement($var_token);
        $allowed = Tokens::$empty_tokens;
        $allowed[T_STRING] = T_STRING;
        $allowed[T_NS_SEPARATOR] = T_NS_SEPARATOR;
        $allowed[T_DOUBLE_COLON] = T_DOUBLE_COLON;
        $allowed[T_OBJECT_OPERATOR] = T_OBJECT_OPERATOR;
        $allowed[T_ASPERAND] = T_ASPERAND;
        $allowed[T_DOLLAR] = T_DOLLAR;
        $allowed[T_SELF] = T_SELF;
        $allowed[T_PARENT] = T_PARENT;
        $allowed[T_STATIC] = T_STATIC;
        $var_token = $phpcs_file->find_previous($allowed, $var_token - 1, null, true);
        if ($var_token < $start && $tokens[$var_token]['code'] !== T_OPEN_PARENTHESIS && $tokens[$var_token]['code'] !== T_OPEN_SQUARE_BRACKET) {
            $var_token = $start;
        }
        // Ignore the first part of FOR loops as we are allowed to
        // assign variables there even though the variable is not the
        // first thing on the line.
        if ($tokens[$var_token]['code'] === T_OPEN_PARENTHESIS && isset($tokens[$var_token]['parenthesis_owner']) === true) {
            $owner = $tokens[$var_token]['parenthesis_owner'];
            if ($tokens[$owner]['code'] === T_FOR) {
                return;
            }
        }
        if ($tokens[$var_token]['code'] === T_VARIABLE || $tokens[$var_token]['code'] === T_OPEN_TAG || $tokens[$var_token]['code'] === T_GOTO_LABEL || $tokens[$var_token]['code'] === T_INLINE_THEN || $tokens[$var_token]['code'] === T_INLINE_ELSE || $tokens[$var_token]['code'] === T_SEMICOLON || $tokens[$var_token]['code'] === T_CLOSE_PARENTHESIS || isset($allowed[$tokens[$var_token]['code']]) === true) {
            return;
        }
        $error = 'Assignments must be the first block of code on a line';
        $error_code = 'Found';
        if (isset($nested) === true) {
            $control_structures = [T_IF => T_IF, T_ELSEIF => T_ELSEIF, T_SWITCH => T_SWITCH, T_CASE => T_CASE, T_FOR => T_FOR, T_MATCH => T_MATCH];
            foreach ($nested as $opener => $closer) {
                if (isset($tokens[$opener]['parenthesis_owner']) === true && isset($control_structures[$tokens[$tokens[$opener]['parenthesis_owner']]['code']]) === true) {
                    $error_code .= 'InControlStructure';
                    break;
                }
            }
        }
        $phpcs_file->add_error($error, $stack_ptr, $error_code);
    }
    //end process()
}
//end class