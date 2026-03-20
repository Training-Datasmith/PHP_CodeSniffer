<?php

declare (strict_types=1);
/**
 * Ensure there is a single blank line after the closing brace of a class definition.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\CSS;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Class_Definition_Closing_Brace_Space_Sniff implements Sniff
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
        return [T_CLOSE_CURLY_BRACKET];
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
        $next = $stack_ptr;
        while (true) {
            $next = $phpcs_file->find_next(T_WHITESPACE, $next + 1, null, true);
            if ($next === false) {
                return;
            }
            if (isset(Tokens::$empty_tokens[$tokens[$next]['code']]) === true && $tokens[$next]['line'] === $tokens[$stack_ptr]['line']) {
                // Trailing comment.
                continue;
            }
            break;
        }
        if ($tokens[$next]['code'] !== T_CLOSE_TAG) {
            $found = $tokens[$next]['line'] - $tokens[$stack_ptr]['line'] - 1;
            if ($found !== 1) {
                $error = 'Expected one blank line after closing brace of class definition; %s found';
                $data = [max(0, $found)];
                $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'SpacingAfterClose', $data);
                if ($fix === true) {
                    $first_on_line = $next;
                    while ($tokens[$first_on_line]['column'] !== 1) {
                        --$first_on_line;
                    }
                    if ($found < 0) {
                        // Next statement on same line as the closing brace.
                        $phpcs_file->fixer->add_content_before($next, $phpcs_file->eol_char . $phpcs_file->eol_char);
                    } elseif ($found === 0) {
                        // Next statement on next line, no blank line.
                        $phpcs_file->fixer->add_content_before($first_on_line, $phpcs_file->eol_char);
                    } else {
                        // Too many blank lines.
                        $phpcs_file->fixer->begin_changeset();
                        for ($i = $first_on_line - 1; $i > $stack_ptr; $i--) {
                            if ($tokens[$i]['code'] !== T_WHITESPACE) {
                                break;
                            }
                            $phpcs_file->fixer->replace_token($i, '');
                        }
                        $phpcs_file->fixer->add_content_before($first_on_line, $phpcs_file->eol_char . $phpcs_file->eol_char);
                        $phpcs_file->fixer->end_changeset();
                    }
                }
                //end if
            }
            //end if
        }
        //end if
        // Ignore nested style definitions from here on. The spacing before the closing brace
        // (a single blank line) will be enforced by the above check, which ensures there is a
        // blank line after the last nested class.
        $found = $phpcs_file->find_previous(T_CLOSE_CURLY_BRACKET, $stack_ptr - 1, $tokens[$stack_ptr]['bracket_opener']);
        if ($found !== false) {
            return;
        }
        $prev = $phpcs_file->find_previous(Tokens::$empty_tokens, $stack_ptr - 1, null, true);
        if ($prev === false) {
            return;
        }
        if ($tokens[$prev]['line'] === $tokens[$stack_ptr]['line']) {
            $error = 'Closing brace of class definition must be on new line';
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'ContentBeforeClose');
            if ($fix === true) {
                $phpcs_file->fixer->add_newline_before($stack_ptr);
            }
        }
    }
    //end process()
}
//end class