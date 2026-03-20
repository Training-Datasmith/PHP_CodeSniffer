<?php

declare (strict_types=1);
/**
 * Checks that control structures have boolean operators in the correct place.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2019 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\PSR12\Sniffs\Control_Structures;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Boolean_Operator_Placement_Sniff implements Sniff
{
    /**
     * Used to restrict the placement of the boolean operator.
     *
     * Allowed value are "first" or "last".
     *
     * @var string|null
     */
    public $allow_only;
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_IF, T_WHILE, T_SWITCH, T_ELSEIF, T_MATCH];
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
        if (isset($tokens[$stack_ptr]['parenthesis_opener']) === false || isset($tokens[$stack_ptr]['parenthesis_closer']) === false) {
            return;
        }
        $paren_opener = $tokens[$stack_ptr]['parenthesis_opener'];
        $paren_closer = $tokens[$stack_ptr]['parenthesis_closer'];
        if ($tokens[$paren_opener]['line'] === $tokens[$paren_closer]['line']) {
            // Conditions are all on the same line.
            return;
        }
        $find = [T_BOOLEAN_AND, T_BOOLEAN_OR];
        if ($this->allow_only === 'first' || $this->allow_only === 'last') {
            $position = $this->allow_only;
        } else {
            $position = null;
        }
        $operator = $paren_opener;
        $error = false;
        $operators = [];
        do {
            $operator = $phpcs_file->find_next($find, $operator + 1, $paren_closer);
            if ($operator === false) {
                break;
            }
            $prev = $phpcs_file->find_previous(T_WHITESPACE, $operator - 1, $paren_opener, true);
            if ($prev === false) {
                // Parse error.
                return;
            }
            $next = $phpcs_file->find_next(T_WHITESPACE, $operator + 1, $paren_closer, true);
            if ($next === false) {
                // Parse error.
                return;
            }
            $first_on_line = false;
            $last_on_line = false;
            if ($tokens[$prev]['line'] < $tokens[$operator]['line']) {
                // The boolean operator is the first content on the line.
                $first_on_line = true;
            }
            if ($tokens[$next]['line'] > $tokens[$operator]['line']) {
                // The boolean operator is the last content on the line.
                $last_on_line = true;
            }
            if ($first_on_line === true && $last_on_line === true) {
                // The operator is the only content on the line.
                // Don't record it because we can't determine
                // placement information from looking at it.
                continue;
            }
            $operators[] = $operator;
            if ($first_on_line === false && $last_on_line === false) {
                // It's in the middle of content, so we can't determine
                // placement information from looking at it, but we may
                // still need to process it.
                continue;
            }
            if ($first_on_line === true) {
                if ($position === null) {
                    $position = 'first';
                }
                if ($position !== 'first') {
                    $error = true;
                }
            } else {
                if ($position === null) {
                    $position = 'last';
                }
                if ($position !== 'last') {
                    $error = true;
                }
            }
        } while ($operator !== false);
        if ($error === false) {
            return;
        }
        switch ($this->allow_only) {
            case 'first':
                $error = 'Boolean operators between conditions must be at the beginning of the line';
                break;
            case 'last':
                $error = 'Boolean operators between conditions must be at the end of the line';
                break;
            default:
                $error = 'Boolean operators between conditions must be at the beginning or end of the line, but not both';
        }
        $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'FoundMixed');
        if ($fix === false) {
            return;
        }
        $phpcs_file->fixer->begin_changeset();
        foreach ($operators as $operator) {
            $prev = $phpcs_file->find_previous(T_WHITESPACE, $operator - 1, $paren_opener, true);
            $next = $phpcs_file->find_next(T_WHITESPACE, $operator + 1, $paren_closer, true);
            if ($position === 'last') {
                if ($tokens[$next]['line'] === $tokens[$operator]['line']) {
                    if ($tokens[$prev]['line'] === $tokens[$operator]['line']) {
                        // Move the content after the operator to the next line.
                        if ($tokens[$operator + 1]['code'] === T_WHITESPACE) {
                            $phpcs_file->fixer->replace_token($operator + 1, '');
                        }
                        $first = $phpcs_file->find_first_on_line(T_WHITESPACE, $operator, true);
                        $padding = str_repeat(' ', $tokens[$first]['column'] - 1);
                        $phpcs_file->fixer->add_content($operator, $phpcs_file->eol_char . $padding);
                    } else {
                        // Move the operator to the end of the previous line.
                        if ($tokens[$operator + 1]['code'] === T_WHITESPACE) {
                            $phpcs_file->fixer->replace_token($operator + 1, '');
                        }
                        $phpcs_file->fixer->add_content($prev, ' ' . $tokens[$operator]['content']);
                        $phpcs_file->fixer->replace_token($operator, '');
                    }
                }
                //end if
            } else {
                if ($tokens[$prev]['line'] === $tokens[$operator]['line']) {
                    if ($tokens[$next]['line'] === $tokens[$operator]['line']) {
                        // Move the operator, and the rest of the expression, to the next line.
                        if ($tokens[$operator - 1]['code'] === T_WHITESPACE) {
                            $phpcs_file->fixer->replace_token($operator - 1, '');
                        }
                        $first = $phpcs_file->find_first_on_line(T_WHITESPACE, $operator, true);
                        $padding = str_repeat(' ', $tokens[$first]['column'] - 1);
                        $phpcs_file->fixer->add_content_before($operator, $phpcs_file->eol_char . $padding);
                    } else {
                        // Move the operator to the start of the next line.
                        if ($tokens[$operator - 1]['code'] === T_WHITESPACE) {
                            $phpcs_file->fixer->replace_token($operator - 1, '');
                        }
                        $phpcs_file->fixer->add_content_before($next, $tokens[$operator]['content'] . ' ');
                        $phpcs_file->fixer->replace_token($operator, '');
                    }
                }
                //end if
            }
            //end if
        }
        //end foreach
        $phpcs_file->fixer->end_changeset();
    }
    //end process()
}
//end class