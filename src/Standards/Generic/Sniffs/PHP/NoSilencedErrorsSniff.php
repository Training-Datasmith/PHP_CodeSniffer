<?php

declare (strict_types=1);
/**
 * Throws an error or warning when any code prefixed with an asperand is encountered.
 *
 * <code>
 *  if (@in_array($array, $needle))
 *  {
 *      doSomething();
 *  }
 * </code>
 *
 * @author    Andy Brockhurst <abrock@yahoo-inc.com>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Generic\Sniffs\PHP;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class No_Silenced_Errors_Sniff implements Sniff
{
    /**
     * If true, an error will be thrown; otherwise a warning.
     *
     * @var boolean
     */
    public $error = false;
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_ASPERAND];
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
        // Prepare the "Found" string to display.
        $context_length = 4;
        $end_of_statement = $phpcs_file->find_end_of_statement($stack_ptr, [T_COMMA, T_COLON]);
        if ($end_of_statement - $stack_ptr < $context_length) {
            $context_length = $end_of_statement - $stack_ptr;
        }
        $found = $phpcs_file->get_tokens_as_string($stack_ptr, $context_length);
        $found = str_replace(["\t", "\n", "\r"], ' ', $found) . '...';
        if ($this->error === true) {
            $error = 'Silencing errors is forbidden; found: %s';
            $phpcs_file->add_error($error, $stack_ptr, 'Forbidden', [$found]);
        } else {
            $error = 'Silencing errors is discouraged; found: %s';
            $phpcs_file->add_warning($error, $stack_ptr, 'Discouraged', [$found]);
        }
    }
    //end process()
}
//end class