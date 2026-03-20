<?php

declare (strict_types=1);
/**
 * Detects for-loops that can be simplified to a while-loop.
 *
 * This rule is based on the PMD rule catalogue. Detects for-loops that can be
 * simplified as a while-loop.
 *
 * <code>
 * class Foo
 * {
 *     public function bar($x)
 *     {
 *         for (;true;) true; // No Init or Update part, may as well be: while (true)
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
class For_Loop_Should_Be_While_Loop_Sniff implements Sniff
{
    /**
     * Registers the tokens that this sniff wants to listen for.
     *
     * @return int[]
     */
    public function register()
    {
        return [T_FOR];
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
        // Skip invalid statement.
        if (isset($token['parenthesis_opener']) === false) {
            return;
        }
        $next = ++$token['parenthesis_opener'];
        $end = --$token['parenthesis_closer'];
        $parts = [0, 0, 0];
        $index = 0;
        for (; $next <= $end; ++$next) {
            $code = $tokens[$next]['code'];
            if ($code === T_SEMICOLON) {
                ++$index;
            } elseif (isset(Tokens::$empty_tokens[$code]) === false) {
                ++$parts[$index];
            }
        }
        if ($parts[0] === 0 && $parts[2] === 0 && $parts[1] > 0) {
            $error = 'This FOR loop can be simplified to a WHILE loop';
            $phpcs_file->add_warning($error, $stack_ptr, 'CanSimplify');
        }
    }
    //end process()
}
//end class