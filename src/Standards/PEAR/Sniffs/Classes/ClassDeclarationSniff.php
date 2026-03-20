<?php

declare (strict_types=1);
/**
 * Checks the declaration of the class is correct.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\PEAR\Sniffs\Classes;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Class_Declaration_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM];
    }
    //end register()
    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param integer                     $stackPtr  The position of the current token in the
     *                                               stack passed in $tokens.
     *
     * @return void
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        $error_data = [strtolower($tokens[$stack_ptr]['content'])];
        if (isset($tokens[$stack_ptr]['scope_opener']) === false) {
            $error = 'Possible parse error: %s missing opening or closing brace';
            $phpcs_file->add_warning($error, $stack_ptr, 'MissingBrace', $error_data);
            return;
        }
        $curly_brace = $tokens[$stack_ptr]['scope_opener'];
        $last_content = $phpcs_file->find_previous(T_WHITESPACE, $curly_brace - 1, $stack_ptr, true);
        $class_line = $tokens[$last_content]['line'];
        $brace_line = $tokens[$curly_brace]['line'];
        if ($brace_line === $class_line) {
            $phpcs_file->record_metric($stack_ptr, 'Class opening brace placement', 'same line');
            $error = 'Opening brace of a %s must be on the line after the definition';
            $fix = $phpcs_file->add_fixable_error($error, $curly_brace, 'OpenBraceNewLine', $error_data);
            if ($fix === true) {
                $phpcs_file->fixer->begin_changeset();
                if ($tokens[$curly_brace - 1]['code'] === T_WHITESPACE) {
                    $phpcs_file->fixer->replace_token($curly_brace - 1, '');
                }
                $phpcs_file->fixer->add_newline_before($curly_brace);
                $phpcs_file->fixer->end_changeset();
            }
            return;
        }
        $phpcs_file->record_metric($stack_ptr, 'Class opening brace placement', 'new line');
        if ($brace_line > $class_line + 1) {
            $error = 'Opening brace of a %s must be on the line following the %s declaration; found %s line(s)';
            $data = [$tokens[$stack_ptr]['content'], $tokens[$stack_ptr]['content'], $brace_line - $class_line - 1];
            $fix = $phpcs_file->add_fixable_error($error, $curly_brace, 'OpenBraceWrongLine', $data);
            if ($fix === true) {
                $phpcs_file->fixer->begin_changeset();
                for ($i = $curly_brace - 1; $i > $last_content; $i--) {
                    if ($tokens[$i]['line'] === $tokens[$curly_brace]['line'] + 1) {
                        break;
                    }
                    $phpcs_file->fixer->replace_token($i, '');
                }
                $phpcs_file->fixer->end_changeset();
            }
            return;
        }
        //end if
        //end if
        if ($tokens[$curly_brace + 1]['content'] !== $phpcs_file->eol_char) {
            $error = 'Opening %s brace must be on a line by itself';
            $next_non_whitespace = $phpcs_file->find_next(T_WHITESPACE, $curly_brace + 1, null, true);
            if ($tokens[$next_non_whitespace]['code'] === T_PHPCS_IGNORE) {
                // Don't auto-fix if the next thing is a PHPCS ignore annotation.
                $phpcs_file->add_error($error, $curly_brace, 'OpenBraceNotAlone', $error_data);
            } else {
                $fix = $phpcs_file->add_fixable_error($error, $curly_brace, 'OpenBraceNotAlone', $error_data);
                if ($fix === true) {
                    $phpcs_file->fixer->add_newline($curly_brace);
                }
            }
        }
        if ($tokens[$curly_brace - 1]['code'] === T_WHITESPACE) {
            $prev_content = $tokens[$curly_brace - 1]['content'];
            if ($prev_content === $phpcs_file->eol_char) {
                $spaces = 0;
            } else {
                $spaces = $tokens[$curly_brace - 1]['length'];
            }
            $first = $phpcs_file->find_first_on_line(T_WHITESPACE, $stack_ptr, true);
            $expected = $tokens[$first]['column'] - 1;
            if ($spaces !== $expected) {
                $error = 'Expected %s spaces before opening brace; %s found';
                $data = [$expected, $spaces];
                $fix = $phpcs_file->add_fixable_error($error, $curly_brace, 'SpaceBeforeBrace', $data);
                if ($fix === true) {
                    $indent = str_repeat(' ', $expected);
                    if ($spaces === 0) {
                        $phpcs_file->fixer->add_content_before($curly_brace, $indent);
                    } else {
                        $phpcs_file->fixer->replace_token($curly_brace - 1, $indent);
                    }
                }
            }
        }
        //end if
    }
    //end process()
}
//end class