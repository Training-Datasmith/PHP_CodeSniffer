<?php

declare (strict_types=1);
/**
 * Verifies that operators have valid spacing surrounding them.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\White_Space;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Operator_Spacing_Sniff implements Sniff
{
    /**
     * A list of tokenizers this sniff supports.
     *
     * @var array
     */
    public $supported_tokenizers = ['PHP', 'JS'];
    /**
     * Allow newlines instead of spaces.
     *
     * @var boolean
     */
    public $ignore_newlines = false;
    /**
     * Don't check spacing for assignment operators.
     *
     * This allows multiple assignment statements to be aligned.
     *
     * @var boolean
     */
    public $ignore_spacing_before_assignments = true;
    /**
     * A list of tokens that aren't considered as operands.
     *
     * @var string[]
     */
    private $non_operand_tokens = [];
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        /*
            First we setup an array of all the tokens that can come before
            a T_MINUS or T_PLUS token to indicate that the token is not being
            used as an operator.
        */
        // Trying to operate on a negative value; eg. ($var * -1).
        $this->non_operand_tokens = Tokens::$operators;
        // Trying to compare a negative value; eg. ($var === -1).
        $this->non_operand_tokens += Tokens::$comparison_tokens;
        // Trying to compare a negative value; eg. ($var || -1 === $b).
        $this->non_operand_tokens += Tokens::$boolean_operators;
        // Trying to assign a negative value; eg. ($var = -1).
        $this->non_operand_tokens += Tokens::$assignment_tokens;
        // Returning/printing a negative value; eg. (return -1).
        $this->non_operand_tokens += [T_RETURN => T_RETURN, T_ECHO => T_ECHO, T_EXIT => T_EXIT, T_PRINT => T_PRINT, T_YIELD => T_YIELD, T_FN_ARROW => T_FN_ARROW, T_MATCH_ARROW => T_MATCH_ARROW];
        // Trying to use a negative value; eg. myFunction($var, -2).
        $this->non_operand_tokens += [T_CASE => T_CASE, T_COLON => T_COLON, T_COMMA => T_COMMA, T_INLINE_ELSE => T_INLINE_ELSE, T_INLINE_THEN => T_INLINE_THEN, T_OPEN_CURLY_BRACKET => T_OPEN_CURLY_BRACKET, T_OPEN_PARENTHESIS => T_OPEN_PARENTHESIS, T_OPEN_SHORT_ARRAY => T_OPEN_SHORT_ARRAY, T_OPEN_SQUARE_BRACKET => T_OPEN_SQUARE_BRACKET, T_STRING_CONCAT => T_STRING_CONCAT];
        // Casting a negative value; eg. (array) -$a.
        $this->non_operand_tokens += Tokens::$cast_tokens;
        /*
            These are the tokens the sniff is looking for.
        */
        $targets = Tokens::$comparison_tokens;
        $targets += Tokens::$operators;
        $targets += Tokens::$assignment_tokens;
        $targets[] = T_INLINE_THEN;
        $targets[] = T_INLINE_ELSE;
        $targets[] = T_INSTANCEOF;
        return $targets;
    }
    //end register()
    /**
     * Processes this sniff, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The current file being checked.
     * @param int                         $stackPtr  The position of the current token in
     *                                               the stack passed in $tokens.
     *
     * @return void
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        if ($this->is_operator($phpcs_file, $stack_ptr) === false) {
            return;
        }
        if ($tokens[$stack_ptr]['code'] === T_BITWISE_AND) {
            // Check there is one space before the & operator.
            if ($tokens[$stack_ptr - 1]['code'] !== T_WHITESPACE) {
                $error = 'Expected 1 space before "&" operator; 0 found';
                $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'NoSpaceBeforeAmp');
                if ($fix === true) {
                    $phpcs_file->fixer->add_content_before($stack_ptr, ' ');
                }
                $phpcs_file->record_metric($stack_ptr, 'Space before operator', 0);
            } else {
                if ($tokens[$stack_ptr - 2]['line'] !== $tokens[$stack_ptr]['line']) {
                    $found = 'newline';
                } else {
                    $found = $tokens[$stack_ptr - 1]['length'];
                }
                $phpcs_file->record_metric($stack_ptr, 'Space before operator', $found);
                if ($found !== 1 && ($found !== 'newline' || $this->ignore_newlines === false)) {
                    $error = 'Expected 1 space before "&" operator; %s found';
                    $data = [$found];
                    $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'SpacingBeforeAmp', $data);
                    if ($fix === true) {
                        $phpcs_file->fixer->replace_token($stack_ptr - 1, ' ');
                    }
                }
            }
            //end if
            $has_next = $phpcs_file->find_next(T_WHITESPACE, $stack_ptr + 1, null, true);
            if ($has_next === false) {
                // Live coding/parse error at end of file.
                return;
            }
            // Check there is one space after the & operator.
            if ($tokens[$stack_ptr + 1]['code'] !== T_WHITESPACE) {
                $error = 'Expected 1 space after "&" operator; 0 found';
                $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'NoSpaceAfterAmp');
                if ($fix === true) {
                    $phpcs_file->fixer->add_content($stack_ptr, ' ');
                }
                $phpcs_file->record_metric($stack_ptr, 'Space after operator', 0);
            } else {
                if ($tokens[$stack_ptr + 2]['line'] !== $tokens[$stack_ptr]['line']) {
                    $found = 'newline';
                } else {
                    $found = $tokens[$stack_ptr + 1]['length'];
                }
                $phpcs_file->record_metric($stack_ptr, 'Space after operator', $found);
                if ($found !== 1 && ($found !== 'newline' || $this->ignore_newlines === false)) {
                    $error = 'Expected 1 space after "&" operator; %s found';
                    $data = [$found];
                    $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'SpacingAfterAmp', $data);
                    if ($fix === true) {
                        $phpcs_file->fixer->replace_token($stack_ptr + 1, ' ');
                    }
                }
            }
            //end if
            return;
        }
        //end if
        $operator = $tokens[$stack_ptr]['content'];
        if ($tokens[$stack_ptr - 1]['code'] !== T_WHITESPACE && ($tokens[$stack_ptr - 1]['code'] === T_INLINE_THEN && $tokens[$stack_ptr]['code'] === T_INLINE_ELSE) === false) {
            $error = "Expected 1 space before \"{$operator}\"; 0 found";
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'NoSpaceBefore');
            if ($fix === true) {
                $phpcs_file->fixer->add_content_before($stack_ptr, ' ');
            }
            $phpcs_file->record_metric($stack_ptr, 'Space before operator', 0);
        } elseif (isset(Tokens::$assignment_tokens[$tokens[$stack_ptr]['code']]) === false || $this->ignore_spacing_before_assignments === false) {
            // Throw an error for assignments only if enabled using the sniff property
            // because other standards allow multiple spaces to align assignments.
            if ($tokens[$stack_ptr - 2]['line'] !== $tokens[$stack_ptr]['line']) {
                $found = 'newline';
            } else {
                $found = $tokens[$stack_ptr - 1]['length'];
            }
            $phpcs_file->record_metric($stack_ptr, 'Space before operator', $found);
            if ($found !== 1 && ($found !== 'newline' || $this->ignore_newlines === false)) {
                $error = 'Expected 1 space before "%s"; %s found';
                $data = [$operator, $found];
                $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'SpacingBefore', $data);
                if ($fix === true) {
                    $phpcs_file->fixer->begin_changeset();
                    if ($found === 'newline') {
                        $i = $stack_ptr - 2;
                        while ($tokens[$i]['code'] === T_WHITESPACE) {
                            $phpcs_file->fixer->replace_token($i, '');
                            $i--;
                        }
                    }
                    $phpcs_file->fixer->replace_token($stack_ptr - 1, ' ');
                    $phpcs_file->fixer->end_changeset();
                }
            }
            //end if
        }
        //end if
        $has_next = $phpcs_file->find_next(T_WHITESPACE, $stack_ptr + 1, null, true);
        if ($has_next === false) {
            // Live coding/parse error at end of file.
            return;
        }
        if ($tokens[$stack_ptr + 1]['code'] !== T_WHITESPACE) {
            // Skip short ternary such as: "$foo = $bar ?: true;".
            if ($tokens[$stack_ptr]['code'] === T_INLINE_THEN && $tokens[$stack_ptr + 1]['code'] === T_INLINE_ELSE) {
                return;
            }
            $error = "Expected 1 space after \"{$operator}\"; 0 found";
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'NoSpaceAfter');
            if ($fix === true) {
                $phpcs_file->fixer->add_content($stack_ptr, ' ');
            }
            $phpcs_file->record_metric($stack_ptr, 'Space after operator', 0);
        } else {
            if (isset($tokens[$stack_ptr + 2]) === true && $tokens[$stack_ptr + 2]['line'] !== $tokens[$stack_ptr]['line']) {
                $found = 'newline';
            } else {
                $found = $tokens[$stack_ptr + 1]['length'];
            }
            $phpcs_file->record_metric($stack_ptr, 'Space after operator', $found);
            if ($found !== 1 && ($found !== 'newline' || $this->ignore_newlines === false)) {
                $error = 'Expected 1 space after "%s"; %s found';
                $data = [$operator, $found];
                $next_non_whitespace = $phpcs_file->find_next(T_WHITESPACE, $stack_ptr + 1, null, true);
                if ($next_non_whitespace !== false && isset(Tokens::$comment_tokens[$tokens[$next_non_whitespace]['code']]) === true && $found === 'newline') {
                    // Don't auto-fix when it's a comment or PHPCS annotation on a new line as
                    // it causes fixer conflicts and can cause the meaning of annotations to change.
                    $phpcs_file->add_error($error, $stack_ptr, 'SpacingAfter', $data);
                } else {
                    $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'SpacingAfter', $data);
                    if ($fix === true) {
                        $phpcs_file->fixer->replace_token($stack_ptr + 1, ' ');
                    }
                }
            }
            //end if
        }
        //end if
    }
    //end process()
    /**
     * Checks if an operator is actually a different type of token in the current context.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The current file being checked.
     * @param int                         $stackPtr  The position of the operator in
     *                                               the stack.
     *
     * @return boolean
     */
    protected function is_operator(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        // Skip default values in function declarations.
        // Skip declare statements.
        if ($tokens[$stack_ptr]['code'] === T_EQUAL || $tokens[$stack_ptr]['code'] === T_MINUS) {
            if (isset($tokens[$stack_ptr]['nested_parenthesis']) === true) {
                $parenthesis = array_keys($tokens[$stack_ptr]['nested_parenthesis']);
                $bracket = array_pop($parenthesis);
                if (isset($tokens[$bracket]['parenthesis_owner']) === true) {
                    $function = $tokens[$bracket]['parenthesis_owner'];
                    if ($tokens[$function]['code'] === T_FUNCTION || $tokens[$function]['code'] === T_CLOSURE || $tokens[$function]['code'] === T_FN || $tokens[$function]['code'] === T_DECLARE) {
                        return false;
                    }
                }
            }
        }
        if ($tokens[$stack_ptr]['code'] === T_EQUAL) {
            // Skip for '=&' case.
            if (isset($tokens[$stack_ptr + 1]) === true && $tokens[$stack_ptr + 1]['code'] === T_BITWISE_AND) {
                return false;
            }
        }
        if ($tokens[$stack_ptr]['code'] === T_BITWISE_AND) {
            // If it's not a reference, then we expect one space either side of the
            // bitwise operator.
            if ($phpcs_file->is_reference($stack_ptr) === true) {
                return false;
            }
        }
        if ($tokens[$stack_ptr]['code'] === T_MINUS || $tokens[$stack_ptr]['code'] === T_PLUS) {
            // Check minus spacing, but make sure we aren't just assigning
            // a minus value or returning one.
            $prev = $phpcs_file->find_previous(Tokens::$empty_tokens, $stack_ptr - 1, null, true);
            if (isset($this->non_operand_tokens[$tokens[$prev]['code']]) === true) {
                return false;
            }
        }
        //end if
        return true;
    }
    //end isOperator()
}
//end class