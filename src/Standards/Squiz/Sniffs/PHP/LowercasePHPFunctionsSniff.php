<?php

declare (strict_types=1);
/**
 * Ensures all calls to inbuilt PHP functions are lowercase.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\PHP;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Lowercase_Php_Functions_Sniff implements Sniff
{
    /**
     * String -> int hash map of all php built in function names
     *
     * @var array
     */
    private $built_in_functions;
    /**
     * Construct the LowercasePHPFunctionSniff
     */
    public function __construct()
    {
        $all_functions = get_defined_functions();
        $this->built_in_functions = array_flip($all_functions['internal']);
    }
    //end __construct()
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_STRING];
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
        $content = $tokens[$stack_ptr]['content'];
        $content_lc = strtolower($content);
        if ($content === $content_lc) {
            return;
        }
        // Make sure it is an inbuilt PHP function.
        // PHP_CodeSniffer can possibly include user defined functions
        // through the use of vendor/autoload.php.
        if (isset($this->built_in_functions[$content_lc]) === false) {
            return;
        }
        // Make sure this is a function call or a use statement.
        if (empty($tokens[$stack_ptr]['nested_attributes']) === false) {
            // Class instantiation in attribute, not function call.
            return;
        }
        $next = $phpcs_file->find_next(Tokens::$empty_tokens, $stack_ptr + 1, null, true);
        if ($next === false) {
            // Not a function call.
            return;
        }
        $ignore = Tokens::$empty_tokens;
        $ignore[] = T_BITWISE_AND;
        $prev = $phpcs_file->find_previous($ignore, $stack_ptr - 1, null, true);
        $prev_prev = $phpcs_file->find_previous(Tokens::$empty_tokens, $prev - 1, null, true);
        if ($tokens[$next]['code'] !== T_OPEN_PARENTHESIS) {
            // Is this a use statement importing a PHP native function ?
            if ($tokens[$next]['code'] !== T_NS_SEPARATOR && $tokens[$prev]['code'] === T_STRING && $tokens[$prev]['content'] === 'function' && $prev_prev !== false && $tokens[$prev_prev]['code'] === T_USE) {
                $error = 'Use statements for PHP native functions must be lowercase; expected "%s" but found "%s"';
                $data = [$content_lc, $content];
                $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'UseStatementUppercase', $data);
                if ($fix === true) {
                    $phpcs_file->fixer->replace_token($stack_ptr, $content_lc);
                }
            }
            // No open parenthesis; not a "use function" statement nor a function call.
            return;
        }
        //end if
        if ($tokens[$prev]['code'] === T_FUNCTION) {
            // Function declaration, not a function call.
            return;
        }
        if ($tokens[$prev]['code'] === T_NS_SEPARATOR) {
            if ($prev_prev !== false && ($tokens[$prev_prev]['code'] === T_STRING || $tokens[$prev_prev]['code'] === T_NAMESPACE || $tokens[$prev_prev]['code'] === T_NEW)) {
                // Namespaced class/function, not an inbuilt function.
                // Could potentially give false negatives for non-namespaced files
                // when namespace\functionName() is encountered.
                return;
            }
        }
        if ($tokens[$prev]['code'] === T_NEW) {
            // Object creation, not an inbuilt function.
            return;
        }
        if ($tokens[$prev]['code'] === T_OBJECT_OPERATOR || $tokens[$prev]['code'] === T_NULLSAFE_OBJECT_OPERATOR) {
            // Not an inbuilt function.
            return;
        }
        if ($tokens[$prev]['code'] === T_DOUBLE_COLON) {
            // Not an inbuilt function.
            return;
        }
        $error = 'Calls to PHP native functions must be lowercase; expected "%s" but found "%s"';
        $data = [$content_lc, $content];
        $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'CallUppercase', $data);
        if ($fix === true) {
            $phpcs_file->fixer->replace_token($stack_ptr, $content_lc);
        }
    }
    //end process()
}
//end class