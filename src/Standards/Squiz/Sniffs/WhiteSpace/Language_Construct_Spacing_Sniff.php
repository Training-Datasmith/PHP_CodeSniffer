<?php

declare (strict_types=1);
/**
 * Ensures all language constructs contain a single space between themselves and their content.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\White_Space;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util;
class Language_Construct_Spacing_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_ECHO, T_PRINT, T_RETURN, T_INCLUDE, T_INCLUDE_ONCE, T_REQUIRE, T_REQUIRE_ONCE, T_NEW];
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
        if (isset($tokens[$stack_ptr + 1]) === false) {
            // Skip if there is no next token.
            return;
        }
        if ($tokens[$stack_ptr + 1]['code'] === T_SEMICOLON) {
            // No content for this language construct.
            return;
        }
        if ($tokens[$stack_ptr + 1]['code'] === T_WHITESPACE) {
            $content = $tokens[$stack_ptr + 1]['content'];
            if ($content !== ' ') {
                $error = 'Language constructs must be followed by a single space; expected 1 space but found "%s"';
                $data = [Util\Common::prepare_for_output($content)];
                $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'IncorrectSingle', $data);
                if ($fix === true) {
                    $phpcs_file->fixer->replace_token($stack_ptr + 1, ' ');
                }
            }
        } elseif ($tokens[$stack_ptr + 1]['code'] !== T_OPEN_PARENTHESIS) {
            $error = 'Language constructs must be followed by a single space; expected "%s" but found "%s"';
            $data = [$tokens[$stack_ptr]['content'] . ' ' . $tokens[$stack_ptr + 1]['content'], $tokens[$stack_ptr]['content'] . $tokens[$stack_ptr + 1]['content']];
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'Incorrect', $data);
            if ($fix === true) {
                $phpcs_file->fixer->add_content($stack_ptr, ' ');
            }
        }
        //end if
    }
    //end process()
}
//end class