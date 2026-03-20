<?php

declare (strict_types=1);
/**
 * Makes sure that any use of double quotes strings are warranted.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\Strings;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Double_Quote_Usage_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_CONSTANT_ENCAPSED_STRING, T_DOUBLE_QUOTED_STRING];
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
        // If tabs are being converted to spaces by the tokeniser, the
        // original content should be used instead of the converted content.
        if (isset($tokens[$stack_ptr]['orig_content']) === true) {
            $working_string = $tokens[$stack_ptr]['orig_content'];
        } else {
            $working_string = $tokens[$stack_ptr]['content'];
        }
        $last_string_token = $stack_ptr;
        $i = $stack_ptr + 1;
        if (isset($tokens[$i]) === true) {
            while ($i < $phpcs_file->num_tokens && $tokens[$i]['code'] === $tokens[$stack_ptr]['code']) {
                if (isset($tokens[$i]['orig_content']) === true) {
                    $working_string .= $tokens[$i]['orig_content'];
                } else {
                    $working_string .= $tokens[$i]['content'];
                }
                $last_string_token = $i;
                $i++;
            }
        }
        $skip_to = $last_string_token + 1;
        // Check if it's a double quoted string.
        if ($working_string[0] !== '"' || substr($working_string, -1) !== '"') {
            return $skip_to;
        }
        // The use of variables in double quoted strings is not allowed.
        if ($tokens[$stack_ptr]['code'] === T_DOUBLE_QUOTED_STRING) {
            $string_tokens = token_get_all('<?php ' . $working_string);
            foreach ($string_tokens as $token) {
                if (is_array($token) === true && $token[0] === T_VARIABLE) {
                    $error = 'Variable "%s" not allowed in double quoted string; use concatenation instead';
                    $data = [$token[1]];
                    $phpcs_file->add_error($error, $stack_ptr, 'ContainsVar', $data);
                }
            }
            return $skip_to;
        }
        //end if
        $allowed_chars = ['\0', '\1', '\2', '\3', '\4', '\5', '\6', '\7', '\n', '\r', '\f', '\t', '\v', '\x', '\b', '\e', '\u', '\''];
        foreach ($allowed_chars as $test_char) {
            if (strpos($working_string, $test_char) !== false) {
                return $skip_to;
            }
        }
        $error = 'String %s does not require double quotes; use single quotes instead';
        $data = [str_replace(["\r", "\n"], ['\r', '\n'], $working_string)];
        $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'NotRequired', $data);
        if ($fix === true) {
            $phpcs_file->fixer->begin_changeset();
            $inner_content = substr($working_string, 1, -1);
            $inner_content = str_replace('\"', '"', $inner_content);
            $inner_content = str_replace('\$', '$', $inner_content);
            $phpcs_file->fixer->replace_token($stack_ptr, "'{$inner_content}'");
            while ($last_string_token !== $stack_ptr) {
                $phpcs_file->fixer->replace_token($last_string_token, '');
                $last_string_token--;
            }
            $phpcs_file->fixer->end_changeset();
        }
        return $skip_to;
    }
    //end process()
}
//end class