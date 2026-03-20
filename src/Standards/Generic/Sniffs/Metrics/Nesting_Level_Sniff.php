<?php

declare (strict_types=1);
/**
 * Checks the nesting level for methods.
 *
 * @author    Johann-Peter Hartmann <hartmann@mayflower.de>
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2007-2014 Mayflower GmbH
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Generic\Sniffs\Metrics;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Nesting_Level_Sniff implements Sniff
{
    /**
     * A nesting level higher than this value will throw a warning.
     *
     * @var integer
     */
    public $nesting_level = 5;
    /**
     * A nesting level higher than this value will throw an error.
     *
     * @var integer
     */
    public $absolute_nesting_level = 10;
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
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
        // Ignore abstract methods.
        if (isset($tokens[$stack_ptr]['scope_opener']) === false) {
            return;
        }
        // Detect start and end of this function definition.
        $start = $tokens[$stack_ptr]['scope_opener'];
        $end = $tokens[$stack_ptr]['scope_closer'];
        $nesting_level = 0;
        // Find the maximum nesting level of any token in the function.
        for ($i = $start + 1; $i < $end; $i++) {
            $level = $tokens[$i]['level'];
            if ($nesting_level < $level) {
                $nesting_level = $level;
            }
        }
        // We subtract the nesting level of the function itself.
        $nesting_level = $nesting_level - $tokens[$stack_ptr]['level'] - 1;
        if ($nesting_level > $this->absolute_nesting_level) {
            $error = 'Function\'s nesting level (%s) exceeds allowed maximum of %s';
            $data = [$nesting_level, $this->absolute_nesting_level];
            $phpcs_file->add_error($error, $stack_ptr, 'MaxExceeded', $data);
        } elseif ($nesting_level > $this->nesting_level) {
            $warning = 'Function\'s nesting level (%s) exceeds %s; consider refactoring the function';
            $data = [$nesting_level, $this->nesting_level];
            $phpcs_file->add_warning($warning, $stack_ptr, 'TooHigh', $data);
        }
    }
    //end process()
}
//end class