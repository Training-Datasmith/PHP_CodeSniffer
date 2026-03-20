<?php

declare (strict_types=1);
/**
 * Makes sure there are no spaces around the concatenation operator.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\Strings;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Concatenation_Spacing_Sniff implements Sniff
{
    /**
     * The number of spaces before and after a string concat.
     *
     * @var integer
     */
    public $spacing = 0;
    /**
     * Allow newlines instead of spaces.
     *
     * @var boolean
     */
    public $ignore_newlines = false;
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_STRING_CONCAT];
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
        if (isset($tokens[$stack_ptr + 2]) === false) {
            // Syntax error or live coding, bow out.
            return;
        }
        $ignore_before = false;
        $prev = $phpcs_file->find_previous(Tokens::$empty_tokens, $stack_ptr - 1, null, true);
        if ($tokens[$prev]['code'] === T_END_HEREDOC || $tokens[$prev]['code'] === T_END_NOWDOC) {
            // Spacing before must be preserved due to the here/nowdoc closing tag.
            $ignore_before = true;
        }
        $this->spacing = (int) $this->spacing;
        if ($ignore_before === false) {
            if ($tokens[$stack_ptr - 1]['code'] !== T_WHITESPACE) {
                $before = 0;
            } else if ($tokens[$stack_ptr - 2]['line'] !== $tokens[$stack_ptr]['line']) {
                $before = 'newline';
            } else {
                $before = $tokens[$stack_ptr - 1]['length'];
            }
            $phpcs_file->record_metric($stack_ptr, 'Spacing before string concat', $before);
        }
        if ($tokens[$stack_ptr + 1]['code'] !== T_WHITESPACE) {
            $after = 0;
        } else if ($tokens[$stack_ptr + 2]['line'] !== $tokens[$stack_ptr]['line']) {
            $after = 'newline';
        } else {
            $after = $tokens[$stack_ptr + 1]['length'];
        }
        $phpcs_file->record_metric($stack_ptr, 'Spacing after string concat', $after);
        if (($ignore_before === true || $before === $this->spacing || $before === 'newline' && $this->ignore_newlines === true) && ($after === $this->spacing || $after === 'newline' && $this->ignore_newlines === true)) {
            return;
        }
        if ($this->spacing === 0) {
            $message = 'Concat operator must not be surrounded by spaces';
            $data = [];
        } else {
            if ($this->spacing > 1) {
                $message = 'Concat operator must be surrounded by %s spaces';
            } else {
                $message = 'Concat operator must be surrounded by a single space';
            }
            $data = [$this->spacing];
        }
        $fix = $phpcs_file->add_fixable_error($message, $stack_ptr, 'PaddingFound', $data);
        if ($fix === true) {
            $padding = str_repeat(' ', $this->spacing);
            if ($ignore_before === false && ($before !== 'newline' || $this->ignore_newlines === false)) {
                if ($tokens[$stack_ptr - 1]['code'] === T_WHITESPACE) {
                    $phpcs_file->fixer->begin_changeset();
                    $phpcs_file->fixer->replace_token($stack_ptr - 1, $padding);
                    if ($this->spacing === 0 && ($tokens[$stack_ptr - 2]['code'] === T_LNUMBER || $tokens[$stack_ptr - 2]['code'] === T_DNUMBER)) {
                        $phpcs_file->fixer->replace_token($stack_ptr - 2, '(' . $tokens[$stack_ptr - 2]['content'] . ')');
                    }
                    $phpcs_file->fixer->end_changeset();
                } elseif ($this->spacing > 0) {
                    $phpcs_file->fixer->add_content($stack_ptr - 1, $padding);
                }
            }
            if ($after !== 'newline' || $this->ignore_newlines === false) {
                if ($tokens[$stack_ptr + 1]['code'] === T_WHITESPACE) {
                    $phpcs_file->fixer->begin_changeset();
                    $phpcs_file->fixer->replace_token($stack_ptr + 1, $padding);
                    if ($this->spacing === 0 && ($tokens[$stack_ptr + 2]['code'] === T_LNUMBER || $tokens[$stack_ptr + 2]['code'] === T_DNUMBER)) {
                        $phpcs_file->fixer->replace_token($stack_ptr + 2, '(' . $tokens[$stack_ptr + 2]['content'] . ')');
                    }
                    $phpcs_file->fixer->end_changeset();
                } elseif ($this->spacing > 0) {
                    $phpcs_file->fixer->add_content($stack_ptr, $padding);
                }
            }
        }
        //end if
    }
    //end process()
}
//end class