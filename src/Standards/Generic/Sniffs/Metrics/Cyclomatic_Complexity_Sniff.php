<?php

declare (strict_types=1);
/**
 * Checks the cyclomatic complexity (McCabe) for functions.
 *
 * The cyclomatic complexity (also called McCabe code metrics)
 * indicates the complexity within a function by counting
 * the different paths the function includes.
 *
 * @author    Johann-Peter Hartmann <hartmann@mayflower.de>
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2007-2014 Mayflower GmbH
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Generic\Sniffs\Metrics;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Cyclomatic_Complexity_Sniff implements Sniff
{
    /**
     * A complexity higher than this value will throw a warning.
     *
     * @var integer
     */
    public $complexity = 10;
    /**
     * A complexity higher than this value will throw an error.
     *
     * @var integer
     */
    public $absolute_complexity = 20;
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
        // Predicate nodes for PHP.
        $find = [T_CASE => true, T_DEFAULT => true, T_CATCH => true, T_IF => true, T_FOR => true, T_FOREACH => true, T_WHILE => true, T_ELSEIF => true, T_INLINE_THEN => true, T_COALESCE => true, T_COALESCE_EQUAL => true, T_MATCH_ARROW => true, T_NULLSAFE_OBJECT_OPERATOR => true];
        $complexity = 1;
        // Iterate from start to end and count predicate nodes.
        for ($i = $start + 1; $i < $end; $i++) {
            if (isset($find[$tokens[$i]['code']]) === true) {
                $complexity++;
            }
        }
        if ($complexity > $this->absolute_complexity) {
            $error = 'Function\'s cyclomatic complexity (%s) exceeds allowed maximum of %s';
            $data = [$complexity, $this->absolute_complexity];
            $phpcs_file->add_error($error, $stack_ptr, 'MaxExceeded', $data);
        } elseif ($complexity > $this->complexity) {
            $warning = 'Function\'s cyclomatic complexity (%s) exceeds %s; consider refactoring the function';
            $data = [$complexity, $this->complexity];
            $phpcs_file->add_warning($warning, $stack_ptr, 'TooHigh', $data);
        }
    }
    //end process()
}
//end class