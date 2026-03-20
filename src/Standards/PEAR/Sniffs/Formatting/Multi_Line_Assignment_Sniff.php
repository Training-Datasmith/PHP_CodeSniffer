<?php

declare (strict_types=1);
/**
 * If an assignment goes over two lines, ensure the equal sign is indented.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\PEAR\Sniffs\Formatting;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Multi_Line_Assignment_Sniff implements Sniff
{
    /**
     * The number of spaces code should be indented.
     *
     * @var integer
     */
    public $indent = 4;
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_EQUAL];
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
        // Equal sign can't be the last thing on the line.
        $next = $phpcs_file->find_next(T_WHITESPACE, $stack_ptr + 1, null, true);
        if ($next === false) {
            // Bad assignment.
            return;
        }
        if ($tokens[$next]['line'] !== $tokens[$stack_ptr]['line']) {
            $error = 'Multi-line assignments must have the equal sign on the second line';
            $phpcs_file->add_error($error, $stack_ptr, 'EqualSignLine');
            return;
        }
        // Make sure it is the first thing on the line, otherwise we ignore it.
        $prev = $phpcs_file->find_previous(T_WHITESPACE, $stack_ptr - 1, false, true);
        if ($prev === false) {
            // Bad assignment.
            return;
        }
        if ($tokens[$prev]['line'] === $tokens[$stack_ptr]['line']) {
            return;
        }
        // Find the required indent based on the ident of the previous line.
        $assignment_indent = 0;
        $prev_line = $tokens[$prev]['line'];
        for ($i = $prev - 1; $i >= 0; $i--) {
            if ($tokens[$i]['line'] !== $prev_line) {
                $i++;
                break;
            }
        }
        if ($tokens[$i]['code'] === T_WHITESPACE) {
            $assignment_indent = $tokens[$i]['length'];
        }
        // Find the actual indent.
        $prev = $phpcs_file->find_previous(T_WHITESPACE, $stack_ptr - 1);
        $expected_indent = $assignment_indent + $this->indent;
        $found_indent = $tokens[$prev]['length'];
        if ($found_indent !== $expected_indent) {
            $error = 'Multi-line assignment not indented correctly; expected %s spaces but found %s';
            $data = [$expected_indent, $found_indent];
            $phpcs_file->add_error($error, $stack_ptr, 'Indent', $data);
        }
    }
    //end process()
}
//end class