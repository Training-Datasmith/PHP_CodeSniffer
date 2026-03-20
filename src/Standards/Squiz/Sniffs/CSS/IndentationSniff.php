<?php

declare (strict_types=1);
/**
 * Ensures styles are indented 4 spaces.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\CSS;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Indentation_Sniff implements Sniff
{
    /**
     * A list of tokenizers this sniff supports.
     *
     * @var array
     */
    public $supported_tokenizers = ['CSS'];
    /**
     * The number of spaces code should be indented.
     *
     * @var integer
     */
    public $indent = 4;
    /**
     * Returns the token types that this sniff is interested in.
     *
     * @return int[]
     */
    public function register()
    {
        return [T_OPEN_TAG];
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
        $num_tokens = count($tokens) - 2;
        $indent_level = 0;
        $nesting_level = 0;
        for ($i = 1; $i < $num_tokens; $i++) {
            if ($tokens[$i]['code'] === T_COMMENT) {
                // Don't check the indent of comments.
                continue;
            }
            if (isset(Tokens::$phpcs_comment_tokens[$tokens[$i]['code']]) === true) {
                // Don't check the indent of comments.
                continue;
            }
            if ($tokens[$i]['code'] === T_OPEN_CURLY_BRACKET) {
                $indent_level++;
                if (isset($tokens[$i]['bracket_closer']) === false) {
                    // Syntax error or live coding.
                    // Anything after this would receive incorrect fixes, so bow out.
                    return;
                }
                // Check for nested class definitions.
                $found = $phpcs_file->find_next(T_OPEN_CURLY_BRACKET, $i + 1, $tokens[$i]['bracket_closer']);
                if ($found !== false) {
                    $nesting_level = $indent_level;
                }
            }
            if ($tokens[$i]['code'] === T_CLOSE_CURLY_BRACKET && $tokens[$i]['line'] !== $tokens[$i - 1]['line'] || $tokens[$i + 1]['code'] === T_CLOSE_CURLY_BRACKET && $tokens[$i]['line'] === $tokens[$i + 1]['line']) {
                $indent_level--;
                if ($indent_level === 0) {
                    $nesting_level = 0;
                }
            }
            if ($tokens[$i]['column'] !== 1) {
                continue;
            }
            if ($tokens[$i]['code'] === T_OPEN_CURLY_BRACKET) {
                continue;
            }
            if ($tokens[$i]['code'] === T_CLOSE_CURLY_BRACKET) {
                continue;
            }
            // We started a new line, so check indent.
            if ($tokens[$i]['code'] === T_WHITESPACE) {
                $content = str_replace($phpcs_file->eol_char, '', $tokens[$i]['content']);
                $found_indent = strlen($content);
            } else {
                $found_indent = 0;
            }
            $expected_indent = $indent_level * $this->indent;
            if ($expected_indent > 0 && strpos($tokens[$i]['content'], $phpcs_file->eol_char) !== false) {
                if ($nesting_level !== $indent_level) {
                    $error = 'Blank lines are not allowed in class definitions';
                    $fix = $phpcs_file->add_fixable_error($error, $i, 'BlankLine');
                    if ($fix === true) {
                        $phpcs_file->fixer->replace_token($i, '');
                    }
                }
            } elseif ($found_indent !== $expected_indent) {
                $error = 'Line indented incorrectly; expected %s spaces, found %s';
                $data = [$expected_indent, $found_indent];
                $fix = $phpcs_file->add_fixable_error($error, $i, 'Incorrect', $data);
                if ($fix === true) {
                    $indent = str_repeat(' ', $expected_indent);
                    if ($found_indent === 0) {
                        $phpcs_file->fixer->add_content_before($i, $indent);
                    } else {
                        $phpcs_file->fixer->replace_token($i, $indent);
                    }
                }
            }
            //end if
        }
        //end for
    }
    //end process()
}
//end class