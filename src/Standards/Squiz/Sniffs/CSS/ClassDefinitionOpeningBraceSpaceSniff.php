<?php

declare (strict_types=1);
/**
 * Ensure a single space before, and a newline after, the class opening brace
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\CSS;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Class_Definition_Opening_Brace_Space_Sniff implements Sniff
{
    /**
     * A list of tokenizers this sniff supports.
     *
     * @var array
     */
    public $supported_tokenizers = ['CSS'];
    /**
     * Returns the token types that this sniff is interested in.
     *
     * @return int[]
     */
    public function register()
    {
        return [T_OPEN_CURLY_BRACKET];
    }
    //end register()
    /**
     * Processes the tokens that this sniff is interested in.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file where the token was found.
     * @param int                         $stackPtr  The position in the stack where
     *                                               the token was found.
     *
     * @return void
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        $prev_non_whitespace = $phpcs_file->find_previous(T_WHITESPACE, $stack_ptr - 1, null, true);
        if ($prev_non_whitespace !== false) {
            $length = 0;
            if ($tokens[$stack_ptr]['line'] !== $tokens[$prev_non_whitespace]['line']) {
                $length = 'newline';
            } elseif ($tokens[$stack_ptr - 1]['code'] === T_WHITESPACE) {
                if (strpos($tokens[$stack_ptr - 1]['content'], "\t") !== false) {
                    $length = 'tab';
                } else {
                    $length = $tokens[$stack_ptr - 1]['length'];
                }
            }
            if ($length === 0) {
                $error = 'Expected 1 space before opening brace of class definition; 0 found';
                $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'NoneBefore');
                if ($fix === true) {
                    $phpcs_file->fixer->add_content_before($stack_ptr, ' ');
                }
            } elseif ($length !== 1) {
                $error = 'Expected 1 space before opening brace of class definition; %s found';
                $data = [$length];
                $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'Before', $data);
                if ($fix === true) {
                    $phpcs_file->fixer->begin_changeset();
                    for ($i = $stack_ptr - 1; $i > $prev_non_whitespace; $i--) {
                        $phpcs_file->fixer->replace_token($i, '');
                    }
                    $phpcs_file->fixer->add_content_before($stack_ptr, ' ');
                    $phpcs_file->fixer->end_changeset();
                }
            }
            //end if
        }
        //end if
        $next_non_empty = $phpcs_file->find_next(Tokens::$empty_tokens, $stack_ptr + 1, null, true);
        if ($next_non_empty === false) {
            return;
        }
        if ($tokens[$next_non_empty]['line'] === $tokens[$stack_ptr]['line']) {
            $error = 'Opening brace should be the last content on the line';
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'ContentBefore');
            if ($fix === true) {
                $phpcs_file->fixer->begin_changeset();
                $phpcs_file->fixer->add_newline($stack_ptr);
                // Remove potentially left over trailing whitespace.
                if ($tokens[$stack_ptr + 1]['code'] === T_WHITESPACE) {
                    $phpcs_file->fixer->replace_token($stack_ptr + 1, '');
                }
                $phpcs_file->fixer->end_changeset();
            }
        } else {
            if (isset($tokens[$stack_ptr]['bracket_closer']) === false) {
                // Syntax error or live coding, bow out.
                return;
            }
            // Check for nested class definitions.
            $found = $phpcs_file->find_next(T_OPEN_CURLY_BRACKET, $stack_ptr + 1, $tokens[$stack_ptr]['bracket_closer']);
            if ($found === false) {
                // Not nested.
                return;
            }
            $last_on_line = $stack_ptr;
            for ($last_on_line; $last_on_line < $tokens[$stack_ptr]['bracket_closer']; $last_on_line++) {
                if ($tokens[$last_on_line]['line'] !== $tokens[$last_on_line + 1]['line']) {
                    break;
                }
            }
            $next_non_white_space = $phpcs_file->find_next(T_WHITESPACE, $last_on_line + 1, null, true);
            if ($next_non_white_space === false) {
                return;
            }
            $found_lines = $tokens[$next_non_white_space]['line'] - $tokens[$stack_ptr]['line'] - 1;
            if ($found_lines !== 1) {
                $error = 'Expected 1 blank line after opening brace of nesting class definition; %s found';
                $data = [max(0, $found_lines)];
                $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'AfterNesting', $data);
                if ($fix === true) {
                    $first_on_next_line = $next_non_white_space;
                    while ($tokens[$first_on_next_line]['column'] !== 1) {
                        --$first_on_next_line;
                    }
                    if ($found < 0) {
                        // First statement on same line as the opening brace.
                        $phpcs_file->fixer->add_content_before($next_non_white_space, $phpcs_file->eol_char . $phpcs_file->eol_char);
                    } elseif ($found === 0) {
                        // Next statement on next line, no blank line.
                        $phpcs_file->fixer->add_newline_before($first_on_next_line);
                    } else {
                        // Too many blank lines.
                        $phpcs_file->fixer->begin_changeset();
                        for ($i = $first_on_next_line - 1; $i > $stack_ptr; $i--) {
                            if ($tokens[$i]['code'] !== T_WHITESPACE) {
                                break;
                            }
                            $phpcs_file->fixer->replace_token($i, '');
                        }
                        $phpcs_file->fixer->add_content_before($first_on_next_line, $phpcs_file->eol_char . $phpcs_file->eol_char);
                        $phpcs_file->fixer->end_changeset();
                    }
                }
                //end if
            }
            //end if
        }
        //end if
    }
    //end process()
}
//end class