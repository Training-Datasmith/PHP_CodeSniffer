<?php

declare (strict_types=1);
/**
 * Ensure each style definition has a semi-colon and it is spaced correctly.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\CSS;

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
    public $supported_tokenizers = ['CSS'];
    /**
     * Returns the token types that this sniff is interested in.
     *
     * @return int[]
     */
    public function register()
    {
        return [T_STYLE];
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
        $next_statement = $phpcs_file->find_next([T_STYLE, T_CLOSE_CURLY_BRACKET], $stack_ptr + 1);
        if ($next_statement === false) {
            return;
        }
        $ignore = Tokens::$empty_tokens;
        if ($tokens[$next_statement]['code'] === T_STYLE) {
            // Allow for star-prefix hack.
            $ignore[] = T_MULTIPLY;
        }
        $end_of_this_statement = $phpcs_file->find_previous($ignore, $next_statement - 1, null, true);
        if ($tokens[$end_of_this_statement]['code'] !== T_SEMICOLON) {
            $error = 'Style definitions must end with a semicolon';
            $phpcs_file->add_error($error, $end_of_this_statement, 'NotAtEnd');
            return;
        }
        if ($tokens[$end_of_this_statement - 1]['code'] !== T_WHITESPACE) {
            return;
        }
        // There is a semi-colon, so now find the last token in the statement.
        $prev_non_empty = $phpcs_file->find_previous(Tokens::$empty_tokens, $end_of_this_statement - 1, null, true);
        $found = $tokens[$end_of_this_statement - 1]['length'];
        if ($tokens[$prev_non_empty]['line'] !== $tokens[$end_of_this_statement]['line']) {
            $found = 'newline';
        }
        $error = 'Expected 0 spaces before semicolon in style definition; %s found';
        $data = [$found];
        $fix = $phpcs_file->add_fixable_error($error, $prev_non_empty, 'SpaceFound', $data);
        if ($fix === true) {
            $phpcs_file->fixer->begin_changeset();
            $phpcs_file->fixer->add_content($prev_non_empty, ';');
            $phpcs_file->fixer->replace_token($end_of_this_statement, '');
            for ($i = $end_of_this_statement - 1; $i > $prev_non_empty; $i--) {
                if ($tokens[$i]['code'] !== T_WHITESPACE) {
                    break;
                }
                $phpcs_file->fixer->replace_token($i, '');
            }
            $phpcs_file->fixer->end_changeset();
        }
    }
    //end process()
}
//end class