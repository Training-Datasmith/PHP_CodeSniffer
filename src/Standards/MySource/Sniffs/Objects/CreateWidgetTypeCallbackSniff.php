<?php

declare (strict_types=1);
/**
 * Ensures the create() method of widget types properly uses callbacks.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\My_Source\Sniffs\Objects;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Create_Widget_Type_Callback_Sniff implements Sniff
{
    /**
     * A list of tokenizers this sniff supports.
     *
     * @var array
     */
    public $supported_tokenizers = ['JS'];
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_OBJECT];
    }
    //end register()
    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token
     *                                               in the stack passed in $tokens.
     *
     * @return void
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        $class_name = $phpcs_file->find_previous(T_STRING, $stack_ptr - 1);
        if (substr(strtolower($tokens[$class_name]['content']), -10) !== 'widgettype') {
            return;
        }
        // Search for a create method.
        $create = $phpcs_file->find_next(T_PROPERTY, $stack_ptr, $tokens[$stack_ptr]['bracket_closer'], null, 'create');
        if ($create === false) {
            return;
        }
        $function = $phpcs_file->find_next([T_WHITESPACE, T_COLON], $create + 1, null, true);
        if ($tokens[$function]['code'] !== T_FUNCTION && $tokens[$function]['code'] !== T_CLOSURE) {
            return;
        }
        $start = $tokens[$function]['scope_opener'] + 1;
        $end = $tokens[$function]['scope_closer'] - 1;
        // Check that the first argument is called "callback".
        $arg = $phpcs_file->find_next(T_WHITESPACE, $tokens[$function]['parenthesis_opener'] + 1, null, true);
        if ($tokens[$arg]['content'] !== 'callback') {
            $error = 'The first argument of the create() method of a widget type must be called "callback"';
            $phpcs_file->add_error($error, $arg, 'FirstArgNotCallback');
        }
        /*
            Look for return statements within the function. They cannot return
            anything and must be preceded by the callback.call() line. The
            callback itself must contain "self" or "this" as the first argument
            and there needs to be a call to the callback function somewhere
            in the create method. All calls to the callback function must be
            followed by a return statement or the end of the method.
        */
        $found_callback = false;
        $passed_callback = false;
        $nested_function = null;
        for ($i = $start; $i <= $end; $i++) {
            // Keep track of nested functions.
            if ($nested_function !== null) {
                if ($i === $nested_function) {
                    $nested_function = null;
                    continue;
                }
            } elseif (($tokens[$i]['code'] === T_FUNCTION || $tokens[$i]['code'] === T_CLOSURE) && isset($tokens[$i]['scope_closer']) === true) {
                $nested_function = $tokens[$i]['scope_closer'];
                continue;
            }
            if ($nested_function === null && $tokens[$i]['code'] === T_RETURN) {
                // Make sure return statements are not returning anything.
                if ($tokens[$i + 1]['code'] !== T_SEMICOLON) {
                    $error = 'The create() method of a widget type must not return a value';
                    $phpcs_file->add_error($error, $i, 'ReturnValue');
                }
                continue;
            }
            if ($tokens[$i]['code'] !== T_STRING) {
                continue;
            }
            if ($tokens[$i]['content'] !== 'callback') {
                continue;
            }
            // If this is the form "callback.call(" then it is a call
            // to the callback function.
            if ($tokens[$i + 1]['code'] !== T_OBJECT_OPERATOR || $tokens[$i + 2]['content'] !== 'call' || $tokens[$i + 3]['code'] !== T_OPEN_PARENTHESIS) {
                // One last chance; this might be the callback function
                // being passed to another function, like this
                // "this.init(something, callback, something)".
                if (isset($tokens[$i]['nested_parenthesis']) === false) {
                    continue;
                }
                // Just make sure those brackets don't belong to anyone,
                // like an IF or FOR statement.
                foreach ($tokens[$i]['nested_parenthesis'] as $bracket) {
                    if (isset($tokens[$bracket]['parenthesis_owner']) === true) {
                        continue 2;
                    }
                }
                // Note that we use this endBracket down further when checking
                // for a RETURN statement.
                $nested_parens = $tokens[$i]['nested_parenthesis'];
                $end_bracket = end($nested_parens);
                $bracket = key($nested_parens);
                $prev = $phpcs_file->find_previous(Tokens::$empty_tokens, $bracket - 1, null, true);
                if ($tokens[$prev]['code'] !== T_STRING) {
                    // This is not a function passing the callback.
                    continue;
                }
                $passed_callback = true;
            }
            //end if
            $found_callback = true;
            // Now it must be followed by a return statement or the end of the function.
            if ($passed_callback === false) {
                // The first argument must be "this" or "self".
                $arg = $phpcs_file->find_next(T_WHITESPACE, $i + 4, null, true);
                if ($tokens[$arg]['content'] !== 'this' && $tokens[$arg]['content'] !== 'self') {
                    $error = 'The first argument passed to the callback function must be "this" or "self"';
                    $phpcs_file->add_error($error, $arg, 'FirstArgNotSelf');
                }
                $end_bracket = $tokens[$i + 3]['parenthesis_closer'];
            }
            for ($next = $end_bracket; $next <= $end; $next++) {
                // Skip whitespace so we find the next content after the call.
                if (isset(Tokens::$empty_tokens[$tokens[$next]['code']]) === true) {
                    continue;
                }
                // Skip closing braces like END IF because it is not executable code.
                if ($tokens[$next]['code'] === T_CLOSE_CURLY_BRACKET) {
                    continue;
                }
                // We don't care about anything on the current line, like a
                // semicolon. It doesn't matter if there are other statements on the
                // line because another sniff will check for those.
                if ($tokens[$next]['line'] === $tokens[$end_bracket]['line']) {
                    continue;
                }
                break;
            }
            if ($next !== $tokens[$function]['scope_closer'] && $tokens[$next]['code'] !== T_RETURN) {
                $error = 'The call to the callback function must be followed by a return statement if it is not the last statement in the create() method';
                $phpcs_file->add_error($error, $i, 'NoReturn');
            }
        }
        //end for
        if ($found_callback === false) {
            $error = 'The create() method of a widget type must call the callback function';
            $phpcs_file->add_error($error, $create, 'CallbackNotCalled');
        }
    }
    //end process()
}
//end class