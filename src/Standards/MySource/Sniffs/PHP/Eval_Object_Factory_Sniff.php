<?php

declare (strict_types=1);
/**
 * Ensures that eval() is not used to create objects.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\My_Source\Sniffs\PHP;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Eval_Object_Factory_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_EVAL];
    }
    //end register()
    /**
     * Processes this sniff, when one of its tokens is encountered.
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
        /*
            We need to find all strings that will be in the eval
            to determine if the "new" keyword is being used.
        */
        $open_bracket = $phpcs_file->find_next(T_OPEN_PARENTHESIS, $stack_ptr + 1);
        $close_bracket = $tokens[$open_bracket]['parenthesis_closer'];
        $strings = [];
        $vars = [];
        for ($i = $open_bracket + 1; $i < $close_bracket; $i++) {
            if (isset(Tokens::$string_tokens[$tokens[$i]['code']]) === true) {
                $strings[$i] = $tokens[$i]['content'];
            } elseif ($tokens[$i]['code'] === T_VARIABLE) {
                $vars[$i] = $tokens[$i]['content'];
            }
        }
        /*
            We now have some variables that we need to expand into
            the strings that were assigned to them, if any.
        */
        foreach ($vars as $var_ptr => $var_name) {
            while (($prev = $phpcs_file->find_previous(T_VARIABLE, $var_ptr - 1)) !== false) {
                // Make sure this is an assignment of the variable. That means
                // it will be the first thing on the line.
                $prev_content = $phpcs_file->find_previous(T_WHITESPACE, $prev - 1, null, true);
                if ($tokens[$prev_content]['line'] === $tokens[$prev]['line']) {
                    $var_ptr = $prev_content;
                    continue;
                }
                if ($tokens[$prev]['content'] !== $var_name) {
                    // This variable has a different name.
                    $var_ptr = $prev_content;
                    continue;
                }
                // We found one.
                break;
            }
            //end while
            if ($prev !== false) {
                // Find all strings on the line.
                $line_end = $phpcs_file->find_next(T_SEMICOLON, $prev + 1);
                for ($i = $prev + 1; $i < $line_end; $i++) {
                    if (isset(Tokens::$string_tokens[$tokens[$i]['code']]) === true) {
                        $strings[$i] = $tokens[$i]['content'];
                    }
                }
            }
        }
        //end foreach
        foreach ($strings as $string) {
            // If the string has "new" in it, it is not allowed.
            // We don't bother checking if the word "new" is printed to screen
            // because that is unlikely to happen. We assume the use
            // of "new" is for object instantiation.
            if (strstr($string, ' new ') !== false) {
                $error = 'Do not use eval() to create objects dynamically; use reflection instead';
                $phpcs_file->add_warning($error, $stack_ptr, 'Found');
            }
        }
    }
    //end process()
}
//end class