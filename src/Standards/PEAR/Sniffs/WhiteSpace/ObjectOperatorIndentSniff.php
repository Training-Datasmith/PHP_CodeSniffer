<?php

declare (strict_types=1);
/**
 * Checks that object operators are indented correctly.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\PEAR\Sniffs\White_Space;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Object_Operator_Indent_Sniff implements Sniff
{
    /**
     * The number of spaces code should be indented.
     *
     * @var integer
     */
    public $indent = 4;
    /**
     * Indicates whether multilevel indenting is allowed.
     *
     * @var boolean
     */
    public $multilevel = false;
    /**
     * Tokens to listen for.
     *
     * @var array
     */
    private $targets = [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR];
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return int[]
     */
    public function register()
    {
        return $this->targets;
    }
    //end register()
    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile All the tokens found in the document.
     * @param int                         $stackPtr  The position of the current token
     *                                               in the stack passed in $tokens.
     *
     * @return void
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        // Make sure this is the first object operator in a chain of them.
        $start = $phpcs_file->find_start_of_statement($stack_ptr);
        $prev = $phpcs_file->find_previous($this->targets, $stack_ptr - 1, $start);
        if ($prev !== false) {
            return;
        }
        // Make sure this is a chained call.
        $end = $phpcs_file->find_end_of_statement($stack_ptr);
        $next = $phpcs_file->find_next($this->targets, $stack_ptr + 1, $end);
        if ($next === false) {
            // Not a chained call.
            return;
        }
        // Determine correct indent.
        for ($i = $start - 1; $i >= 0; $i--) {
            if ($tokens[$i]['line'] !== $tokens[$start]['line']) {
                $i++;
                break;
            }
        }
        $base_indent = 0;
        if ($i >= 0 && $tokens[$i]['code'] === T_WHITESPACE) {
            $base_indent = $tokens[$i]['length'];
        }
        $base_indent += $this->indent;
        // Determine the scope of the original object operator.
        $orig_brackets = null;
        if (isset($tokens[$stack_ptr]['nested_parenthesis']) === true) {
            $orig_brackets = $tokens[$stack_ptr]['nested_parenthesis'];
        }
        $orig_conditions = null;
        if (isset($tokens[$stack_ptr]['conditions']) === true) {
            $orig_conditions = $tokens[$stack_ptr]['conditions'];
        }
        // Check indentation of each object operator in the chain.
        // If the first object operator is on a different line than
        // the variable, make sure we check its indentation too.
        if ($tokens[$stack_ptr]['line'] > $tokens[$start]['line']) {
            $next = $stack_ptr;
        }
        $previous_indent = $base_indent;
        while ($next !== false) {
            // Make sure it is in the same scope, otherwise don't check indent.
            $brackets = null;
            if (isset($tokens[$next]['nested_parenthesis']) === true) {
                $brackets = $tokens[$next]['nested_parenthesis'];
            }
            $conditions = null;
            if (isset($tokens[$next]['conditions']) === true) {
                $conditions = $tokens[$next]['conditions'];
            }
            if ($orig_brackets === $brackets && $orig_conditions === $conditions) {
                // Make sure it starts a line, otherwise don't check indent.
                $prev = $phpcs_file->find_previous(T_WHITESPACE, $next - 1, $stack_ptr, true);
                $indent = $tokens[$next - 1];
                if ($tokens[$prev]['line'] !== $tokens[$next]['line'] && $indent['code'] === T_WHITESPACE) {
                    if ($indent['line'] === $tokens[$next]['line']) {
                        $found_indent = strlen($indent['content']);
                    } else {
                        $found_indent = 0;
                    }
                    $min_indent = $previous_indent;
                    $max_indent = $previous_indent;
                    $expected_indent = $previous_indent;
                    if ($this->multilevel === true) {
                        $min_indent = max($previous_indent - $this->indent, $base_indent);
                        $max_indent = $previous_indent + $this->indent;
                        $expected_indent = min(max($found_indent, $min_indent), $max_indent);
                    }
                    if ($found_indent < $min_indent || $found_indent > $max_indent) {
                        $error = 'Object operator not indented correctly; expected %s spaces but found %s';
                        $data = [$expected_indent, $found_indent];
                        $fix = $phpcs_file->add_fixable_error($error, $next, 'Incorrect', $data);
                        if ($fix === true) {
                            $spaces = str_repeat(' ', $expected_indent);
                            if ($found_indent === 0) {
                                $phpcs_file->fixer->add_content_before($next, $spaces);
                            } else {
                                $phpcs_file->fixer->replace_token($next - 1, $spaces);
                            }
                        }
                    }
                    $previous_indent = $expected_indent;
                }
                //end if
                // It cant be the last thing on the line either.
                $content = $phpcs_file->find_next(T_WHITESPACE, $next + 1, null, true);
                if ($tokens[$content]['line'] !== $tokens[$next]['line']) {
                    $error = 'Object operator must be at the start of the line, not the end';
                    $fix = $phpcs_file->add_fixable_error($error, $next, 'StartOfLine');
                    if ($fix === true) {
                        $phpcs_file->fixer->begin_changeset();
                        for ($x = $next + 1; $x < $content; $x++) {
                            $phpcs_file->fixer->replace_token($x, '');
                        }
                        $phpcs_file->fixer->add_newline_before($next);
                        $phpcs_file->fixer->end_changeset();
                    }
                }
            }
            //end if
            $next = $phpcs_file->find_next($this->targets, $next + 1, null, false, null, true);
        }
        //end while
    }
    //end process()
}
//end class