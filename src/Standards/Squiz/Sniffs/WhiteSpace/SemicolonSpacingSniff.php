<?php

declare (strict_types=1);
/**
 * Ensure there is no whitespace before a semicolon.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\White_Space;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Semicolon_Spacing_Sniff implements Sniff
{
    /**
     * A list of tokenizers this sniff supports.
     *
     * @var array
     */
    public $supported_tokenizers = ['PHP', 'JS'];
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_SEMICOLON];
    }
    //end register()
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
        $tokens = $phpcs_file->get_tokens();
        $prev_type = $tokens[$stack_ptr - 1]['code'];
        if (isset(Tokens::$empty_tokens[$prev_type]) === false) {
            return;
        }
        $non_space = $phpcs_file->find_previous(Tokens::$empty_tokens, $stack_ptr - 2, null, true);
        // Detect whether this is a semi-colon for a condition in a `for()` control structure.
        $for_condition = false;
        if (isset($tokens[$stack_ptr]['nested_parenthesis']) === true) {
            $nested_parens = $tokens[$stack_ptr]['nested_parenthesis'];
            $close_parenthesis = end($nested_parens);
            if (isset($tokens[$close_parenthesis]['parenthesis_owner']) === true) {
                $owner = $tokens[$close_parenthesis]['parenthesis_owner'];
                if ($tokens[$owner]['code'] === T_FOR) {
                    $for_condition = true;
                    $non_space = $phpcs_file->find_previous(T_WHITESPACE, $stack_ptr - 2, null, true);
                }
            }
        }
        if ($tokens[$non_space]['code'] === T_SEMICOLON || $for_condition === true && $non_space === $tokens[$owner]['parenthesis_opener'] || isset($tokens[$non_space]['scope_opener']) === true && $tokens[$non_space]['scope_opener'] === $non_space) {
            // Empty statement.
            return;
        }
        $expected = $tokens[$non_space]['content'] . ';';
        $found = $phpcs_file->get_tokens_as_string($non_space, $stack_ptr - $non_space) . ';';
        $found = str_replace("\n", '\n', $found);
        $found = str_replace("\r", '\r', $found);
        $found = str_replace("\t", '\t', $found);
        $error = 'Space found before semicolon; expected "%s" but found "%s"';
        $data = [$expected, $found];
        $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'Incorrect', $data);
        if ($fix === true) {
            $phpcs_file->fixer->begin_changeset();
            $i = $stack_ptr - 1;
            while ($tokens[$i]['code'] === T_WHITESPACE && $i > $non_space) {
                $phpcs_file->fixer->replace_token($i, '');
                $i--;
            }
            $phpcs_file->fixer->add_content($non_space, ';');
            $phpcs_file->fixer->replace_token($stack_ptr, '');
            $phpcs_file->fixer->end_changeset();
        }
    }
    //end process()
}
//end class