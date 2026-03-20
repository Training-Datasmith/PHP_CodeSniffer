<?php

declare (strict_types=1);
/**
 * Ensures JS classes don't contain duplicate property names.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\Classes;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Duplicate_Property_Sniff implements Sniff
{
    /**
     * A list of tokenizers this sniff supports.
     *
     * @var array
     */
    public $supported_tokenizers = ['JS'];
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_OBJECT];
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
        $properties = [];
        $wanted_tokens = [T_PROPERTY, T_OBJECT];
        $next = $phpcs_file->find_next($wanted_tokens, $stack_ptr + 1, $tokens[$stack_ptr]['bracket_closer']);
        while ($next !== false && $next < $tokens[$stack_ptr]['bracket_closer']) {
            if ($tokens[$next]['code'] === T_OBJECT) {
                // Skip nested objects.
                $next = $tokens[$next]['bracket_closer'];
            } else {
                $prop_name = $tokens[$next]['content'];
                if (isset($properties[$prop_name]) === true) {
                    $error = 'Duplicate property definition found for "%s"; previously defined on line %s';
                    $data = [$prop_name, $tokens[$properties[$prop_name]]['line']];
                    $phpcs_file->add_error($error, $next, 'Found', $data);
                }
                $properties[$prop_name] = $next;
            }
            //end if
            $next = $phpcs_file->find_next($wanted_tokens, $next + 1, $tokens[$stack_ptr]['bracket_closer']);
        }
        //end while
    }
    //end process()
}
//end class