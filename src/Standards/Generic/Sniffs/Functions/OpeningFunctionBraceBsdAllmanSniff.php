<?php

declare (strict_types=1);
/**
 * Checks that the opening brace of a function is on the line after the function declaration.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Generic\Sniffs\Functions;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Opening_Function_Brace_Bsd_Allman_Sniff implements Sniff
{
    /**
     * Should this sniff check function braces?
     *
     * @var boolean
     */
    public $check_functions = true;
    /**
     * Should this sniff check closure braces?
     *
     * @var boolean
     */
    public $check_closures = false;
    /**
     * Registers the tokens that this sniff wants to listen for.
     *
     * @return int[]
     */
    public function register()
    {
        return [T_FUNCTION, T_CLOSURE];
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
        if (isset($tokens[$stack_ptr]['scope_opener']) === false) {
            return;
        }
        if ($tokens[$stack_ptr]['code'] === T_FUNCTION && (bool) $this->check_functions === false || $tokens[$stack_ptr]['code'] === T_CLOSURE && (bool) $this->check_closures === false) {
            return;
        }
        $opening_brace = $tokens[$stack_ptr]['scope_opener'];
        $close_bracket = $tokens[$stack_ptr]['parenthesis_closer'];
        if ($tokens[$stack_ptr]['code'] === T_CLOSURE) {
            $use = $phpcs_file->find_next(T_USE, $close_bracket + 1, $tokens[$stack_ptr]['scope_opener']);
            if ($use !== false) {
                $open_bracket = $phpcs_file->find_next(T_OPEN_PARENTHESIS, $use + 1);
                $close_bracket = $tokens[$open_bracket]['parenthesis_closer'];
            }
        }
        // Find the end of the function declaration.
        $prev = $phpcs_file->find_previous(Tokens::$empty_tokens, $opening_brace - 1, $close_bracket, true);
        $function_line = $tokens[$prev]['line'];
        $brace_line = $tokens[$opening_brace]['line'];
        $line_difference = $brace_line - $function_line;
        $metric_type = 'Function';
        if ($tokens[$stack_ptr]['code'] === T_CLOSURE) {
            $metric_type = 'Closure';
        }
        if ($line_difference === 0) {
            $error = 'Opening brace should be on a new line';
            $fix = $phpcs_file->add_fixable_error($error, $opening_brace, 'BraceOnSameLine');
            if ($fix === true) {
                $has_trailing_annotation = false;
                for ($next_line = $opening_brace + 1; $next_line < $phpcs_file->num_tokens; $next_line++) {
                    if ($tokens[$opening_brace]['line'] !== $tokens[$next_line]['line']) {
                        break;
                    }
                    if (isset(Tokens::$phpcs_comment_tokens[$tokens[$next_line]['code']]) === true) {
                        $has_trailing_annotation = true;
                    }
                }
                $phpcs_file->fixer->begin_changeset();
                $indent = $phpcs_file->find_first_on_line([], $opening_brace);
                if ($has_trailing_annotation === false || $next_line === false) {
                    if ($tokens[$indent]['code'] === T_WHITESPACE) {
                        $phpcs_file->fixer->add_content_before($opening_brace, $tokens[$indent]['content']);
                    }
                    if ($tokens[$opening_brace - 1]['code'] === T_WHITESPACE) {
                        $phpcs_file->fixer->replace_token($opening_brace - 1, '');
                    }
                    $phpcs_file->fixer->add_newline_before($opening_brace);
                } else {
                    $phpcs_file->fixer->replace_token($opening_brace, '');
                    $phpcs_file->fixer->add_newline_before($next_line);
                    $phpcs_file->fixer->add_content_before($next_line, '{');
                    if ($tokens[$indent]['code'] === T_WHITESPACE) {
                        $phpcs_file->fixer->add_content_before($next_line, $tokens[$indent]['content']);
                    }
                }
                $phpcs_file->fixer->end_changeset();
            }
            //end if
            $phpcs_file->record_metric($stack_ptr, "{$metric_type} opening brace placement", 'same line');
        } elseif ($line_difference > 1) {
            $error = 'Opening brace should be on the line after the declaration; found %s blank line(s)';
            $data = [$line_difference - 1];
            $prev_non_ws = $phpcs_file->find_previous(T_WHITESPACE, $opening_brace - 1, $close_bracket, true);
            if ($prev_non_ws !== $prev) {
                // There must be a comment between the end of the function declaration and the open brace.
                // Report, but don't fix.
                $phpcs_file->add_error($error, $opening_brace, 'BraceSpacing', $data);
            } else {
                $fix = $phpcs_file->add_fixable_error($error, $opening_brace, 'BraceSpacing', $data);
                if ($fix === true) {
                    $phpcs_file->fixer->begin_changeset();
                    for ($i = $opening_brace; $i > $prev; $i--) {
                        if ($tokens[$i]['line'] === $tokens[$opening_brace]['line']) {
                            if ($tokens[$i]['column'] === 1) {
                                $phpcs_file->fixer->add_new_line_before($i);
                            }
                            continue;
                        }
                        $phpcs_file->fixer->replace_token($i, '');
                    }
                    $phpcs_file->fixer->end_changeset();
                }
            }
            //end if
        }
        //end if
        $ignore = Tokens::$phpcs_comment_tokens;
        $ignore[] = T_WHITESPACE;
        $next = $phpcs_file->find_next($ignore, $opening_brace + 1, null, true);
        if ($tokens[$next]['line'] === $tokens[$opening_brace]['line']) {
            if ($next === $tokens[$stack_ptr]['scope_closer']) {
                // Ignore empty functions.
                return;
            }
            $error = 'Opening brace must be the last content on the line';
            $fix = $phpcs_file->add_fixable_error($error, $opening_brace, 'ContentAfterBrace');
            if ($fix === true) {
                $phpcs_file->fixer->add_newline($opening_brace);
            }
        }
        // Only continue checking if the opening brace looks good.
        if ($line_difference !== 1) {
            return;
        }
        // We need to actually find the first piece of content on this line,
        // as if this is a method with tokens before it (public, static etc)
        // or an if with an else before it, then we need to start the scope
        // checking from there, rather than the current token.
        $line_start = $phpcs_file->find_first_on_line(T_WHITESPACE, $stack_ptr, true);
        // The opening brace is on the correct line, now it needs to be
        // checked to be correctly indented.
        $start_column = $tokens[$line_start]['column'];
        $brace_indent = $tokens[$opening_brace]['column'];
        if ($brace_indent !== $start_column) {
            $expected = $start_column - 1;
            $found = $brace_indent - 1;
            $error = 'Opening brace indented incorrectly; expected %s spaces, found %s';
            $data = [$expected, $found];
            $fix = $phpcs_file->add_fixable_error($error, $opening_brace, 'BraceIndent', $data);
            if ($fix === true) {
                $indent = str_repeat(' ', $expected);
                if ($found === 0) {
                    $phpcs_file->fixer->add_content_before($opening_brace, $indent);
                } else {
                    $phpcs_file->fixer->replace_token($opening_brace - 1, $indent);
                }
            }
        }
        //end if
        $phpcs_file->record_metric($stack_ptr, "{$metric_type} opening brace placement", 'new line');
    }
    //end process()
}
//end class