<?php

declare (strict_types=1);
/**
 * Ensure there are no blank lines between the names of classes/IDs.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\CSS;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Class_Definition_Name_Spacing_Sniff implements Sniff
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
        if (isset($tokens[$stack_ptr]['bracket_closer']) === false) {
            // Syntax error or live coding, bow out.
            return;
        }
        // Do not check nested style definitions as, for example, in @media style rules.
        $nested = $phpcs_file->find_next(T_OPEN_CURLY_BRACKET, $stack_ptr + 1, $tokens[$stack_ptr]['bracket_closer']);
        if ($nested !== false) {
            return;
        }
        // Find the first blank line before this opening brace, unless we get
        // to another style definition, comment or the start of the file.
        $end_tokens = [T_OPEN_CURLY_BRACKET => T_OPEN_CURLY_BRACKET, T_CLOSE_CURLY_BRACKET => T_CLOSE_CURLY_BRACKET, T_OPEN_TAG => T_OPEN_TAG];
        $end_tokens += Tokens::$comment_tokens;
        $prev = $phpcs_file->find_previous(Tokens::$empty_tokens, $stack_ptr - 1, null, true);
        $found_content = false;
        $current_line = $tokens[$prev]['line'];
        for ($i = $stack_ptr - 1; $i >= 0; $i--) {
            if (isset($end_tokens[$tokens[$i]['code']]) === true) {
                break;
            }
            if ($tokens[$i]['line'] === $current_line) {
                if ($tokens[$i]['code'] !== T_WHITESPACE) {
                    $found_content = true;
                }
                continue;
            }
            // We changed lines.
            if ($found_content === false) {
                // Before we throw an error, make sure we are not looking
                // at a gap before the style definition.
                $prev = $phpcs_file->find_previous(T_WHITESPACE, $i, null, true);
                if ($prev !== false && isset($end_tokens[$tokens[$prev]['code']]) === false) {
                    $error = 'Blank lines are not allowed between class names';
                    $phpcs_file->add_error($error, $i + 1, 'BlankLinesFound');
                }
                break;
            }
            $found_content = false;
            $current_line = $tokens[$i]['line'];
        }
        //end for
    }
    //end process()
}
//end class