<?php

declare (strict_types=1);
/**
 * Ensures USE blocks are declared correctly.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\PSR2\Sniffs\Namespaces;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Use_Declaration_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_USE];
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
        if ($this->should_ignore_use($phpcs_file, $stack_ptr) === true) {
            return;
        }
        $tokens = $phpcs_file->get_tokens();
        // One space after the use keyword.
        if ($tokens[$stack_ptr + 1]['content'] !== ' ') {
            $error = 'There must be a single space after the USE keyword';
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'SpaceAfterUse');
            if ($fix === true) {
                $phpcs_file->fixer->replace_token($stack_ptr + 1, ' ');
            }
        }
        // Only one USE declaration allowed per statement.
        $next = $phpcs_file->find_next([T_COMMA, T_SEMICOLON, T_OPEN_USE_GROUP, T_CLOSE_TAG], $stack_ptr + 1);
        if ($next !== false && $tokens[$next]['code'] !== T_SEMICOLON && $tokens[$next]['code'] !== T_CLOSE_TAG) {
            $error = 'There must be one USE keyword per declaration';
            if ($tokens[$next]['code'] === T_COMMA) {
                $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'MultipleDeclarations');
                if ($fix === true) {
                    switch ($tokens[$stack_ptr + 2]['content']) {
                        case 'const':
                            $base_use = 'use const';
                            break;
                        case 'function':
                            $base_use = 'use function';
                            break;
                        default:
                            $base_use = 'use';
                    }
                    if ($tokens[$next + 1]['code'] !== T_WHITESPACE) {
                        $base_use .= ' ';
                    }
                    $phpcs_file->fixer->replace_token($next, ';' . $phpcs_file->eol_char . $base_use);
                }
            } else {
                $closing_curly = $phpcs_file->find_next(T_CLOSE_USE_GROUP, $next + 1);
                if ($closing_curly === false) {
                    // Parse error or live coding. Not auto-fixable.
                    $phpcs_file->add_error($error, $stack_ptr, 'MultipleDeclarations');
                } else {
                    $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'MultipleDeclarations');
                    if ($fix === true) {
                        $base_use = rtrim($phpcs_file->get_tokens_as_string($stack_ptr, $next - $stack_ptr));
                        $last_non_whitespace = $phpcs_file->find_previous(T_WHITESPACE, $closing_curly - 1, null, true);
                        $phpcs_file->fixer->begin_changeset();
                        // Remove base use statement.
                        for ($i = $stack_ptr; $i <= $next; $i++) {
                            $phpcs_file->fixer->replace_token($i, '');
                        }
                        if (preg_match('`^[\r\n]+$`', $tokens[$next + 1]['content']) === 1) {
                            $phpcs_file->fixer->replace_token($next + 1, '');
                        }
                        // Convert grouped use statements into full use statements.
                        do {
                            $next = $phpcs_file->find_next(Tokens::$empty_tokens, $next + 1, $closing_curly, true);
                            if ($next === false) {
                                // Group use statement with trailing comma after last item.
                                break;
                            }
                            $non_whitespace = $phpcs_file->find_previous(T_WHITESPACE, $next - 1, null, true);
                            for ($i = $non_whitespace + 1; $i < $next; $i++) {
                                if (preg_match('`^[\r\n]+$`', $tokens[$i]['content']) === 1) {
                                    // Preserve new lines.
                                    continue;
                                }
                                $phpcs_file->fixer->replace_token($i, '');
                            }
                            if ($tokens[$next]['content'] === 'const' || $tokens[$next]['content'] === 'function') {
                                $phpcs_file->fixer->add_content_before($next, 'use ');
                                $next = $phpcs_file->find_next(Tokens::$empty_tokens, $next + 1, $closing_curly, true);
                                $phpcs_file->fixer->add_content_before($next, str_replace('use ', '', $base_use));
                            } else {
                                $phpcs_file->fixer->add_content_before($next, $base_use);
                            }
                            $next = $phpcs_file->find_next(T_COMMA, $next + 1, $closing_curly);
                            if ($next !== false) {
                                $next_non_empty = $phpcs_file->find_next(Tokens::$empty_tokens, $next + 1, $closing_curly, true);
                                if ($next_non_empty !== false && $tokens[$next_non_empty]['line'] === $tokens[$next]['line']) {
                                    $prev_non_whitespace = $phpcs_file->find_previous(T_WHITESPACE, $next_non_empty - 1, $next, true);
                                    if ($prev_non_whitespace === $next) {
                                        $phpcs_file->fixer->replace_token($next, ';' . $phpcs_file->eol_char);
                                    } else {
                                        $phpcs_file->fixer->replace_token($next, ';');
                                        $phpcs_file->fixer->add_newline($prev_non_whitespace);
                                    }
                                } else {
                                    // Last item with trailing comma or next item already on new line.
                                    $phpcs_file->fixer->replace_token($next, ';');
                                }
                            } else {
                                // Last item without trailing comma.
                                $phpcs_file->fixer->add_content($last_non_whitespace, ';');
                            }
                        } while ($next !== false);
                        // Remove closing curly,semi-colon and any whitespace between last child and closing curly.
                        $next = $phpcs_file->find_next(Tokens::$empty_tokens, $closing_curly + 1, null, true);
                        if ($next === false || $tokens[$next]['code'] !== T_SEMICOLON) {
                            // Parse error, forgotten semi-colon.
                            $next = $closing_curly;
                        }
                        for ($i = $last_non_whitespace + 1; $i <= $next; $i++) {
                            $phpcs_file->fixer->replace_token($i, '');
                        }
                        $phpcs_file->fixer->end_changeset();
                    }
                    //end if
                }
                //end if
            }
            //end if
        }
        //end if
        // Make sure this USE comes after the first namespace declaration.
        $prev = $phpcs_file->find_previous(T_NAMESPACE, $stack_ptr - 1);
        if ($prev === false) {
            $next = $phpcs_file->find_next(T_NAMESPACE, $stack_ptr + 1);
            if ($next !== false) {
                $error = 'USE declarations must go after the namespace declaration';
                $phpcs_file->add_error($error, $stack_ptr, 'UseBeforeNamespace');
            }
        }
        // Only interested in the last USE statement from here onwards.
        $next_use = $phpcs_file->find_next(T_USE, $stack_ptr + 1);
        while ($this->should_ignore_use($phpcs_file, $next_use) === true) {
            $next_use = $phpcs_file->find_next(T_USE, $next_use + 1);
            if ($next_use === false) {
                break;
            }
        }
        if ($next_use !== false) {
            return;
        }
        $end = $phpcs_file->find_next([T_SEMICOLON, T_CLOSE_USE_GROUP, T_CLOSE_TAG], $stack_ptr + 1);
        if ($end === false) {
            return;
        }
        if ($tokens[$end]['code'] === T_CLOSE_USE_GROUP) {
            $next_non_empty = $phpcs_file->find_next(Tokens::$empty_tokens, $end + 1, null, true);
            if ($tokens[$next_non_empty]['code'] === T_SEMICOLON) {
                $end = $next_non_empty;
            }
        }
        // Find either the start of the next line or the beginning of the next statement,
        // whichever comes first.
        for ($end = ++$end; $end < $phpcs_file->num_tokens; $end++) {
            if (isset(Tokens::$empty_tokens[$tokens[$end]['code']]) === false) {
                break;
            }
            if ($tokens[$end]['column'] === 1) {
                // Reached the next line.
                break;
            }
        }
        --$end;
        if (($tokens[$end]['code'] === T_COMMENT || isset(Tokens::$phpcs_comment_tokens[$tokens[$end]['code']]) === true) && substr($tokens[$end]['content'], 0, 2) === '/*' && substr($tokens[$end]['content'], -2) !== '*/') {
            // Multi-line block comments are not allowed as trailing comment after a use statement.
            --$end;
        }
        $next = $phpcs_file->find_next(T_WHITESPACE, $end + 1, null, true);
        if ($next === false || $tokens[$next]['code'] === T_CLOSE_TAG) {
            return;
        }
        $diff = $tokens[$next]['line'] - $tokens[$end]['line'] - 1;
        if ($diff !== 1) {
            if ($diff < 0) {
                $diff = 0;
            }
            $error = 'There must be one blank line after the last USE statement; %s found;';
            $data = [$diff];
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'SpaceAfterLastUse', $data);
            if ($fix === true) {
                if ($diff === 0) {
                    $phpcs_file->fixer->add_newline($end);
                } else {
                    $phpcs_file->fixer->begin_changeset();
                    for ($i = $end + 1; $i < $next; $i++) {
                        if ($tokens[$i]['line'] === $tokens[$next]['line']) {
                            break;
                        }
                        $phpcs_file->fixer->replace_token($i, '');
                    }
                    $phpcs_file->fixer->add_newline($end);
                    $phpcs_file->fixer->end_changeset();
                }
            }
        }
        //end if
    }
    //end process()
    /**
     * Check if this use statement is part of the namespace block.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token in
     *                                               the stack passed in $tokens.
     *
     * @return bool
     */
    private function should_ignore_use(\Php_code_Sniffer\Files\File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        // Ignore USE keywords inside closures and during live coding.
        $next = $phpcs_file->find_next(Tokens::$empty_tokens, $stack_ptr + 1, null, true);
        if ($next === false || $tokens[$next]['code'] === T_OPEN_PARENTHESIS) {
            return true;
        }
        // Ignore USE keywords for traits.
        if ($phpcs_file->has_condition($stack_ptr, [T_CLASS, T_TRAIT, T_ENUM]) === true) {
            return true;
        }
        return false;
    }
    //end shouldIgnoreUse()
}
//end class