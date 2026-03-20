<?php

declare (strict_types=1);
/**
 * Verifies that control statements conform to their coding standards.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\Control_Structures;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Control_Signature_Sniff implements Sniff
{
    /**
     * How many spaces should precede the colon if using alternative syntax.
     *
     * @var integer
     */
    public $required_spaces_before_colon = 1;
    /**
     * A list of tokenizers this sniff supports.
     *
     * @var array
     */
    public $supported_tokenizers = ['PHP', 'JS'];
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return int[]
     */
    public function register()
    {
        return [T_TRY, T_CATCH, T_FINALLY, T_DO, T_WHILE, T_FOR, T_IF, T_FOREACH, T_ELSE, T_ELSEIF, T_SWITCH, T_MATCH];
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
        $next_non_empty = $phpcs_file->find_next(Tokens::$empty_tokens, $stack_ptr + 1, null, true);
        if ($next_non_empty === false) {
            return;
        }
        $is_alternative = false;
        if (isset($tokens[$stack_ptr]['scope_opener']) === true && $tokens[$tokens[$stack_ptr]['scope_opener']]['code'] === T_COLON) {
            $is_alternative = true;
        }
        // Single space after the keyword.
        $expected = 1;
        if (isset($tokens[$stack_ptr]['parenthesis_closer']) === false && $is_alternative === true) {
            // Catching cases like:
            // if (condition) : ... else: ... endif
            // where there is no condition.
            $expected = (int) $this->required_spaces_before_colon;
        }
        $found = 1;
        if ($tokens[$stack_ptr + 1]['code'] !== T_WHITESPACE) {
            $found = 0;
        } elseif ($tokens[$stack_ptr + 1]['content'] !== ' ') {
            if (strpos($tokens[$stack_ptr + 1]['content'], $phpcs_file->eol_char) !== false) {
                $found = 'newline';
            } else {
                $found = $tokens[$stack_ptr + 1]['length'];
            }
        }
        if ($found !== $expected) {
            $error = 'Expected %s space(s) after %s keyword; %s found';
            $data = [$expected, strtoupper($tokens[$stack_ptr]['content']), $found];
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'SpaceAfterKeyword', $data);
            if ($fix === true) {
                if ($found === 0) {
                    $phpcs_file->fixer->add_content($stack_ptr, str_repeat(' ', $expected));
                } else {
                    $phpcs_file->fixer->replace_token($stack_ptr + 1, str_repeat(' ', $expected));
                }
            }
        }
        // Single space after closing parenthesis.
        if (isset($tokens[$stack_ptr]['parenthesis_closer']) === true && isset($tokens[$stack_ptr]['scope_opener']) === true) {
            $expected = 1;
            if ($is_alternative === true) {
                $expected = (int) $this->required_spaces_before_colon;
            }
            $closer = $tokens[$stack_ptr]['parenthesis_closer'];
            $opener = $tokens[$stack_ptr]['scope_opener'];
            $content = $phpcs_file->get_tokens_as_string($closer + 1, $opener - $closer - 1);
            if (trim($content) === '') {
                if (strpos($content, $phpcs_file->eol_char) !== false) {
                    $found = 'newline';
                } else {
                    $found = strlen($content);
                }
            } else {
                $found = '"' . str_replace($phpcs_file->eol_char, '\n', $content) . '"';
            }
            if ($found !== $expected) {
                $error = 'Expected %s space(s) after closing parenthesis; found %s';
                $data = [$expected, $found];
                $fix = $phpcs_file->add_fixable_error($error, $closer, 'SpaceAfterCloseParenthesis', $data);
                if ($fix === true) {
                    $padding = str_repeat(' ', $expected);
                    if ($closer === $opener - 1) {
                        $phpcs_file->fixer->add_content($closer, $padding);
                    } else {
                        $phpcs_file->fixer->begin_changeset();
                        if (trim($content) === '') {
                            $phpcs_file->fixer->add_content($closer, $padding);
                            if ($found !== 0) {
                                for ($i = $closer + 1; $i < $opener; $i++) {
                                    $phpcs_file->fixer->replace_token($i, '');
                                }
                            }
                        } else {
                            $phpcs_file->fixer->add_content($closer, $padding . $tokens[$opener]['content']);
                            $phpcs_file->fixer->replace_token($opener, '');
                            if ($tokens[$opener]['line'] !== $tokens[$closer]['line']) {
                                $next = $phpcs_file->find_next(T_WHITESPACE, $opener + 1, null, true);
                                if ($tokens[$next]['line'] !== $tokens[$opener]['line']) {
                                    for ($i = $opener + 1; $i < $next; $i++) {
                                        $phpcs_file->fixer->replace_token($i, '');
                                    }
                                }
                            }
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
        // Single newline after opening brace.
        if (isset($tokens[$stack_ptr]['scope_opener']) === true) {
            $opener = $tokens[$stack_ptr]['scope_opener'];
            for ($next = $opener + 1; $next < $phpcs_file->num_tokens; $next++) {
                $code = $tokens[$next]['code'];
                if ($code === T_WHITESPACE) {
                    continue;
                }
                if ($code === T_INLINE_HTML && trim($tokens[$next]['content']) === '') {
                    continue;
                }
                // Skip all empty tokens on the same line as the opener.
                if ($tokens[$next]['line'] === $tokens[$opener]['line'] && (isset(Tokens::$empty_tokens[$code]) === true || $code === T_CLOSE_TAG)) {
                    continue;
                }
                // We found the first bit of a code, or a comment on the
                // following line.
                break;
            }
            //end for
            if ($tokens[$next]['line'] === $tokens[$opener]['line']) {
                $error = 'Newline required after opening brace';
                $fix = $phpcs_file->add_fixable_error($error, $opener, 'NewlineAfterOpenBrace');
                if ($fix === true) {
                    $phpcs_file->fixer->begin_changeset();
                    for ($i = $opener + 1; $i < $next; $i++) {
                        if (trim($tokens[$i]['content']) !== '') {
                            break;
                        }
                        // Remove whitespace.
                        $phpcs_file->fixer->replace_token($i, '');
                    }
                    $phpcs_file->fixer->add_content($opener, $phpcs_file->eol_char);
                    $phpcs_file->fixer->end_changeset();
                }
            }
            //end if
        } elseif ($tokens[$stack_ptr]['code'] === T_WHILE) {
            // Zero spaces after parenthesis closer, but only if followed by a semicolon.
            $closer = $tokens[$stack_ptr]['parenthesis_closer'];
            $next_non_empty = $phpcs_file->find_next(Tokens::$empty_tokens, $closer + 1, null, true);
            if ($next_non_empty !== false && $tokens[$next_non_empty]['code'] === T_SEMICOLON) {
                $found = 0;
                if ($tokens[$closer + 1]['code'] === T_WHITESPACE) {
                    if (strpos($tokens[$closer + 1]['content'], $phpcs_file->eol_char) !== false) {
                        $found = 'newline';
                    } else {
                        $found = $tokens[$closer + 1]['length'];
                    }
                }
                if ($found !== 0) {
                    $error = 'Expected 0 spaces before semicolon; %s found';
                    $data = [$found];
                    $fix = $phpcs_file->add_fixable_error($error, $closer, 'SpaceBeforeSemicolon', $data);
                    if ($fix === true) {
                        $phpcs_file->fixer->replace_token($closer + 1, '');
                    }
                }
            }
        }
        //end if
        // Only want to check multi-keyword structures from here on.
        if ($tokens[$stack_ptr]['code'] === T_WHILE) {
            if (isset($tokens[$stack_ptr]['scope_closer']) !== false) {
                return;
            }
            $closer = $phpcs_file->find_previous(Tokens::$empty_tokens, $stack_ptr - 1, null, true);
            if ($closer === false || $tokens[$closer]['code'] !== T_CLOSE_CURLY_BRACKET || $tokens[$tokens[$closer]['scope_condition']]['code'] !== T_DO) {
                return;
            }
        } elseif ($tokens[$stack_ptr]['code'] === T_ELSE || $tokens[$stack_ptr]['code'] === T_ELSEIF || $tokens[$stack_ptr]['code'] === T_CATCH || $tokens[$stack_ptr]['code'] === T_FINALLY) {
            if (isset($tokens[$stack_ptr]['scope_opener']) === true && $tokens[$tokens[$stack_ptr]['scope_opener']]['code'] === T_COLON) {
                // Special case for alternate syntax, where this token is actually
                // the closer for the previous block, so there is no spacing to check.
                return;
            }
            $closer = $phpcs_file->find_previous(Tokens::$empty_tokens, $stack_ptr - 1, null, true);
            if ($closer === false || $tokens[$closer]['code'] !== T_CLOSE_CURLY_BRACKET) {
                return;
            }
        } else {
            return;
        }
        //end if
        // Single space after closing brace.
        $found = 1;
        if ($tokens[$closer + 1]['code'] !== T_WHITESPACE) {
            $found = 0;
        } elseif ($tokens[$closer]['line'] !== $tokens[$stack_ptr]['line']) {
            $found = 'newline';
        } elseif ($tokens[$closer + 1]['content'] !== ' ') {
            $found = $tokens[$closer + 1]['length'];
        }
        if ($found !== 1) {
            $error = 'Expected 1 space after closing brace; %s found';
            $data = [$found];
            if ($phpcs_file->find_next(Tokens::$comment_tokens, $closer + 1, $stack_ptr) !== false) {
                // Comment found between closing brace and keyword, don't auto-fix.
                $phpcs_file->add_error($error, $closer, 'SpaceAfterCloseBrace', $data);
                return;
            }
            $fix = $phpcs_file->add_fixable_error($error, $closer, 'SpaceAfterCloseBrace', $data);
            if ($fix === true) {
                if ($found === 0) {
                    $phpcs_file->fixer->add_content($closer, ' ');
                } else {
                    $phpcs_file->fixer->replace_token($closer + 1, ' ');
                }
            }
        }
    }
    //end process()
}
//end class