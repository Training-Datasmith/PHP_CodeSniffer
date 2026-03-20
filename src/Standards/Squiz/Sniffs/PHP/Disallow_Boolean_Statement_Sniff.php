<?php

declare (strict_types=1);
/**
 * Ensures that boolean operators are only used inside control structure conditions.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\PHP;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Disallow_Boolean_Statement_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return Tokens::$boolean_operators;
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
        if (isset($tokens[$stack_ptr]['nested_parenthesis']) === true) {
            foreach ($tokens[$stack_ptr]['nested_parenthesis'] as $open => $close) {
                if (isset($tokens[$open]['parenthesis_owner']) === true) {
                    // Any owner means we are not just a simple statement.
                    return;
                }
            }
        }
        $error = 'Boolean operators are not allowed outside of control structure conditions';
        $phpcs_file->add_error($error, $stack_ptr, 'Found');
    }
    //end process()
}
//end class