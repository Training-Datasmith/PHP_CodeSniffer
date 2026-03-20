<?php

declare (strict_types=1);
/**
 * This sniff class detected empty statement.
 *
 * This sniff implements the common algorithm for empty statement body detection.
 * A body is considered as empty if it is completely empty or it only contains
 * whitespace characters and/or comments.
 *
 * <code>
 * stmt {
 *   // foo
 * }
 * stmt (conditions) {
 *   // foo
 * }
 * </code>
 *
 * @author    Manuel Pichler <mapi@manuel-pichler.de>
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2007-2014 Manuel Pichler. All rights reserved.
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Generic\Sniffs\Code_Analysis;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Empty_Statement_Sniff implements Sniff
{
    /**
     * Registers the tokens that this sniff wants to listen for.
     *
     * @return int[]
     */
    public function register()
    {
        return [T_TRY, T_CATCH, T_FINALLY, T_DO, T_ELSE, T_ELSEIF, T_FOR, T_FOREACH, T_IF, T_SWITCH, T_WHILE, T_MATCH];
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
        // Skip statements without a body.
        if (isset($token['scope_opener']) === false) {
            return;
        }
        $next = $phpcs_file->find_next(Tokens::$empty_tokens, $token['scope_opener'] + 1, $token['scope_closer'] - 1, true);
        if ($next !== false) {
            return;
        }
        // Get token identifier.
        $name = strtoupper($token['content']);
        $error = 'Empty %s statement detected';
        $phpcs_file->add_error($error, $stack_ptr, 'Detected' . ucfirst(strtolower($name)), [$name]);
    }
    //end process()
}
//end class