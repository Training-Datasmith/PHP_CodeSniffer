<?php

declare (strict_types=1);
/**
 * Checks against empty PHP statements.
 *
 * - Check against two semi-colons with no executable code in between.
 * - Check against an empty PHP open - close tag combination.
 *
 * @author    Juliette Reinders Folmer <phpcs_nospam@adviesenzo.nl>
 * @copyright 2017 Juliette Reinders Folmer. All rights reserved.
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Generic\Sniffs\Code_Analysis;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Empty_Php_Statement_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return int[]
     */
    public function register()
    {
        return [T_SEMICOLON, T_CLOSE_TAG];
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
        switch ($tokens[$stack_ptr]['type']) {
            // Detect `something();;`.
            case 'T_SEMICOLON':
                $prev_non_empty = $phpcs_file->find_previous(Tokens::$empty_tokens, $stack_ptr - 1, null, true);
                if ($prev_non_empty === false) {
                    return;
                }
                if ($tokens[$prev_non_empty]['code'] !== T_SEMICOLON && $tokens[$prev_non_empty]['code'] !== T_OPEN_TAG && $tokens[$prev_non_empty]['code'] !== T_OPEN_TAG_WITH_ECHO) {
                    if (isset($tokens[$prev_non_empty]['scope_condition']) === false) {
                        return;
                    }
                    if ($tokens[$prev_non_empty]['scope_opener'] !== $prev_non_empty && $tokens[$prev_non_empty]['code'] !== T_CLOSE_CURLY_BRACKET) {
                        return;
                    }
                    $scope_owner = $tokens[$tokens[$prev_non_empty]['scope_condition']]['code'];
                    if ($scope_owner === T_CLOSURE || $scope_owner === T_ANON_CLASS || $scope_owner === T_MATCH) {
                        return;
                    }
                    // Else, it's something like `if (foo) {};` and the semi-colon is not needed.
                }
                if (isset($tokens[$stack_ptr]['nested_parenthesis']) === true) {
                    $nested = $tokens[$stack_ptr]['nested_parenthesis'];
                    $last_closer = array_pop($nested);
                    if (isset($tokens[$last_closer]['parenthesis_owner']) === true && $tokens[$tokens[$last_closer]['parenthesis_owner']]['code'] === T_FOR) {
                        // Empty for() condition.
                        return;
                    }
                }
                $fix = $phpcs_file->add_fixable_warning('Empty PHP statement detected: superfluous semi-colon.', $stack_ptr, 'SemicolonWithoutCodeDetected');
                if ($fix === true) {
                    $phpcs_file->fixer->begin_changeset();
                    if ($tokens[$prev_non_empty]['code'] === T_OPEN_TAG || $tokens[$prev_non_empty]['code'] === T_OPEN_TAG_WITH_ECHO) {
                        // Check for superfluous whitespace after the semi-colon which will be
                        // removed as the `<?php ` open tag token already contains whitespace,
                        // either a space or a new line.
                        if ($tokens[$stack_ptr + 1]['code'] === T_WHITESPACE) {
                            $replacement = str_replace(' ', '', $tokens[$stack_ptr + 1]['content']);
                            $phpcs_file->fixer->replace_token($stack_ptr + 1, $replacement);
                        }
                    }
                    for ($i = $stack_ptr; $i > $prev_non_empty; $i--) {
                        if ($tokens[$i]['code'] !== T_SEMICOLON && $tokens[$i]['code'] !== T_WHITESPACE) {
                            break;
                        }
                        $phpcs_file->fixer->replace_token($i, '');
                    }
                    $phpcs_file->fixer->end_changeset();
                }
                //end if
                break;
            // Detect `<?php ? >`.
            case 'T_CLOSE_TAG':
                $prev_non_empty = $phpcs_file->find_previous(T_WHITESPACE, $stack_ptr - 1, null, true);
                if ($prev_non_empty === false || $tokens[$prev_non_empty]['code'] !== T_OPEN_TAG && $tokens[$prev_non_empty]['code'] !== T_OPEN_TAG_WITH_ECHO) {
                    return;
                }
                $fix = $phpcs_file->add_fixable_warning('Empty PHP open/close tag combination detected.', $prev_non_empty, 'EmptyPHPOpenCloseTagsDetected');
                if ($fix === true) {
                    $phpcs_file->fixer->begin_changeset();
                    for ($i = $prev_non_empty; $i <= $stack_ptr; $i++) {
                        $phpcs_file->fixer->replace_token($i, '');
                    }
                    $phpcs_file->fixer->end_changeset();
                }
                break;
            default:
                // Deliberately left empty.
                break;
        }
        //end switch
    }
    //end process()
}
//end class