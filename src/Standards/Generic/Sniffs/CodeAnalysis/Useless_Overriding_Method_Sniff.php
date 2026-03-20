<?php

declare (strict_types=1);
/**
 * Detects unnecessary overridden methods that simply call their parent.
 *
 * This rule is based on the PMD rule catalogue. The Useless Overriding Method
 * sniff detects the use of methods that only call their parent class's method
 * with the same name and arguments. These methods are not required.
 *
 * <code>
 * class FooBar {
 *   public function __construct($a, $b) {
 *     parent::__construct($a, $b);
 *   }
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
class Useless_Overriding_Method_Sniff implements Sniff
{
    /**
     * Registers the tokens that this sniff wants to listen for.
     *
     * @return int[]
     */
    public function register()
    {
        return [T_FUNCTION];
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
        // Skip function without body.
        if (isset($token['scope_opener']) === false) {
            return;
        }
        // Get function name.
        $method_name = $phpcs_file->get_declaration_name($stack_ptr);
        // Get all parameters from method signature.
        $signature = [];
        foreach ($phpcs_file->get_method_parameters($stack_ptr) as $param) {
            $signature[] = $param['name'];
        }
        $next = ++$token['scope_opener'];
        $end = --$token['scope_closer'];
        for (; $next <= $end; ++$next) {
            $code = $tokens[$next]['code'];
            if (isset(Tokens::$empty_tokens[$code]) === true) {
                continue;
            }
            if ($code === T_RETURN) {
                continue;
            }
            break;
        }
        // Any token except 'parent' indicates correct code.
        if ($tokens[$next]['code'] !== T_PARENT) {
            return;
        }
        // Find next non empty token index, should be double colon.
        $next = $phpcs_file->find_next(Tokens::$empty_tokens, $next + 1, null, true);
        // Skip for invalid code.
        if ($next === false || $tokens[$next]['code'] !== T_DOUBLE_COLON) {
            return;
        }
        // Find next non empty token index, should be the function name.
        $next = $phpcs_file->find_next(Tokens::$empty_tokens, $next + 1, null, true);
        // Skip for invalid code or other method.
        if ($next === false || $tokens[$next]['content'] !== $method_name) {
            return;
        }
        // Find next non empty token index, should be the open parenthesis.
        $next = $phpcs_file->find_next(Tokens::$empty_tokens, $next + 1, null, true);
        // Skip for invalid code.
        if ($next === false || $tokens[$next]['code'] !== T_OPEN_PARENTHESIS) {
            return;
        }
        $parameters = [''];
        $parenthesis_count = 1;
        $count = count($tokens);
        for (++$next; $next < $count; ++$next) {
            $code = $tokens[$next]['code'];
            if ($code === T_OPEN_PARENTHESIS) {
                ++$parenthesis_count;
            } elseif ($code === T_CLOSE_PARENTHESIS) {
                --$parenthesis_count;
            } elseif ($parenthesis_count === 1 && $code === T_COMMA) {
                $parameters[] = '';
            } elseif (isset(Tokens::$empty_tokens[$code]) === false) {
                $parameters[count($parameters) - 1] .= $tokens[$next]['content'];
            }
            if ($parenthesis_count === 0) {
                break;
            }
        }
        //end for
        $next = $phpcs_file->find_next(Tokens::$empty_tokens, $next + 1, null, true);
        if ($next === false || $tokens[$next]['code'] !== T_SEMICOLON) {
            return;
        }
        // Check rest of the scope.
        for (++$next; $next <= $end; ++$next) {
            $code = $tokens[$next]['code'];
            // Skip for any other content.
            if (isset(Tokens::$empty_tokens[$code]) === false) {
                return;
            }
        }
        $parameters = array_map('trim', $parameters);
        $parameters = array_filter($parameters);
        if (count($parameters) === count($signature) && $parameters === $signature) {
            $phpcs_file->add_warning('Possible useless method overriding detected', $stack_ptr, 'Found');
        }
    }
    //end process()
}
//end class