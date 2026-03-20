<?php

declare (strict_types=1);
/**
 * Ensures that systems and asset types are used if they are included.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\My_Source\Sniffs\Channels;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Unused_System_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_DOUBLE_COLON];
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
        // Check if this is a call to includeSystem, includeAsset or includeWidget.
        $method_name = strtolower($tokens[$stack_ptr + 1]['content']);
        if ($method_name === 'includesystem' || $method_name === 'includeasset' || $method_name === 'includewidget') {
            $system_name = $phpcs_file->find_next(T_WHITESPACE, $stack_ptr + 3, null, true);
            if ($system_name === false || $tokens[$system_name]['code'] !== T_CONSTANT_ENCAPSED_STRING) {
                // Must be using a variable instead of a specific system name.
                // We can't accurately check that.
                return;
            }
            $system_name = trim($tokens[$system_name]['content'], " '");
        } else {
            return;
        }
        if ($method_name === 'includeasset') {
            $system_name .= 'assettype';
        } elseif ($method_name === 'includewidget') {
            $system_name .= 'widgettype';
        }
        $system_name = strtolower($system_name);
        // Now check if this system is used anywhere in this scope.
        $level = $tokens[$stack_ptr]['level'];
        for ($i = $stack_ptr + 1; $i < $phpcs_file->num_tokens; $i++) {
            if ($tokens[$i]['level'] < $level) {
                // We have gone out of scope.
                // If the original include was inside an IF statement that
                // is checking if the system exists, check the outer scope
                // as well.
                if ($tokens[$stack_ptr]['level'] === $level) {
                    // We are still in the base level, so this is the first
                    // time we have got here.
                    $conditions = array_keys($tokens[$stack_ptr]['conditions']);
                    if (empty($conditions) === false) {
                        $cond = array_pop($conditions);
                        if ($tokens[$cond]['code'] === T_IF) {
                            $i = $tokens[$cond]['scope_closer'];
                            $level--;
                            continue;
                        }
                    }
                }
                break;
            }
            //end if
            if ($tokens[$i]['code'] !== T_DOUBLE_COLON && $tokens[$i]['code'] !== T_EXTENDS && $tokens[$i]['code'] !== T_IMPLEMENTS) {
                continue;
            }
            switch ($tokens[$i]['code']) {
                case T_DOUBLE_COLON:
                    $used_name = strtolower($tokens[$i - 1]['content']);
                    if ($used_name === $system_name) {
                        // The included system was used, so it is fine.
                        return;
                    }
                    break;
                case T_EXTENDS:
                    $class_name_token = $phpcs_file->find_next(T_STRING, $i + 1);
                    $class_name = strtolower($tokens[$class_name_token]['content']);
                    if ($class_name === $system_name) {
                        // The included system was used, so it is fine.
                        return;
                    }
                    break;
                case T_IMPLEMENTS:
                    $end_implements = $phpcs_file->find_next([T_EXTENDS, T_OPEN_CURLY_BRACKET], $i + 1);
                    for ($x = $i + 1; $x < $end_implements; $x++) {
                        if ($tokens[$x]['code'] === T_STRING) {
                            $class_name = strtolower($tokens[$x]['content']);
                            if ($class_name === $system_name) {
                                // The included system was used, so it is fine.
                                return;
                            }
                        }
                    }
                    break;
            }
            //end switch
        }
        //end for
        // If we get to here, the system was not use.
        $error = 'Included system "%s" is never used';
        $data = [$system_name];
        $phpcs_file->add_error($error, $stack_ptr, 'Found', $data);
    }
    //end process()
}
//end class