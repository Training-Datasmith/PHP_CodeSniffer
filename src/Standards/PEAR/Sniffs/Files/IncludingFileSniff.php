<?php

declare (strict_types=1);
/**
 * Ensure include_once is used in conditional situations and require_once is used elsewhere.
 *
 * Also checks that brackets do not surround the file being included.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\PEAR\Sniffs\Files;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Including_File_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_INCLUDE_ONCE, T_REQUIRE_ONCE, T_REQUIRE, T_INCLUDE];
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
        $next_token = $phpcs_file->find_next(Tokens::$empty_tokens, $stack_ptr + 1, null, true);
        if ($tokens[$next_token]['code'] === T_OPEN_PARENTHESIS) {
            $error = '"%s" is a statement not a function; no parentheses are required';
            $data = [$tokens[$stack_ptr]['content']];
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'BracketsNotRequired', $data);
            if ($fix === true) {
                $phpcs_file->fixer->begin_changeset();
                $phpcs_file->fixer->replace_token($tokens[$next_token]['parenthesis_closer'], '');
                if ($tokens[$next_token - 1]['code'] !== T_WHITESPACE) {
                    $phpcs_file->fixer->replace_token($next_token, ' ');
                } else {
                    $phpcs_file->fixer->replace_token($next_token, '');
                }
                $phpcs_file->fixer->end_changeset();
            }
        }
        if (count($tokens[$stack_ptr]['conditions']) !== 0) {
            $in_condition = true;
        } else {
            $in_condition = false;
        }
        // Check to see if this including statement is within the parenthesis
        // of a condition. If that's the case then we need to process it as being
        // within a condition, as they are checking the return value.
        if (isset($tokens[$stack_ptr]['nested_parenthesis']) === true) {
            foreach ($tokens[$stack_ptr]['nested_parenthesis'] as $left => $right) {
                if (isset($tokens[$left]['parenthesis_owner']) === true) {
                    $in_condition = true;
                }
            }
        }
        // Check to see if they are assigning the return value of this
        // including call. If they are then they are probably checking it, so
        // it's conditional.
        $previous = $phpcs_file->find_previous(Tokens::$empty_tokens, $stack_ptr - 1, null, true);
        if (isset(Tokens::$assignment_tokens[$tokens[$previous]['code']]) === true) {
            // The have assigned the return value to it, so its conditional.
            $in_condition = true;
        }
        $token_code = $tokens[$stack_ptr]['code'];
        if ($in_condition === true) {
            // We are inside a conditional statement. We need an include_once.
            if ($token_code === T_REQUIRE_ONCE) {
                $error = 'File is being conditionally included; ';
                $error .= 'use "include_once" instead';
                $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'UseIncludeOnce');
                if ($fix === true) {
                    $phpcs_file->fixer->replace_token($stack_ptr, 'include_once');
                }
            } elseif ($token_code === T_REQUIRE) {
                $error = 'File is being conditionally included; ';
                $error .= 'use "include" instead';
                $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'UseInclude');
                if ($fix === true) {
                    $phpcs_file->fixer->replace_token($stack_ptr, 'include');
                }
            }
        } else if ($token_code === T_INCLUDE_ONCE) {
            $error = 'File is being unconditionally included; ';
            $error .= 'use "require_once" instead';
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'UseRequireOnce');
            if ($fix === true) {
                $phpcs_file->fixer->replace_token($stack_ptr, 'require_once');
            }
        } elseif ($token_code === T_INCLUDE) {
            $error = 'File is being unconditionally included; ';
            $error .= 'use "require" instead';
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'UseRequire');
            if ($fix === true) {
                $phpcs_file->fixer->replace_token($stack_ptr, 'require');
            }
        }
        //end if
    }
    //end process()
}
//end class