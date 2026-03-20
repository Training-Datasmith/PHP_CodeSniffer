<?php

declare (strict_types=1);
/**
 * Checks that the strict_types has been declared.
 *
 * @author    Sertan Danis <sdanis@squiz.net>
 * @copyright 2006-2019 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Generic\Sniffs\PHP;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Require_Strict_Types_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_OPEN_TAG];
    }
    //end register()
    /**
     * Processes this sniff, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token in
     *                                               the stack passed in $tokens.
     *
     * @return int
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        $declare = $phpcs_file->find_next(T_DECLARE, $stack_ptr);
        $found = false;
        if ($declare !== false) {
            $next_string = $phpcs_file->find_next(T_STRING, $declare);
            if ($next_string !== false) {
                if (strtolower($tokens[$next_string]['content']) === 'strict_types') {
                    // There is a strict types declaration.
                    $found = true;
                }
            }
        }
        if ($found === false) {
            $error = 'Missing required strict_types declaration';
            $phpcs_file->add_error($error, $stack_ptr, 'MissingDeclaration');
        }
        // Skip the rest of the file so we don't pick up additional
        // open tags, typically embedded in HTML.
        return $phpcs_file->num_tokens;
    }
    //end process()
}
//end class