<?php

declare (strict_types=1);
/**
 * Detects unconditional if- and elseif-statements.
 *
 * This rule is based on the PMD rule catalogue. The Unconditional If Statement
 * sniff detects statement conditions that are only set to one of the constant
 * values <b>true</b> or <b>false</b>
 *
 * <code>
 * class Foo
 * {
 *     public function close()
 *     {
 *         if (true)
 *         {
 *             // ...
 *         }
 *     }
 * }
 * </code>
 *
 * @author    Manuel Pichler <mapi@manuel-pichler.de>
 * @copyright 2007-2014 Manuel Pichler. All rights reserved.
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Generic\Sniffs\Code_Analysis;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Unconditional_If_Statement_Sniff implements Sniff
{
    /**
     * Registers the tokens that this sniff wants to listen for.
     *
     * @return int[]
     */
    public function register()
    {
        return [T_IF, T_ELSEIF];
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
        $token = $tokens[$stack_ptr];
        // Skip if statement without body.
        if (isset($token['parenthesis_opener']) === false) {
            return;
        }
        $next = ++$token['parenthesis_opener'];
        $end = --$token['parenthesis_closer'];
        $good_condition = false;
        for (; $next <= $end; ++$next) {
            $code = $tokens[$next]['code'];
            if (isset(Tokens::$empty_tokens[$code]) === true) {
                continue;
            }
            if ($code !== T_TRUE && $code !== T_FALSE) {
                $good_condition = true;
            }
        }
        if ($good_condition === false) {
            $error = 'Avoid IF statements that are always true or false';
            $phpcs_file->add_warning($error, $stack_ptr, 'Found');
        }
    }
    //end process()
}
//end class