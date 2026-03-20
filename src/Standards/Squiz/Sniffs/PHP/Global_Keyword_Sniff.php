<?php

declare (strict_types=1);
/**
 * Stops the usage of the "global" keyword.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\PHP;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Global_Keyword_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_GLOBAL];
    }
    //end register()
    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token in the
     *                                               stack passed in $tokens.
     *
     * @return void
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        $next_var = $tokens[$phpcs_file->find_next([T_VARIABLE], $stack_ptr)];
        $var_name = str_replace('$', '', $next_var['content']);
        $error = 'Use of the "global" keyword is forbidden; use "$GLOBALS[\'%s\']" instead';
        $data = [$var_name];
        $phpcs_file->add_error($error, $stack_ptr, 'NotAllowed', $data);
    }
    //end process()
}
//end class