<?php

declare (strict_types=1);
/**
 * Discourages the use of alias functions.
 *
 * Alias functions are kept in PHP for compatibility
 * with older versions. Can be used to forbid the use of any function.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Generic\Sniffs\PHP;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Forbidden_Functions_Sniff implements Sniff
{
    /**
     * A list of forbidden functions with their alternatives.
     *
     * The value is NULL if no alternative exists. IE, the
     * function should just not be used.
     *
     * @var array<string, string|null>
     */
    public $forbidden_functions = ['sizeof' => 'count', 'delete' => 'unset'];
    /**
     * A cache of forbidden function names, for faster lookups.
     *
     * @var string[]
     */
    protected $forbidden_function_names = [];
    /**
     * If true, forbidden functions will be considered regular expressions.
     *
     * @var boolean
     */
    protected $pattern_match = false;
    /**
     * If true, an error will be thrown; otherwise a warning.
     *
     * @var boolean
     */
    public $error = true;
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        // Everyone has had a chance to figure out what forbidden functions
        // they want to check for, so now we can cache out the list.
        $this->forbidden_function_names = array_keys($this->forbidden_functions);
        if ($this->pattern_match === true) {
            foreach ($this->forbidden_function_names as $i => $name) {
                $this->forbidden_function_names[$i] = '/' . $name . '/i';
            }
            return [T_STRING];
        }
        // If we are not pattern matching, we need to work out what
        // tokens to listen for.
        $has_halt_compiler = false;
        $string = '<?php ';
        foreach ($this->forbidden_function_names as $name) {
            if ($name === '__halt_compiler') {
                $has_halt_compiler = true;
            } else {
                $string .= $name . '();';
            }
        }
        if ($has_halt_compiler === true) {
            $string .= '__halt_compiler();';
        }
        $register = [];
        $tokens = token_get_all($string);
        array_shift($tokens);
        foreach ($tokens as $token) {
            if (is_array($token) === true) {
                $register[] = $token[0];
            }
        }
        $this->forbidden_function_names = array_map('strtolower', $this->forbidden_function_names);
        $this->forbidden_functions = array_combine($this->forbidden_function_names, $this->forbidden_functions);
        return array_unique($register);
    }
    //end register()
    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token in
     *                                               the stack passed in $tokens.
     *
     * @return void
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        $ignore = [T_DOUBLE_COLON => true, T_OBJECT_OPERATOR => true, T_NULLSAFE_OBJECT_OPERATOR => true, T_FUNCTION => true, T_CONST => true, T_PUBLIC => true, T_PRIVATE => true, T_PROTECTED => true, T_AS => true, T_NEW => true, T_INSTEADOF => true, T_NS_SEPARATOR => true, T_IMPLEMENTS => true];
        $prev_token = $phpcs_file->find_previous(T_WHITESPACE, $stack_ptr - 1, null, true);
        // If function call is directly preceded by a NS_SEPARATOR it points to the
        // global namespace, so we should still catch it.
        if ($tokens[$prev_token]['code'] === T_NS_SEPARATOR) {
            $prev_token = $phpcs_file->find_previous(T_WHITESPACE, $prev_token - 1, null, true);
            if ($tokens[$prev_token]['code'] === T_STRING) {
                // Not in the global namespace.
                return;
            }
        }
        if (isset($ignore[$tokens[$prev_token]['code']]) === true) {
            // Not a call to a PHP function.
            return;
        }
        $next_token = $phpcs_file->find_next(T_WHITESPACE, $stack_ptr + 1, null, true);
        if (isset($ignore[$tokens[$next_token]['code']]) === true) {
            // Not a call to a PHP function.
            return;
        }
        if ($tokens[$stack_ptr]['code'] === T_STRING && $tokens[$next_token]['code'] !== T_OPEN_PARENTHESIS) {
            // Not a call to a PHP function.
            return;
        }
        if (empty($tokens[$stack_ptr]['nested_attributes']) === false) {
            // Class instantiation in attribute, not function call.
            return;
        }
        $function = strtolower($tokens[$stack_ptr]['content']);
        $pattern = null;
        if ($this->pattern_match === true) {
            $count = 0;
            $pattern = preg_replace($this->forbidden_function_names, $this->forbidden_function_names, $function, 1, $count);
            if ($count === 0) {
                return;
            }
            // Remove the pattern delimiters and modifier.
            $pattern = substr($pattern, 1, -2);
        } else if (in_array($function, $this->forbidden_function_names, true) === false) {
            return;
        }
        //end if
        $this->add_error($phpcs_file, $stack_ptr, $tokens[$stack_ptr]['content'], $pattern);
    }
    //end process()
    /**
     * Generates the error or warning for this sniff.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the forbidden function
     *                                               in the token array.
     * @param string                      $function  The name of the forbidden function.
     * @param string                      $pattern   The pattern used for the match.
     *
     * @return void
     */
    protected function add_error($phpcs_file, $stack_ptr, $function, $pattern = null)
    {
        $data = [$function];
        $error = 'The use of function %s() is ';
        if ($this->error === true) {
            $type = 'Found';
            $error .= 'forbidden';
        } else {
            $type = 'Discouraged';
            $error .= 'discouraged';
        }
        if ($pattern === null) {
            $pattern = strtolower($function);
        }
        if ($this->forbidden_functions[$pattern] !== null && $this->forbidden_functions[$pattern] !== 'null') {
            $type .= 'WithAlternative';
            $data[] = $this->forbidden_functions[$pattern];
            $error .= '; use %s() instead';
        }
        if ($this->error === true) {
            $phpcs_file->add_error($error, $stack_ptr, $type, $data);
        } else {
            $phpcs_file->add_warning($error, $stack_ptr, $type, $data);
        }
    }
    //end addError()
}
//end class