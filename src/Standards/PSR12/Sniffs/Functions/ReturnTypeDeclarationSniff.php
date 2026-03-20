<?php

declare (strict_types=1);
/**
 * Ensure return types are defined correctly for functions and closures.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2019 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\PSR12\Sniffs\Functions;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Return_Type_Declaration_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_FUNCTION, T_CLOSURE, T_FN];
    }
    //end register()
    /**
     * Processes this test when one of its tokens is encountered.
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
        if (isset($tokens[$stack_ptr]['parenthesis_opener']) === false || isset($tokens[$stack_ptr]['parenthesis_closer']) === false || $tokens[$stack_ptr]['parenthesis_opener'] === null || $tokens[$stack_ptr]['parenthesis_closer'] === null) {
            return;
        }
        $method_properties = $phpcs_file->get_method_properties($stack_ptr);
        if ($method_properties['return_type'] === '') {
            return;
        }
        $return_type = $method_properties['return_type_token'];
        if ($method_properties['nullable_return_type'] === true) {
            $return_type = $phpcs_file->find_previous(T_NULLABLE, $return_type - 1);
        }
        if ($tokens[$return_type - 1]['code'] !== T_WHITESPACE || $tokens[$return_type - 1]['content'] !== ' ' || $tokens[$return_type - 2]['code'] !== T_COLON) {
            $error = 'There must be a single space between the colon and type in a return type declaration';
            if ($tokens[$return_type - 1]['code'] === T_WHITESPACE && $tokens[$return_type - 2]['code'] === T_COLON) {
                $fix = $phpcs_file->add_fixable_error($error, $return_type, 'SpaceBeforeReturnType');
                if ($fix === true) {
                    $phpcs_file->fixer->replace_token($return_type - 1, ' ');
                }
            } elseif ($tokens[$return_type - 1]['code'] === T_COLON) {
                $fix = $phpcs_file->add_fixable_error($error, $return_type, 'SpaceBeforeReturnType');
                if ($fix === true) {
                    $phpcs_file->fixer->add_content_before($return_type, ' ');
                }
            } else {
                $phpcs_file->add_error($error, $return_type, 'SpaceBeforeReturnType');
            }
        }
        $colon = $phpcs_file->find_previous(T_COLON, $return_type);
        if ($tokens[$colon - 1]['code'] !== T_CLOSE_PARENTHESIS) {
            $error = 'There must not be a space before the colon in a return type declaration';
            $prev = $phpcs_file->find_previous(T_WHITESPACE, $colon - 1, null, true);
            if ($tokens[$prev]['code'] === T_CLOSE_PARENTHESIS) {
                $fix = $phpcs_file->add_fixable_error($error, $colon, 'SpaceBeforeColon');
                if ($fix === true) {
                    $phpcs_file->fixer->begin_changeset();
                    for ($x = $prev + 1; $x < $colon; $x++) {
                        $phpcs_file->fixer->replace_token($x, '');
                    }
                    $phpcs_file->fixer->end_changeset();
                }
            } else {
                $phpcs_file->add_error($error, $colon, 'SpaceBeforeColon');
            }
        }
    }
    //end process()
}
//end class