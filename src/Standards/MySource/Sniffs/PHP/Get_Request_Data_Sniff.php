<?php

declare (strict_types=1);
/**
 * Ensures that getRequestData() is used to access super globals.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\My_Source\Sniffs\PHP;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Get_Request_Data_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_VARIABLE];
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
        $var_name = $tokens[$stack_ptr]['content'];
        if ($var_name !== '$_REQUEST' && $var_name !== '$_GET' && $var_name !== '$_POST' && $var_name !== '$_FILES') {
            return;
        }
        // The only place these super globals can be accessed directly is
        // in the getRequestData() method of the Security class.
        $in_class = false;
        foreach ($tokens[$stack_ptr]['conditions'] as $i => $type) {
            if ($tokens[$i]['code'] === T_CLASS) {
                $class_name = $phpcs_file->find_next(T_STRING, $i);
                $class_name = $tokens[$class_name]['content'];
                if (strtolower($class_name) === 'security') {
                    $in_class = true;
                } else {
                    // We don't have nested classes.
                    break;
                }
            } elseif ($in_class === true && $tokens[$i]['code'] === T_FUNCTION) {
                $func_name = $phpcs_file->find_next(T_STRING, $i);
                $func_name = $tokens[$func_name]['content'];
                if (strtolower($func_name) === 'getrequestdata') {
                    // This is valid.
                    return;
                }
                // We don't have nested functions.
                break;
            }
            //end if
        }
        //end foreach
        // If we get to here, the super global was used incorrectly.
        // First find out how it is being used.
        $global_name = strtolower(substr($var_name, 2));
        $used_var = '';
        $open_bracket = $phpcs_file->find_next(T_WHITESPACE, $stack_ptr + 1, null, true);
        if ($tokens[$open_bracket]['code'] === T_OPEN_SQUARE_BRACKET) {
            $close_bracket = $tokens[$open_bracket]['bracket_closer'];
            $used_var = $phpcs_file->get_tokens_as_string($open_bracket + 1, $close_bracket - $open_bracket - 1);
        }
        $type = 'SuperglobalAccessed';
        $error = 'The %s super global must not be accessed directly; use Security::getRequestData(';
        $data = [$var_name];
        if ($used_var !== '') {
            $type .= 'WithVar';
            $error .= '%s, \'%s\'';
            $data[] = $used_var;
            $data[] = $global_name;
        }
        $error .= ') instead';
        $phpcs_file->add_error($error, $stack_ptr, $type, $data);
    }
    //end process()
}
//end class