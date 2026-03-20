<?php

declare (strict_types=1);
/**
 * Checks that the opening brace of a function is on the same line as the function declaration.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Generic\Sniffs\Functions;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Opening_Function_Brace_Kernighan_Ritchie_Sniff implements Sniff
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
     * @return void
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
        if ($line_difference > 0) {
            $phpcs_file->record_metric($stack_ptr, "{$metric_type} opening brace placement", 'new line');
            $error = 'Opening brace should be on the same line as the declaration';
            $fix = $phpcs_file->add_fixable_error($error, $opening_brace, 'BraceOnNewLine');
            if ($fix === true) {
                $prev = $phpcs_file->find_previous(Tokens::$empty_tokens, $opening_brace - 1, $close_bracket, true);
                $phpcs_file->fixer->begin_changeset();
                $phpcs_file->fixer->add_content($prev, ' {');
                $phpcs_file->fixer->replace_token($opening_brace, '');
                if ($tokens[$opening_brace + 1]['code'] === T_WHITESPACE && $tokens[$opening_brace + 2]['line'] > $tokens[$opening_brace]['line']) {
                    // Brace is followed by a new line, so remove it to ensure we don't
                    // leave behind a blank line at the top of the block.
                    $phpcs_file->fixer->replace_token($opening_brace + 1, '');
                    if ($tokens[$opening_brace - 1]['code'] === T_WHITESPACE && $tokens[$opening_brace - 1]['line'] === $tokens[$opening_brace]['line'] && $tokens[$opening_brace - 2]['line'] < $tokens[$opening_brace]['line']) {
                        // Brace is preceded by indent, so remove it to ensure we don't
                        // leave behind more indent than is required for the first line.
                        $phpcs_file->fixer->replace_token($opening_brace - 1, '');
                    }
                }
                $phpcs_file->fixer->end_changeset();
            }
            //end if
        } else {
            $phpcs_file->record_metric($stack_ptr, "{$metric_type} opening brace placement", 'same line');
        }
        //end if
        $ignore = Tokens::$phpcs_comment_tokens;
        $ignore[] = T_WHITESPACE;
        $next = $phpcs_file->find_next($ignore, $opening_brace + 1, null, true);
        if ($tokens[$next]['line'] === $tokens[$opening_brace]['line']) {
            if ($next === $tokens[$stack_ptr]['scope_closer'] || $tokens[$next]['code'] === T_CLOSE_TAG) {
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
        if ($line_difference > 0) {
            return;
        }
        // We are looking for tabs, even if they have been replaced, because
        // we enforce a space here.
        if (isset($tokens[$opening_brace - 1]['orig_content']) === true) {
            $spacing = $tokens[$opening_brace - 1]['orig_content'];
        } else {
            $spacing = $tokens[$opening_brace - 1]['content'];
        }
        if ($tokens[$opening_brace - 1]['code'] !== T_WHITESPACE) {
            $length = 0;
        } elseif ($spacing === "\t") {
            $length = '\t';
        } else {
            $length = strlen($spacing);
        }
        if ($length !== 1) {
            $error = 'Expected 1 space before opening brace; found %s';
            $data = [$length];
            $fix = $phpcs_file->add_fixable_error($error, $close_bracket, 'SpaceBeforeBrace', $data);
            if ($fix === true) {
                if ($length === 0 || $length === '\t') {
                    $phpcs_file->fixer->add_content_before($opening_brace, ' ');
                } else {
                    $phpcs_file->fixer->replace_token($opening_brace - 1, ' ');
                }
            }
        }
    }
    //end process()
}
//end class