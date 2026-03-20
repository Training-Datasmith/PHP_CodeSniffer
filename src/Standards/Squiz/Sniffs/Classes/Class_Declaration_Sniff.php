<?php

declare (strict_types=1);
/**
 * Checks the declaration of the class and its inheritance is correct.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\Classes;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Standards\PSR2\Sniffs\Classes\Class_Declaration_Sniff as PSR2ClassDeclarationSniff;
use Php_code_Sniffer\Util\Tokens;
class Class_Declaration_Sniff extends Psr2class_Declaration_Sniff
{
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
        // We want all the errors from the PSR2 standard, plus some of our own.
        parent::process($phpcs_file, $stack_ptr);
        // Check that this is the only class or interface in the file.
        $next_class = $phpcs_file->find_next([T_CLASS, T_INTERFACE], $stack_ptr + 1);
        if ($next_class !== false) {
            // We have another, so an error is thrown.
            $error = 'Only one interface or class is allowed in a file';
            $phpcs_file->add_error($error, $next_class, 'MultipleClasses');
        }
    }
    //end process()
    /**
     * Processes the opening section of a class declaration.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token
     *                                               in the stack passed in $tokens.
     *
     * @return void
     */
    public function process_open(File $phpcs_file, $stack_ptr)
    {
        parent::process_open($phpcs_file, $stack_ptr);
        $tokens = $phpcs_file->get_tokens();
        if ($tokens[$stack_ptr - 1]['code'] === T_WHITESPACE) {
            $prev_content = $tokens[$stack_ptr - 1]['content'];
            if ($prev_content !== $phpcs_file->eol_char) {
                $blank_space = substr($prev_content, strpos($prev_content, $phpcs_file->eol_char));
                $spaces = strlen($blank_space);
                if ($tokens[$stack_ptr - 2]['code'] !== T_ABSTRACT && $tokens[$stack_ptr - 2]['code'] !== T_FINAL && $tokens[$stack_ptr - 2]['code'] !== T_READONLY) {
                    if ($spaces !== 0) {
                        $type = strtolower($tokens[$stack_ptr]['content']);
                        $error = 'Expected 0 spaces before %s keyword; %s found';
                        $data = [$type, $spaces];
                        $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'SpaceBeforeKeyword', $data);
                        if ($fix === true) {
                            $phpcs_file->fixer->replace_token($stack_ptr - 1, '');
                        }
                    }
                }
            }
            //end if
        }
        //end if
    }
    //end processOpen()
    /**
     * Processes the closing section of a class declaration.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token
     *                                               in the stack passed in $tokens.
     *
     * @return void
     */
    public function process_close(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        if (isset($tokens[$stack_ptr]['scope_closer']) === false) {
            return;
        }
        $close_brace = $tokens[$stack_ptr]['scope_closer'];
        // Check that the closing brace has one blank line after it.
        for ($next_content = $close_brace + 1; $next_content < $phpcs_file->num_tokens; $next_content++) {
            // Ignore comments on the same line as the brace.
            if ($tokens[$next_content]['line'] === $tokens[$close_brace]['line'] && ($tokens[$next_content]['code'] === T_WHITESPACE || $tokens[$next_content]['code'] === T_COMMENT || isset(Tokens::$phpcs_comment_tokens[$tokens[$next_content]['code']]) === true)) {
                continue;
            }
            if ($tokens[$next_content]['code'] !== T_WHITESPACE) {
                break;
            }
        }
        if ($next_content === $phpcs_file->num_tokens) {
            // Ignore the line check as this is the very end of the file.
            $difference = 1;
        } else {
            $difference = $tokens[$next_content]['line'] - $tokens[$close_brace]['line'] - 1;
        }
        $last_content = $phpcs_file->find_previous(T_WHITESPACE, $close_brace - 1, $stack_ptr, true);
        if ($difference === -1 || $tokens[$last_content]['line'] === $tokens[$close_brace]['line']) {
            $error = 'Closing %s brace must be on a line by itself';
            $data = [$tokens[$stack_ptr]['content']];
            $fix = $phpcs_file->add_fixable_error($error, $close_brace, 'CloseBraceSameLine', $data);
            if ($fix === true) {
                if ($difference === -1) {
                    $phpcs_file->fixer->add_newline_before($next_content);
                }
                if ($tokens[$last_content]['line'] === $tokens[$close_brace]['line']) {
                    $phpcs_file->fixer->add_newline_before($close_brace);
                }
            }
        } elseif ($tokens[$close_brace - 1]['code'] === T_WHITESPACE) {
            $prev_content = $tokens[$close_brace - 1]['content'];
            if ($prev_content !== $phpcs_file->eol_char) {
                $blank_space = substr($prev_content, strpos($prev_content, $phpcs_file->eol_char));
                $spaces = strlen($blank_space);
                if ($spaces !== 0) {
                    if ($tokens[$close_brace - 1]['line'] !== $tokens[$close_brace]['line']) {
                        $error = 'Expected 0 spaces before closing brace; newline found';
                        $phpcs_file->add_error($error, $close_brace, 'NewLineBeforeCloseBrace');
                    } else {
                        $error = 'Expected 0 spaces before closing brace; %s found';
                        $data = [$spaces];
                        $fix = $phpcs_file->add_fixable_error($error, $close_brace, 'SpaceBeforeCloseBrace', $data);
                        if ($fix === true) {
                            $phpcs_file->fixer->replace_token($close_brace - 1, '');
                        }
                    }
                }
            }
        }
        //end if
        if ($difference !== -1 && $difference !== 1) {
            if ($tokens[$next_content]['code'] === T_DOC_COMMENT_OPEN_TAG) {
                $next = $phpcs_file->find_next(T_WHITESPACE, $tokens[$next_content]['comment_closer'] + 1, null, true);
                if ($next !== false && $tokens[$next]['code'] === T_FUNCTION) {
                    return;
                }
            }
            $error = 'Closing brace of a %s must be followed by a single blank line; found %s';
            $data = [$tokens[$stack_ptr]['content'], $difference];
            $fix = $phpcs_file->add_fixable_error($error, $close_brace, 'NewlinesAfterCloseBrace', $data);
            if ($fix === true) {
                if ($difference === 0) {
                    $first = $phpcs_file->find_first_on_line([], $next_content, true);
                    $phpcs_file->fixer->add_newline_before($first);
                } else {
                    $phpcs_file->fixer->begin_changeset();
                    for ($i = $close_brace + 1; $i < $next_content; $i++) {
                        if ($tokens[$i]['line'] <= $tokens[$close_brace]['line'] + 1) {
                            continue;
                        }
                        if ($tokens[$i]['line'] === $tokens[$next_content]['line']) {
                            break;
                        }
                        $phpcs_file->fixer->replace_token($i, '');
                    }
                    $phpcs_file->fixer->end_changeset();
                }
            }
        }
        //end if
    }
    //end processClose()
}
//end class