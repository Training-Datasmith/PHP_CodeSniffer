<?php

declare (strict_types=1);
/**
 * Ensures classes are in camel caps, and the first letter is capitalised.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\Classes;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Common;
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
     * @param int                         $stackPtr  The position of the current token in the
     *                                               stack passed in $tokens.
     *
     * @return void
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        if (isset($tokens[$stack_ptr]['scope_opener']) === false) {
            $error = 'Possible parse error: %s missing opening or closing brace';
            $data = [$tokens[$stack_ptr]['content']];
            $phpcs_file->add_warning($error, $stack_ptr, 'MissingBrace', $data);
            return;
        }
        // Determine the name of the class or interface. Note that we cannot
        // simply look for the first T_STRING because a class name
        // starting with the number will be multiple tokens.
        $opener = $tokens[$stack_ptr]['scope_opener'];
        $name_start = $phpcs_file->find_next(T_WHITESPACE, $stack_ptr + 1, $opener, true);
        $name_end = $phpcs_file->find_next([T_WHITESPACE, T_COLON], $name_start, $opener);
        if ($name_end === false) {
            $name = $tokens[$name_start]['content'];
        } else {
            $name = trim($phpcs_file->get_tokens_as_string($name_start, $name_end - $name_start));
        }
        // Check for PascalCase format.
        $valid = Common::is_camel_caps($name, true, true, false);
        if ($valid === false) {
            $type = ucfirst($tokens[$stack_ptr]['content']);
            $error = '%s name "%s" is not in PascalCase format';
            $data = [$type, $name];
            $phpcs_file->add_error($error, $stack_ptr, 'NotCamelCaps', $data);
            $phpcs_file->record_metric($stack_ptr, 'PascalCase class name', 'no');
        } else {
            $phpcs_file->record_metric($stack_ptr, 'PascalCase class name', 'yes');
        }
    }
    //end process()
}
//end class