<?php

declare (strict_types=1);
/**
 * Ensures class and interface names start with a capital letter and use _ separators.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\PEAR\Sniffs\Naming_Conventions;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Valid_Class_Name_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM];
    }
    //end register()
    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The current file being processed.
     * @param int                         $stackPtr  The position of the current token
     *                                               in the stack passed in $tokens.
     *
     * @return void
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        $class_name = $phpcs_file->find_next(T_STRING, $stack_ptr);
        $name = trim($tokens[$class_name]['content']);
        $error_data = [ucfirst($tokens[$stack_ptr]['content'])];
        // Make sure the first letter is a capital.
        if (preg_match('|^[A-Z]|', $name) === 0) {
            $error = '%s name must begin with a capital letter';
            $phpcs_file->add_error($error, $stack_ptr, 'StartWithCapital', $error_data);
        }
        // Check that each new word starts with a capital as well, but don't
        // check the first word, as it is checked above.
        $valid_name = true;
        $name_bits = explode('_', $name);
        $first_bit = array_shift($name_bits);
        foreach ($name_bits as $bit) {
            if ($bit === '' || $bit[0] !== strtoupper($bit[0])) {
                $valid_name = false;
                break;
            }
        }
        if ($valid_name === false) {
            // Strip underscores because they cause the suggested name
            // to be incorrect.
            $name_bits = explode('_', trim($name, '_'));
            $first_bit = array_shift($name_bits);
            if ($first_bit === '') {
                $error = '%s name is not valid';
                $phpcs_file->add_error($error, $stack_ptr, 'Invalid', $error_data);
            } else {
                $new_name = strtoupper($first_bit[0]) . substr($first_bit, 1) . '_';
                foreach ($name_bits as $bit) {
                    if ($bit !== '') {
                        $new_name .= strtoupper($bit[0]) . substr($bit, 1) . '_';
                    }
                }
                $new_name = rtrim($new_name, '_');
                $error = '%s name is not valid; consider %s instead';
                $data = $error_data;
                $data[] = $new_name;
                $phpcs_file->add_error($error, $stack_ptr, 'Invalid', $data);
            }
        }
        //end if
    }
    //end process()
}
//end class