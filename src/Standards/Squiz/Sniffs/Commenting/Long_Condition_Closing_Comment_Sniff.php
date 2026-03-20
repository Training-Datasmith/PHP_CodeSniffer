<?php

declare (strict_types=1);
/**
 * Ensures long conditions have a comment at the end.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\Commenting;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Long_Condition_Closing_Comment_Sniff implements Sniff
{
    /**
     * A list of tokenizers this sniff supports.
     *
     * @var array
     */
    public $supported_tokenizers = ['PHP', 'JS'];
    /**
     * The openers that we are interested in.
     *
     * @var integer[]
     */
    private static $openers = [T_SWITCH, T_IF, T_FOR, T_FOREACH, T_WHILE, T_TRY, T_CASE, T_MATCH];
    /**
     * The length that a code block must be before
     * requiring a closing comment.
     *
     * @var integer
     */
    public $line_limit = 20;
    /**
     * The format the end comment should be in.
     *
     * The placeholder %s will be replaced with the type of condition opener.
     *
     * @var string
     */
    public $comment_format = '//end %s';
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_CLOSE_CURLY_BRACKET];
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
        if (isset($tokens[$stack_ptr]['scope_condition']) === false) {
            // No scope condition. It is a function closer.
            return;
        }
        $start_condition = $tokens[$tokens[$stack_ptr]['scope_condition']];
        $start_brace = $tokens[$tokens[$stack_ptr]['scope_opener']];
        $end_brace = $tokens[$stack_ptr];
        // We are only interested in some code blocks.
        if (in_array($start_condition['code'], self::$openers, true) === false) {
            return;
        }
        if ($start_condition['code'] === T_IF) {
            // If this is actually an ELSE IF, skip it as the brace
            // will be checked by the original IF.
            $else = $phpcs_file->find_previous(T_WHITESPACE, $tokens[$stack_ptr]['scope_condition'] - 1, null, true);
            if ($tokens[$else]['code'] === T_ELSE) {
                return;
            }
            // IF statements that have an ELSE block need to use
            // "end if" rather than "end else" or "end elseif".
            do {
                $next_token = $phpcs_file->find_next(T_WHITESPACE, $stack_ptr + 1, null, true);
                if ($tokens[$next_token]['code'] === T_ELSE || $tokens[$next_token]['code'] === T_ELSEIF) {
                    // Check for ELSE IF (2 tokens) as opposed to ELSEIF (1 token).
                    if ($tokens[$next_token]['code'] === T_ELSE && isset($tokens[$next_token]['scope_closer']) === false) {
                        $next_token = $phpcs_file->find_next(T_WHITESPACE, $next_token + 1, null, true);
                        if ($tokens[$next_token]['code'] !== T_IF || isset($tokens[$next_token]['scope_closer']) === false) {
                            // Not an ELSE IF or is an inline ELSE IF.
                            break;
                        }
                    }
                    if (isset($tokens[$next_token]['scope_closer']) === false) {
                        // There isn't going to be anywhere to print the "end if" comment
                        // because there is no closer.
                        return;
                    }
                    // The end brace becomes the ELSE's end brace.
                    $stack_ptr = $tokens[$next_token]['scope_closer'];
                    $end_brace = $tokens[$stack_ptr];
                } else {
                    break;
                }
                //end if
            } while (isset($tokens[$next_token]['scope_closer']) === true);
        }
        //end if
        if ($start_condition['code'] === T_TRY) {
            // TRY statements need to check until the end of all CATCH statements.
            do {
                $next_token = $phpcs_file->find_next(T_WHITESPACE, $stack_ptr + 1, null, true);
                if ($tokens[$next_token]['code'] === T_CATCH || $tokens[$next_token]['code'] === T_FINALLY) {
                    // The end brace becomes the CATCH end brace.
                    $stack_ptr = $tokens[$next_token]['scope_closer'];
                    $end_brace = $tokens[$stack_ptr];
                } else {
                    break;
                }
            } while (isset($tokens[$next_token]['scope_closer']) === true);
        }
        if ($start_condition['code'] === T_MATCH) {
            // Move the stackPtr to after the semi-colon/comma if there is one.
            $next_token = $phpcs_file->find_next(T_WHITESPACE, $stack_ptr + 1, null, true);
            if ($next_token !== false && ($tokens[$next_token]['code'] === T_SEMICOLON || $tokens[$next_token]['code'] === T_COMMA)) {
                $stack_ptr = $next_token;
            }
        }
        $line_difference = $end_brace['line'] - $start_brace['line'];
        $expected = sprintf($this->comment_format, $start_condition['content']);
        $comment = $phpcs_file->find_next([T_COMMENT], $stack_ptr, null, false);
        if ($comment === false || $tokens[$comment]['line'] !== $end_brace['line']) {
            if ($line_difference >= $this->line_limit) {
                $error = 'End comment for long condition not found; expected "%s"';
                $data = [$expected];
                $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'Missing', $data);
                if ($fix === true) {
                    $next = $phpcs_file->find_next(T_WHITESPACE, $stack_ptr + 1, null, true);
                    if ($next !== false && $tokens[$next]['line'] === $tokens[$stack_ptr]['line']) {
                        $expected .= $phpcs_file->eol_char;
                    }
                    $phpcs_file->fixer->add_content($stack_ptr, $expected);
                }
            }
            return;
        }
        if ($comment - $stack_ptr !== 1) {
            $error = 'Space found before closing comment; expected "%s"';
            $data = [$expected];
            $phpcs_file->add_error($error, $stack_ptr, 'SpacingBefore', $data);
        }
        if (trim($tokens[$comment]['content']) !== $expected) {
            $found = trim($tokens[$comment]['content']);
            $error = 'Incorrect closing comment; expected "%s" but found "%s"';
            $data = [$expected, $found];
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'Invalid', $data);
            if ($fix === true) {
                $phpcs_file->fixer->replace_token($comment, $expected . $phpcs_file->eol_char);
            }
            return;
        }
    }
    //end process()
}
//end class