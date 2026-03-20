<?php

declare (strict_types=1);
/**
 * Ensures that self and static are not used to call public methods in action classes.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\My_Source\Sniffs\Channels;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Disallow_Self_Actions_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_CLASS];
    }
    //end register()
    /**
     * Processes this sniff, when one of its tokens is encountered.
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
        // We are not interested in abstract classes.
        $prev = $phpcs_file->find_previous(T_WHITESPACE, $stack_ptr - 1, null, true);
        if ($prev !== false && $tokens[$prev]['code'] === T_ABSTRACT) {
            return;
        }
        // We are only interested in Action classes.
        $class_name_token = $phpcs_file->find_next(T_WHITESPACE, $stack_ptr + 1, null, true);
        $class_name = $tokens[$class_name_token]['content'];
        if (substr($class_name, -7) !== 'Actions') {
            return;
        }
        $found_functions = [];
        $found_calls = [];
        // Find all static method calls in the form self::method() in the class.
        $class_end = $tokens[$stack_ptr]['scope_closer'];
        for ($i = $class_name_token + 1; $i < $class_end; $i++) {
            if ($tokens[$i]['code'] !== T_DOUBLE_COLON) {
                if ($tokens[$i]['code'] === T_FUNCTION) {
                    // Cache the function information.
                    $func_name = $phpcs_file->find_next(T_STRING, $i + 1);
                    $func_scope = $phpcs_file->find_previous(Tokens::$scope_modifiers, $i - 1);
                    $found_functions[$tokens[$func_name]['content']] = strtolower($tokens[$func_scope]['content']);
                }
                continue;
            }
            $prev_token = $phpcs_file->find_previous(T_WHITESPACE, $i - 1, null, true);
            if ($tokens[$prev_token]['content'] !== 'self' && $tokens[$prev_token]['content'] !== 'static') {
                continue;
            }
            $func_name_token = $phpcs_file->find_next(T_WHITESPACE, $i + 1, null, true);
            if ($tokens[$func_name_token]['code'] === T_VARIABLE) {
                // We are only interested in function calls.
                continue;
            }
            $func_name = $tokens[$func_name_token]['content'];
            // We've found the function, now we need to find it and see if it is
            // public, private or protected. If it starts with an underscore we
            // can assume it is private.
            if ($func_name[0] === '_') {
                continue;
            }
            $found_calls[$i] = ['name' => $func_name, 'type' => strtolower($tokens[$prev_token]['content'])];
        }
        //end for
        $error_class_name = substr($class_name, 0, -7);
        foreach ($found_calls as $token => $func_data) {
            if (isset($found_functions[$func_data['name']]) === false) {
                // Function was not in this class, might have come from the parent.
                // Either way, we can't really check this.
                continue;
            }
            if ($found_functions[$func_data['name']] === 'public') {
                $type = $func_data['type'];
                $error = "Static calls to public methods in Action classes must not use the {$type} keyword; use %s::%s() instead";
                $data = [$error_class_name, $func_name];
                $phpcs_file->add_error($error, $token, 'Found' . ucfirst($func_data['type']), $data);
            }
        }
    }
    //end process()
}
//end class