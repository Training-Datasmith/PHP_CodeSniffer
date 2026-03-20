<?php

declare (strict_types=1);
/**
 * Checks that two strings are not concatenated together; suggests using one string instead.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Generic\Sniffs\Strings;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Unnecessary_String_Concat_Sniff implements Sniff
{
    /**
     * A list of tokenizers this sniff supports.
     *
     * @var array
     */
    public $supported_tokenizers = ['PHP', 'JS'];
    /**
     * If true, an error will be thrown; otherwise a warning.
     *
     * @var boolean
     */
    public $error = true;
    /**
     * If true, strings concatenated over multiple lines are allowed.
     *
     * Useful if you break strings over multiple lines to work
     * within a max line length.
     *
     * @var boolean
     */
    public $allow_multiline = false;
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_STRING_CONCAT, T_PLUS];
    }
    //end register()
    /**
     * Processes this sniff, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token
     *                                               in the stack passed in $tokens.
     *
     * @return void
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        // Work out which type of file this is for.
        $tokens = $phpcs_file->get_tokens();
        if ($tokens[$stack_ptr]['code'] === T_STRING_CONCAT) {
            if ($phpcs_file->tokenizer_type === 'JS') {
                return;
            }
        } else if ($phpcs_file->tokenizer_type === 'PHP') {
            return;
        }
        $prev = $phpcs_file->find_previous(T_WHITESPACE, $stack_ptr - 1, null, true);
        $next = $phpcs_file->find_next(T_WHITESPACE, $stack_ptr + 1, null, true);
        if ($prev === false || $next === false) {
            return;
        }
        if (isset(Tokens::$string_tokens[$tokens[$prev]['code']]) === true && isset(Tokens::$string_tokens[$tokens[$next]['code']]) === true) {
            if ($tokens[$prev]['content'][0] === $tokens[$next]['content'][0]) {
                // Before we throw an error for PHP, allow strings to be
                // combined if they would have < and ? next to each other because
                // this trick is sometimes required in PHP strings.
                if ($phpcs_file->tokenizer_type === 'PHP') {
                    $prev_char = substr($tokens[$prev]['content'], -2, 1);
                    $next_char = $tokens[$next]['content'][1];
                    $combined = $prev_char . $next_char;
                    if ($combined === '?' . '>' || $combined === '<' . '?') {
                        return;
                    }
                }
                if ($this->allow_multiline === true && $tokens[$prev]['line'] !== $tokens[$next]['line']) {
                    return;
                }
                $error = 'String concat is not required here; use a single string instead';
                if ($this->error === true) {
                    $phpcs_file->add_error($error, $stack_ptr, 'Found');
                } else {
                    $phpcs_file->add_warning($error, $stack_ptr, 'Found');
                }
            }
            //end if
        }
        //end if
    }
    //end process()
}
//end class