<?php

declare (strict_types=1);
/**
 * Ensures logical operators 'and' and 'or' are not used.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\Operators;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Valid_Logical_Operators_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_LOGICAL_AND, T_LOGICAL_OR];
    }
    //end register()
    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The current file being scanned.
     * @param int                         $stackPtr  The position of the current token in the
     *                                               stack passed in $tokens.
     *
     * @return void
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        $replacements = ['and' => '&&', 'or' => '||'];
        $operator = strtolower($tokens[$stack_ptr]['content']);
        if (isset($replacements[$operator]) === false) {
            return;
        }
        $error = 'Logical operator "%s" is prohibited; use "%s" instead';
        $data = [$operator, $replacements[$operator]];
        $phpcs_file->add_error($error, $stack_ptr, 'NotAllowed', $data);
    }
    //end process()
}
//end class