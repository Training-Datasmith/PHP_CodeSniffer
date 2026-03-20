<?php

declare (strict_types=1);
/**
 * Checks that control structures have the correct spacing around brackets.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\White_Space;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Control_Structure_Spacing_Sniff implements Sniff
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
        return [T_IF, T_WHILE, T_FOREACH, T_FOR, T_SWITCH, T_DO, T_ELSE, T_ELSEIF, T_TRY, T_CATCH, T_FINALLY, T_MATCH];
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
        if (isset($tokens[$stack_ptr]['parenthesis_opener']) === true && isset($tokens[$stack_ptr]['parenthesis_closer']) === true) {
            $paren_opener = $tokens[$stack_ptr]['parenthesis_opener'];
            $paren_closer = $tokens[$stack_ptr]['parenthesis_closer'];
            if ($tokens[$paren_opener + 1]['code'] === T_WHITESPACE) {
                $gap = $tokens[$paren_opener + 1]['length'];
                if ($gap === 0) {
                    $phpcs_file->record_metric($stack_ptr, 'Spaces after control structure open parenthesis', 'newline');
                    $gap = 'newline';
                } else {
                    $phpcs_file->record_metric($stack_ptr, 'Spaces after control structure open parenthesis', $gap);
                }
                $error = 'Expected 0 spaces after opening bracket; %s found';
                $data = [$gap];
                $fix = $phpcs_file->add_fixable_error($error, $paren_opener + 1, 'SpacingAfterOpenBrace', $data);
                if ($fix === true) {
                    $phpcs_file->fixer->replace_token($paren_opener + 1, '');
                }
            } else {
                $phpcs_file->record_metric($stack_ptr, 'Spaces after control structure open parenthesis', 0);
            }
            if ($tokens[$paren_opener]['line'] === $tokens[$paren_closer]['line'] && $tokens[$paren_closer - 1]['code'] === T_WHITESPACE) {
                $gap = $tokens[$paren_closer - 1]['length'];
                $error = 'Expected 0 spaces before closing bracket; %s found';
                $data = [$gap];
                $fix = $phpcs_file->add_fixable_error($error, $paren_closer - 1, 'SpaceBeforeCloseBrace', $data);
                if ($fix === true) {
                    $phpcs_file->fixer->replace_token($paren_closer - 1, '');
                }
                if ($gap === 0) {
                    $phpcs_file->record_metric($stack_ptr, 'Spaces before control structure close parenthesis', 'newline');
                } else {
                    $phpcs_file->record_metric($stack_ptr, 'Spaces before control structure close parenthesis', $gap);
                }
            } else {
                $phpcs_file->record_metric($stack_ptr, 'Spaces before control structure close parenthesis', 0);
            }
        }
        //end if
        if (isset($tokens[$stack_ptr]['scope_closer']) === false) {
            return;
        }
        $scope_opener = $tokens[$stack_ptr]['scope_opener'];
        $scope_closer = $tokens[$stack_ptr]['scope_closer'];
        for ($first_content = $scope_opener + 1; $first_content < $phpcs_file->num_tokens; $first_content++) {
            $code = $tokens[$first_content]['code'];
            if ($code === T_WHITESPACE) {
                continue;
            }
            if ($code === T_INLINE_HTML && trim($tokens[$first_content]['content']) === '') {
                continue;
            }
            // Skip all empty tokens on the same line as the opener.
            if ($tokens[$first_content]['line'] === $tokens[$scope_opener]['line'] && (isset(Tokens::$empty_tokens[$code]) === true || $code === T_CLOSE_TAG)) {
                continue;
            }
            break;
        }
        // We ignore spacing for some structures that tend to have their own rules.
        $ignore = [T_FUNCTION => true, T_CLASS => true, T_INTERFACE => true, T_TRAIT => true, T_ENUM => true, T_DOC_COMMENT_OPEN_TAG => true];
        if (isset($ignore[$tokens[$first_content]['code']]) === false && $tokens[$first_content]['line'] >= $tokens[$scope_opener]['line'] + 2) {
            $gap = $tokens[$first_content]['line'] - $tokens[$scope_opener]['line'] - 1;
            $phpcs_file->record_metric($stack_ptr, 'Blank lines at start of control structure', $gap);
            $error = 'Blank line found at start of control structure';
            $fix = $phpcs_file->add_fixable_error($error, $scope_opener, 'SpacingAfterOpen');
            if ($fix === true) {
                $phpcs_file->fixer->begin_changeset();
                $i = $scope_opener + 1;
                while ($tokens[$i]['line'] !== $tokens[$first_content]['line']) {
                    // Start removing content from the line after the opener.
                    if ($tokens[$i]['line'] !== $tokens[$scope_opener]['line']) {
                        $phpcs_file->fixer->replace_token($i, '');
                    }
                    $i++;
                }
                $phpcs_file->fixer->end_changeset();
            }
        } else {
            $phpcs_file->record_metric($stack_ptr, 'Blank lines at start of control structure', 0);
        }
        //end if
        if ($first_content !== $scope_closer) {
            $last_content = $phpcs_file->find_previous(T_WHITESPACE, $scope_closer - 1, null, true);
            $last_non_empty_content = $phpcs_file->find_previous(Tokens::$empty_tokens, $scope_closer - 1, null, true);
            $check_token = $last_content;
            if (isset($tokens[$last_non_empty_content]['scope_condition']) === true) {
                $check_token = $tokens[$last_non_empty_content]['scope_condition'];
            }
            if (isset($ignore[$tokens[$check_token]['code']]) === false && $tokens[$last_content]['line'] <= $tokens[$scope_closer]['line'] - 2) {
                $error_token = $scope_closer;
                for ($i = $scope_closer - 1; $i > $last_content; $i--) {
                    if ($tokens[$i]['line'] < $tokens[$scope_closer]['line']) {
                        $error_token = $i;
                        break;
                    }
                }
                $gap = $tokens[$scope_closer]['line'] - $tokens[$last_content]['line'] - 1;
                $phpcs_file->record_metric($stack_ptr, 'Blank lines at end of control structure', $gap);
                $error = 'Blank line found at end of control structure';
                $fix = $phpcs_file->add_fixable_error($error, $error_token, 'SpacingBeforeClose');
                if ($fix === true) {
                    $phpcs_file->fixer->begin_changeset();
                    for ($i = $scope_closer - 1; $i > $last_content; $i--) {
                        if ($tokens[$i]['line'] === $tokens[$scope_closer]['line']) {
                            continue;
                        }
                        if ($tokens[$i]['line'] === $tokens[$last_content]['line']) {
                            break;
                        }
                        $phpcs_file->fixer->replace_token($i, '');
                    }
                    $phpcs_file->fixer->end_changeset();
                }
            } else {
                $phpcs_file->record_metric($stack_ptr, 'Blank lines at end of control structure', 0);
            }
            //end if
        }
        //end if
        if ($tokens[$stack_ptr]['code'] === T_MATCH) {
            // Move the scope closer to the semicolon/comma.
            $next = $phpcs_file->find_next(Tokens::$empty_tokens, $scope_closer + 1, null, true);
            if ($next !== false && ($tokens[$next]['code'] === T_SEMICOLON || $tokens[$next]['code'] === T_COMMA)) {
                $scope_closer = $next;
            }
        }
        $trailing_content = $phpcs_file->find_next(T_WHITESPACE, $scope_closer + 1, null, true);
        if ($tokens[$trailing_content]['code'] === T_COMMENT || isset(Tokens::$phpcs_comment_tokens[$tokens[$trailing_content]['code']]) === true) {
            // Special exception for code where the comment about
            // an ELSE or ELSEIF is written between the control structures.
            $next_code = $phpcs_file->find_next(Tokens::$empty_tokens, $scope_closer + 1, null, true);
            if ($tokens[$next_code]['code'] === T_ELSE || $tokens[$next_code]['code'] === T_ELSEIF || $tokens[$trailing_content]['line'] === $tokens[$scope_closer]['line']) {
                $trailing_content = $next_code;
            }
        }
        //end if
        if ($tokens[$trailing_content]['code'] === T_ELSE) {
            if ($tokens[$stack_ptr]['code'] === T_IF) {
                // IF with ELSE.
                return;
            }
        }
        if ($tokens[$trailing_content]['code'] === T_WHILE && $tokens[$stack_ptr]['code'] === T_DO) {
            // DO with WHILE.
            return;
        }
        if ($tokens[$trailing_content]['code'] === T_CLOSE_TAG) {
            // At the end of the script or embedded code.
            return;
        }
        if (isset($tokens[$trailing_content]['scope_condition']) === true && $tokens[$trailing_content]['scope_condition'] !== $trailing_content && isset($tokens[$trailing_content]['scope_opener']) === true && $tokens[$trailing_content]['scope_opener'] !== $trailing_content) {
            // Another control structure's closing brace.
            $owner = $tokens[$trailing_content]['scope_condition'];
            if ($tokens[$owner]['code'] === T_FUNCTION) {
                // The next content is the closing brace of a function
                // so normal function rules apply and we can ignore it.
                return;
            }
            if ($tokens[$owner]['code'] === T_CLOSURE && ($phpcs_file->has_condition($stack_ptr, [T_FUNCTION, T_CLOSURE]) === true || isset($tokens[$stack_ptr]['nested_parenthesis']) === true)) {
                return;
            }
            if ($tokens[$trailing_content]['line'] !== $tokens[$scope_closer]['line'] + 1) {
                $error = 'Blank line found after control structure';
                $fix = $phpcs_file->add_fixable_error($error, $scope_closer, 'LineAfterClose');
                if ($fix === true) {
                    $phpcs_file->fixer->begin_changeset();
                    $i = $scope_closer + 1;
                    while ($tokens[$i]['line'] !== $tokens[$trailing_content]['line']) {
                        $phpcs_file->fixer->replace_token($i, '');
                        $i++;
                    }
                    $phpcs_file->fixer->add_newline($scope_closer);
                    $phpcs_file->fixer->end_changeset();
                }
            }
        } elseif ($tokens[$trailing_content]['code'] !== T_ELSE && $tokens[$trailing_content]['code'] !== T_ELSEIF && $tokens[$trailing_content]['code'] !== T_CATCH && $tokens[$trailing_content]['code'] !== T_FINALLY && $tokens[$trailing_content]['line'] === $tokens[$scope_closer]['line'] + 1) {
            $error = 'No blank line found after control structure';
            $fix = $phpcs_file->add_fixable_error($error, $scope_closer, 'NoLineAfterClose');
            if ($fix === true) {
                $trailing_content = $phpcs_file->find_next(T_WHITESPACE, $scope_closer + 1, null, true);
                if (($tokens[$trailing_content]['code'] === T_COMMENT || isset(Tokens::$phpcs_comment_tokens[$tokens[$trailing_content]['code']]) === true) && $tokens[$trailing_content]['line'] === $tokens[$scope_closer]['line']) {
                    $phpcs_file->fixer->add_newline($trailing_content);
                } else {
                    $phpcs_file->fixer->add_newline($scope_closer);
                }
            }
        }
        //end if
    }
    //end process()
}
//end class