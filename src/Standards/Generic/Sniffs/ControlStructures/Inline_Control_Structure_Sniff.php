<?php

declare (strict_types=1);
/**
 * Verifies that inline control statements are not present.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Generic\Sniffs\Control_Structures;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Inline_Control_Structure_Sniff implements Sniff
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
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_IF, T_ELSE, T_ELSEIF, T_FOREACH, T_WHILE, T_DO, T_SWITCH, T_FOR];
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
        if (isset($tokens[$stack_ptr]['scope_opener']) === true) {
            $phpcs_file->record_metric($stack_ptr, 'Control structure defined inline', 'no');
            return;
        }
        // Ignore the ELSE in ELSE IF. We'll process the IF part later.
        if ($tokens[$stack_ptr]['code'] === T_ELSE) {
            $next = $phpcs_file->find_next(T_WHITESPACE, $stack_ptr + 1, null, true);
            if ($tokens[$next]['code'] === T_IF) {
                return;
            }
        }
        if ($tokens[$stack_ptr]['code'] === T_WHILE || $tokens[$stack_ptr]['code'] === T_FOR) {
            // This could be from a DO WHILE, which doesn't have an opening brace or a while/for without body.
            if (isset($tokens[$stack_ptr]['parenthesis_closer']) === true) {
                $after_parens_closer = $phpcs_file->find_next(Tokens::$empty_tokens, $tokens[$stack_ptr]['parenthesis_closer'] + 1, null, true);
                if ($after_parens_closer === false) {
                    // Live coding.
                    return;
                }
                if ($tokens[$after_parens_closer]['code'] === T_SEMICOLON) {
                    $phpcs_file->record_metric($stack_ptr, 'Control structure defined inline', 'no');
                    return;
                }
            }
            // In Javascript DO WHILE loops without curly braces are legal. This
            // is only valid if a single statement is present between the DO and
            // the WHILE. We can detect this by checking only a single semicolon
            // is present between them.
            if ($tokens[$stack_ptr]['code'] === T_WHILE && $phpcs_file->tokenizer_type === 'JS') {
                $last_do = $phpcs_file->find_previous(T_DO, $stack_ptr - 1);
                $last_semicolon = $phpcs_file->find_previous(T_SEMICOLON, $stack_ptr - 1);
                if ($last_do !== false && $last_semicolon !== false && $last_do < $last_semicolon) {
                    $preceding_semicolon = $phpcs_file->find_previous(T_SEMICOLON, $last_semicolon - 1);
                    if ($preceding_semicolon === false || $preceding_semicolon < $last_do) {
                        return;
                    }
                }
            }
        }
        //end if
        if (isset($tokens[$stack_ptr]['parenthesis_opener'], $tokens[$stack_ptr]['parenthesis_closer']) === false && $tokens[$stack_ptr]['code'] !== T_ELSE) {
            if ($tokens[$stack_ptr]['code'] !== T_DO) {
                // Live coding or parse error.
                return;
            }
            $next_while = $phpcs_file->find_next(T_WHILE, $stack_ptr + 1);
            if ($next_while !== false && isset($tokens[$next_while]['parenthesis_opener'], $tokens[$next_while]['parenthesis_closer']) === false) {
                // Live coding or parse error.
                return;
            }
            unset($next_while);
        }
        $start = $stack_ptr;
        if (isset($tokens[$stack_ptr]['parenthesis_closer']) === true) {
            $start = $tokens[$stack_ptr]['parenthesis_closer'];
        }
        $next_non_empty = $phpcs_file->find_next(Tokens::$empty_tokens, $start + 1, null, true);
        if ($next_non_empty === false) {
            // Live coding or parse error.
            return;
        }
        if ($tokens[$next_non_empty]['code'] === T_OPEN_CURLY_BRACKET || $tokens[$next_non_empty]['code'] === T_COLON) {
            // T_CLOSE_CURLY_BRACKET missing, or alternative control structure with
            // T_END... missing. Either live coding, parse error or end
            // tag in short open tags and scan run with short_open_tag=Off.
            // Bow out completely as any further detection will be unreliable
            // and create incorrect fixes or cause fixer conflicts.
            return $phpcs_file->num_tokens + 1;
        }
        unset($next_non_empty, $start);
        // This is a control structure without an opening brace,
        // so it is an inline statement.
        if ($this->error === true) {
            $fix = $phpcs_file->add_fixable_error('Inline control structures are not allowed', $stack_ptr, 'NotAllowed');
        } else {
            $fix = $phpcs_file->add_fixable_warning('Inline control structures are discouraged', $stack_ptr, 'Discouraged');
        }
        $phpcs_file->record_metric($stack_ptr, 'Control structure defined inline', 'yes');
        // Stop here if we are not fixing the error.
        if ($fix !== true) {
            return;
        }
        $phpcs_file->fixer->begin_changeset();
        if (isset($tokens[$stack_ptr]['parenthesis_closer']) === true) {
            $closer = $tokens[$stack_ptr]['parenthesis_closer'];
        } else {
            $closer = $stack_ptr;
        }
        if ($tokens[$closer + 1]['code'] === T_WHITESPACE || $tokens[$closer + 1]['code'] === T_SEMICOLON) {
            $phpcs_file->fixer->add_content($closer, ' {');
        } else {
            $phpcs_file->fixer->add_content($closer, ' { ');
        }
        $fixable_scope_openers = $this->register();
        $last_non_empty = $closer;
        for ($end = $closer + 1; $end < $phpcs_file->num_tokens; $end++) {
            if ($tokens[$end]['code'] === T_SEMICOLON) {
                break;
            }
            if ($tokens[$end]['code'] === T_CLOSE_TAG) {
                $end = $last_non_empty;
                break;
            }
            if (in_array($tokens[$end]['code'], $fixable_scope_openers, true) === true && isset($tokens[$end]['scope_opener']) === false) {
                // The best way to fix nested inline scopes is middle-out.
                // So skip this one. It will be detected and fixed on a future loop.
                $phpcs_file->fixer->rollback_changeset();
                return;
            }
            if (isset($tokens[$end]['scope_opener']) === true) {
                $type = $tokens[$end]['code'];
                $end = $tokens[$end]['scope_closer'];
                if ($type === T_DO || $type === T_IF || $type === T_ELSEIF || $type === T_TRY || $type === T_CATCH || $type === T_FINALLY) {
                    $next = $phpcs_file->find_next(Tokens::$empty_tokens, $end + 1, null, true);
                    if ($next === false) {
                        break;
                    }
                    $next_type = $tokens[$next]['code'];
                    // Let additional conditions loop and find their ending.
                    if (($type === T_IF || $type === T_ELSEIF) && ($next_type === T_ELSEIF || $next_type === T_ELSE)) {
                        continue;
                    }
                    // Account for TRY... CATCH/FINALLY statements.
                    if (($type === T_TRY || $type === T_CATCH || $type === T_FINALLY) && ($next_type === T_CATCH || $next_type === T_FINALLY)) {
                        continue;
                    }
                    // Account for DO... WHILE conditions.
                    if ($type === T_DO && $next_type === T_WHILE) {
                        $end = $phpcs_file->find_next(T_SEMICOLON, $next + 1);
                    }
                } elseif ($type === T_CLOSURE) {
                    // There should be a semicolon after the closing brace.
                    $next = $phpcs_file->find_next(Tokens::$empty_tokens, $end + 1, null, true);
                    if ($next !== false && $tokens[$next]['code'] === T_SEMICOLON) {
                        $end = $next;
                    }
                }
                //end if
                if ($tokens[$end]['code'] !== T_END_HEREDOC && $tokens[$end]['code'] !== T_END_NOWDOC) {
                    break;
                }
            }
            //end if
            if (isset($tokens[$end]['parenthesis_closer']) === true) {
                $end = $tokens[$end]['parenthesis_closer'];
                $last_non_empty = $end;
                continue;
            }
            if ($tokens[$end]['code'] !== T_WHITESPACE) {
                $last_non_empty = $end;
            }
        }
        //end for
        if ($end === $phpcs_file->num_tokens) {
            $end = $last_non_empty;
        }
        $next_content = $phpcs_file->find_next(Tokens::$empty_tokens, $end + 1, null, true);
        if ($next_content === false || $tokens[$next_content]['line'] !== $tokens[$end]['line']) {
            // Looks for completely empty statements.
            $next = $phpcs_file->find_next(T_WHITESPACE, $closer + 1, $end + 1, true);
        } else {
            $next = $end + 1;
            $end_line = $end;
        }
        if ($next !== $end) {
            if ($next_content === false || $tokens[$next_content]['line'] !== $tokens[$end]['line']) {
                // Account for a comment on the end of the line.
                for ($end_line = $end; $end_line < $phpcs_file->num_tokens; $end_line++) {
                    if (isset($tokens[$end_line + 1]) === false || $tokens[$end_line]['line'] !== $tokens[$end_line + 1]['line']) {
                        break;
                    }
                }
                if (isset(Tokens::$comment_tokens[$tokens[$end_line]['code']]) === false && ($tokens[$end_line]['code'] !== T_WHITESPACE || isset(Tokens::$comment_tokens[$tokens[$end_line - 1]['code']]) === false)) {
                    $end_line = $end;
                }
            }
            if ($end_line !== $end) {
                $end_token = $end_line;
                $added_content = '';
            } else {
                $end_token = $end;
                $added_content = $phpcs_file->eol_char;
                if ($tokens[$end]['code'] !== T_SEMICOLON && $tokens[$end]['code'] !== T_CLOSE_CURLY_BRACKET) {
                    $phpcs_file->fixer->add_content($end, '; ');
                }
            }
            $next = $phpcs_file->find_next(T_WHITESPACE, $end_token + 1, null, true);
            if ($next !== false && ($tokens[$next]['code'] === T_ELSE || $tokens[$next]['code'] === T_ELSEIF)) {
                $phpcs_file->fixer->add_content_before($next, '} ');
            } else {
                $indent = '';
                for ($first = $stack_ptr; $first > 0; $first--) {
                    if ($tokens[$first]['column'] === 1) {
                        break;
                    }
                }
                if ($tokens[$first]['code'] === T_WHITESPACE) {
                    $indent = $tokens[$first]['content'];
                } elseif ($tokens[$first]['code'] === T_INLINE_HTML || $tokens[$first]['code'] === T_OPEN_TAG) {
                    $added_content = '';
                }
                $added_content .= $indent . '}';
                if ($next !== false && $tokens[$end_token]['code'] === T_COMMENT) {
                    $added_content .= $phpcs_file->eol_char;
                }
                $phpcs_file->fixer->add_content($end_token, $added_content);
            }
            //end if
        } else {
            if ($next_content === false || $tokens[$next_content]['line'] !== $tokens[$end]['line']) {
                // Account for a comment on the end of the line.
                for ($end_line = $end; $end_line < $phpcs_file->num_tokens; $end_line++) {
                    if (isset($tokens[$end_line + 1]) === false || $tokens[$end_line]['line'] !== $tokens[$end_line + 1]['line']) {
                        break;
                    }
                }
                if ($tokens[$end_line]['code'] !== T_COMMENT && ($tokens[$end_line]['code'] !== T_WHITESPACE || $tokens[$end_line - 1]['code'] !== T_COMMENT)) {
                    $end_line = $end;
                }
            }
            if ($end_line !== $end) {
                $phpcs_file->fixer->replace_token($end, '');
                $phpcs_file->fixer->add_newline_before($end_line);
                $phpcs_file->fixer->add_content($end_line, '}');
            } else {
                $phpcs_file->fixer->replace_token($end, '}');
            }
        }
        //end if
        $phpcs_file->fixer->end_changeset();
    }
    //end process()
}
//end class