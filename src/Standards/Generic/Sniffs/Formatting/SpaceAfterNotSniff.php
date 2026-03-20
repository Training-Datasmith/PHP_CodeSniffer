<?php

declare (strict_types=1);
/**
 * Ensures there is a single space after a NOT operator.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Generic\Sniffs\Formatting;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Space_After_Not_Sniff implements Sniff
{
    /**
     * A list of tokenizers this sniff supports.
     *
     * @var array
     */
    public $supported_tokenizers = ['PHP', 'JS'];
    /**
     * The number of spaces desired after the NOT operator.
     *
     * @var integer
     */
    public $spacing = 1;
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
        return [T_BOOLEAN_NOT];
    }
    //end register()
    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token in
     *                                               the stack passed in $tokens.
     *
     * @return void
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        $this->spacing = (int) $this->spacing;
        $next_non_empty = $phpcs_file->find_next(Tokens::$empty_tokens, $stack_ptr + 1, null, true);
        if ($next_non_empty === false) {
            return;
        }
        if ($this->ignore_newlines === true && $tokens[$stack_ptr]['line'] !== $tokens[$next_non_empty]['line']) {
            return;
        }
        if ($this->spacing === 0 && $next_non_empty === $stack_ptr + 1) {
            return;
        }
        $next_non_whitespace = $phpcs_file->find_next(T_WHITESPACE, $stack_ptr + 1, null, true);
        if ($next_non_empty !== $next_non_whitespace) {
            $error = 'Expected %s space(s) after NOT operator; comment found';
            $data = [$this->spacing];
            $phpcs_file->add_error($error, $stack_ptr, 'CommentFound', $data);
            return;
        }
        $found = 0;
        if ($tokens[$stack_ptr]['line'] !== $tokens[$next_non_empty]['line']) {
            $found = 'newline';
        } elseif ($tokens[$stack_ptr + 1]['code'] === T_WHITESPACE) {
            $found = $tokens[$stack_ptr + 1]['length'];
        }
        if ($found === $this->spacing) {
            return;
        }
        $error = 'Expected %s space(s) after NOT operator; %s found';
        $data = [$this->spacing, $found];
        $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'Incorrect', $data);
        if ($fix === true) {
            $padding = str_repeat(' ', $this->spacing);
            if ($found === 0) {
                $phpcs_file->fixer->add_content($stack_ptr, $padding);
            } else {
                $phpcs_file->fixer->begin_changeset();
                $start = $stack_ptr + 1;
                if ($this->spacing > 0) {
                    $phpcs_file->fixer->replace_token($start, $padding);
                    ++$start;
                }
                for ($i = $start; $i < $next_non_whitespace; $i++) {
                    $phpcs_file->fixer->replace_token($i, '');
                }
                $phpcs_file->fixer->end_changeset();
            }
        }
    }
    //end process()
}
//end class