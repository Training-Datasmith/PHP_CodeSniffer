<?php

declare (strict_types=1);
/**
 * Verifies that trait import statements are defined correctly.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\PSR12\Sniffs\Traits;

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
     * @param int                         $stackPtr  The position of the current token in the
     *                                               stack passed in $tokens.
     *
     * @return void
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        // Needs to be a use statement directly inside a class.
        $conditions = $tokens[$stack_ptr]['conditions'];
        end($conditions);
        if (isset(Tokens::$oo_scope_tokens[current($conditions)]) === false) {
            return;
        }
        $oo_token = key($conditions);
        $opener = $tokens[$oo_token]['scope_opener'];
        // Figure out where all the use statements are.
        $use_tokens = [$stack_ptr];
        for ($i = $stack_ptr + 1; $i < $tokens[$oo_token]['scope_closer']; $i++) {
            if ($tokens[$i]['code'] === T_USE) {
                $use_tokens[] = $i;
            }
            if (isset($tokens[$i]['scope_closer']) === true) {
                $i = $tokens[$i]['scope_closer'];
            }
        }
        $num_use_tokens = count($use_tokens);
        foreach ($use_tokens as $use_pos => $use_token) {
            if ($use_pos === 0) {
                /*
                    This is the first use statement.
                */
                // The first non-comment line must be the use line.
                $last_valid_content = $use_token;
                for ($i = $use_token - 1; $i > $opener; $i--) {
                    if ($tokens[$i]['code'] === T_WHITESPACE && ($tokens[$i - 1]['line'] === $tokens[$i]['line'] || $tokens[$i + 1]['line'] === $tokens[$i]['line'])) {
                        continue;
                    }
                    if (isset(Tokens::$comment_tokens[$tokens[$i]['code']]) === true) {
                        if ($tokens[$i]['code'] === T_DOC_COMMENT_CLOSE_TAG) {
                            // Skip past the comment.
                            $i = $tokens[$i]['comment_opener'];
                        }
                        $last_valid_content = $i;
                        continue;
                    }
                    break;
                }
                //end for
                if ($tokens[$last_valid_content]['line'] !== $tokens[$opener]['line'] + 1) {
                    $error = 'The first trait import statement must be declared on the first non-comment line after the %s opening brace';
                    $data = [strtolower($tokens[$oo_token]['content'])];
                    // Figure out if we can fix this error.
                    $prev = $phpcs_file->find_previous(Tokens::$empty_tokens, $use_token - 1, $opener - 1, true);
                    if ($tokens[$prev]['line'] === $tokens[$opener]['line']) {
                        $fix = $phpcs_file->add_fixable_error($error, $use_token, 'UseAfterBrace', $data);
                        if ($fix === true) {
                            // We know that the USE statements is the first non-comment content
                            // in the class, so we just need to remove blank lines.
                            $phpcs_file->fixer->begin_changeset();
                            for ($i = $use_token - 1; $i > $opener; $i--) {
                                if ($tokens[$i]['line'] === $tokens[$opener]['line']) {
                                    break;
                                }
                                if ($tokens[$i]['line'] === $tokens[$use_token]['line']) {
                                    continue;
                                }
                                if ($tokens[$i]['code'] === T_WHITESPACE && $tokens[$i - 1]['line'] !== $tokens[$i]['line'] && $tokens[$i + 1]['line'] !== $tokens[$i]['line']) {
                                    $phpcs_file->fixer->replace_token($i, '');
                                }
                                if (isset(Tokens::$comment_tokens[$tokens[$i]['code']]) === true) {
                                    if ($tokens[$i]['code'] === T_DOC_COMMENT_CLOSE_TAG) {
                                        // Skip past the comment.
                                        $i = $tokens[$i]['comment_opener'];
                                    }
                                    $last_valid_content = $i;
                                }
                            }
                            //end for
                            $phpcs_file->fixer->end_changeset();
                        }
                        //end if
                    } else {
                        $phpcs_file->add_error($error, $use_token, 'UseAfterBrace', $data);
                    }
                    //end if
                }
                //end if
            } else {
                // Make sure this use statement is not on the same line as the previous one.
                $prev = $phpcs_file->find_previous(Tokens::$empty_tokens, $use_token - 1, null, true);
                if ($prev !== false && $tokens[$prev]['line'] === $tokens[$use_token]['line']) {
                    $error = 'Each imported trait must be on its own line';
                    $prev_non_ws = $phpcs_file->find_previous(T_WHITESPACE, $use_token - 1, null, true);
                    if ($prev_non_ws !== $prev) {
                        $phpcs_file->add_error($error, $use_token, 'SpacingBeforeImport');
                    } else {
                        $fix = $phpcs_file->add_fixable_error($error, $use_token, 'SpacingBeforeImport');
                        if ($fix === true) {
                            $phpcs_file->fixer->begin_changeset();
                            for ($x = $use_token - 1; $x > $prev; $x--) {
                                if ($tokens[$x]['line'] === $tokens[$use_token]['line']) {
                                    // Preserve indent.
                                    continue;
                                }
                                $phpcs_file->fixer->replace_token($x, '');
                            }
                            $phpcs_file->fixer->add_newline($prev);
                            if ($tokens[$prev]['line'] === $tokens[$use_token]['line']) {
                                if ($tokens[$use_token - 1]['code'] === T_WHITESPACE) {
                                    $phpcs_file->fixer->replace_token($use_token - 1, '');
                                }
                                $padding = str_repeat(' ', $tokens[$use_tokens[0]]['column'] - 1);
                                $phpcs_file->fixer->add_content($prev, $padding);
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
            // Check the formatting of the statement.
            if (isset($tokens[$use_token]['scope_opener']) === true) {
                $this->process_use_group($phpcs_file, $use_token);
                $end = $tokens[$use_token]['scope_closer'];
            } else {
                $this->process_use_statement($phpcs_file, $use_token);
                $end = $phpcs_file->find_next(T_SEMICOLON, $use_token + 1);
                if ($end === false) {
                    // Syntax error.
                    return;
                }
            }
            if ($use_pos === $num_use_tokens - 1) {
                /*
                    This is the last use statement.
                */
                $next = $phpcs_file->find_next(T_WHITESPACE, $end + 1, null, true);
                if ($next === $tokens[$oo_token]['scope_closer']) {
                    // Last content in the class.
                    $closer = $tokens[$oo_token]['scope_closer'];
                    if ($tokens[$closer]['line'] > $tokens[$end]['line'] + 1) {
                        $error = 'There must be no blank line after the last trait import statement at the bottom of a %s';
                        $data = [strtolower($tokens[$oo_token]['content'])];
                        $fix = $phpcs_file->add_fixable_error($error, $end, 'BlankLineAfterLastUse', $data);
                        if ($fix === true) {
                            $phpcs_file->fixer->begin_changeset();
                            for ($i = $end + 1; $i < $closer; $i++) {
                                if ($tokens[$i]['line'] === $tokens[$end]['line']) {
                                    continue;
                                }
                                if ($tokens[$i]['line'] === $tokens[$closer]['line']) {
                                    // Don't remove indents.
                                    break;
                                }
                                $phpcs_file->fixer->replace_token($i, '');
                            }
                            $phpcs_file->fixer->end_changeset();
                        }
                    }
                    //end if
                } elseif ($tokens[$next]['code'] !== T_USE) {
                    // Comments are allowed on the same line as the use statement, so make sure
                    // we don't error for those.
                    for ($next = $end + 1; $next < $tokens[$oo_token]['scope_closer']; $next++) {
                        if ($tokens[$next]['code'] === T_WHITESPACE) {
                            continue;
                        }
                        if (isset(Tokens::$comment_tokens[$tokens[$next]['code']]) === true && $tokens[$next]['line'] === $tokens[$end]['line']) {
                            continue;
                        }
                        break;
                    }
                    if ($tokens[$next]['line'] <= $tokens[$end]['line'] + 1) {
                        $error = 'There must be a blank line following the last trait import statement';
                        $fix = $phpcs_file->add_fixable_error($error, $end, 'NoBlankLineAfterUse');
                        if ($fix === true) {
                            if ($tokens[$next]['line'] === $tokens[$use_token]['line']) {
                                $phpcs_file->fixer->add_content_before($next, $phpcs_file->eol_char . $phpcs_file->eol_char);
                            } else {
                                for ($i = $next - 1; $i > $end; $i--) {
                                    if ($tokens[$i]['line'] !== $tokens[$next]['line']) {
                                        break;
                                    }
                                }
                                $phpcs_file->fixer->add_newline_before($i + 1);
                            }
                        }
                    }
                }
                //end if
            } else {
                // Ensure use statements are grouped.
                $next = $phpcs_file->find_next(Tokens::$empty_tokens, $end + 1, null, true);
                if ($next !== $use_tokens[$use_pos + 1]) {
                    $error = 'Imported traits must be grouped together';
                    $phpcs_file->add_error($error, $use_tokens[$use_pos + 1], 'NotGrouped');
                }
            }
            //end if
        }
        //end foreach
        return $tokens[$oo_token]['scope_closer'];
    }
    //end process()
    /**
     * Processes a group use statement.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token in the
     *                                               stack passed in $tokens.
     *
     * @return void
     */
    protected function process_use_group(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        $opener = $tokens[$stack_ptr]['scope_opener'];
        $closer = $tokens[$stack_ptr]['scope_closer'];
        if ($tokens[$opener]['line'] !== $tokens[$stack_ptr]['line']) {
            $error = 'The opening brace of a trait import statement must be on the same line as the USE keyword';
            // Figure out if we can fix this error.
            $can_fix = true;
            for ($i = $stack_ptr + 1; $i < $opener; $i++) {
                if ($tokens[$i]['line'] !== $tokens[$i + 1]['line'] && $tokens[$i]['code'] !== T_WHITESPACE) {
                    $can_fix = false;
                    break;
                }
            }
            if ($can_fix === true) {
                $fix = $phpcs_file->add_fixable_error($error, $opener, 'OpenBraceNewLine');
                if ($fix === true) {
                    $phpcs_file->fixer->begin_changeset();
                    for ($i = $stack_ptr + 1; $i < $opener; $i++) {
                        if ($tokens[$i]['line'] !== $tokens[$i + 1]['line']) {
                            // Everything should have a single space around it.
                            $phpcs_file->fixer->replace_token($i, ' ');
                        }
                    }
                    $phpcs_file->fixer->end_changeset();
                }
            } else {
                $phpcs_file->add_error($error, $opener, 'OpenBraceNewLine');
            }
        }
        //end if
        $error = 'Expected 1 space before opening brace in trait import statement; %s found';
        if ($tokens[$opener - 1]['code'] !== T_WHITESPACE) {
            $data = ['0'];
            $fix = $phpcs_file->add_fixable_error($error, $opener, 'SpaceBeforeOpeningBrace', $data);
            if ($fix === true) {
                $phpcs_file->fixer->add_content_before($opener, ' ');
            }
        } elseif ($tokens[$opener - 1]['content'] !== ' ') {
            $prev = $phpcs_file->find_previous(T_WHITESPACE, $opener - 1, null, true);
            if ($tokens[$prev]['line'] !== $tokens[$opener]['line']) {
                $found = 'newline';
            } else {
                $found = $tokens[$opener - 1]['length'];
            }
            $data = [$found];
            $fix = $phpcs_file->add_fixable_error($error, $opener, 'SpaceBeforeOpeningBrace', $data);
            if ($fix === true) {
                if ($found === 'newline') {
                    $phpcs_file->fixer->begin_changeset();
                    for ($x = $opener - 1; $x > $prev; $x--) {
                        $phpcs_file->fixer->replace_token($x, '');
                    }
                    $phpcs_file->fixer->add_content_before($opener, ' ');
                    $phpcs_file->fixer->end_changeset();
                } else {
                    $phpcs_file->fixer->replace_token($opener - 1, ' ');
                }
            }
        }
        //end if
        $next = $phpcs_file->find_next(Tokens::$empty_tokens, $opener + 1, $closer - 1, true);
        if ($next !== false && $tokens[$next]['line'] !== $tokens[$opener]['line'] + 1) {
            $error = 'First trait conflict resolution statement must be on the line after the opening brace';
            $next_non_ws = $phpcs_file->find_next(T_WHITESPACE, $opener + 1, $closer - 1, true);
            if ($next_non_ws !== $next) {
                $phpcs_file->add_error($error, $opener, 'SpaceAfterOpeningBrace');
            } else {
                $fix = $phpcs_file->add_fixable_error($error, $opener, 'SpaceAfterOpeningBrace');
                if ($fix === true) {
                    $phpcs_file->fixer->begin_changeset();
                    for ($x = $opener + 1; $x < $next; $x++) {
                        if ($tokens[$x]['line'] === $tokens[$next]['line']) {
                            // Preserve indent.
                            break;
                        }
                        $phpcs_file->fixer->replace_token($x, '');
                    }
                    $phpcs_file->fixer->add_newline($opener);
                    $phpcs_file->fixer->end_changeset();
                }
            }
        }
        //end if
        for ($i = $stack_ptr + 1; $i < $opener; $i++) {
            if ($tokens[$i]['code'] !== T_COMMA) {
                continue;
            }
            if ($tokens[$i - 1]['code'] === T_WHITESPACE) {
                $error = 'Expected no space before comma in trait import statement; %s found';
                $data = [$tokens[$i - 1]['length']];
                $fix = $phpcs_file->add_fixable_error($error, $i, 'SpaceBeforeComma', $data);
                if ($fix === true) {
                    $phpcs_file->fixer->replace_token($i - 1, '');
                }
            }
            $error = 'Expected 1 space after comma in trait import statement; %s found';
            if ($tokens[$i + 1]['code'] !== T_WHITESPACE) {
                $data = ['0'];
                $fix = $phpcs_file->add_fixable_error($error, $i, 'SpaceAfterComma', $data);
                if ($fix === true) {
                    $phpcs_file->fixer->add_content($i, ' ');
                }
            } elseif ($tokens[$i + 1]['content'] !== ' ') {
                $next = $phpcs_file->find_next(T_WHITESPACE, $i + 1, $opener, true);
                if ($tokens[$next]['line'] !== $tokens[$i]['line']) {
                    $found = 'newline';
                } else {
                    $found = $tokens[$i + 1]['length'];
                }
                $data = [$found];
                $fix = $phpcs_file->add_fixable_error($error, $i, 'SpaceAfterComma', $data);
                if ($fix === true) {
                    if ($found === 'newline') {
                        $phpcs_file->fixer->begin_changeset();
                        for ($x = $i + 1; $x < $next; $x++) {
                            $phpcs_file->fixer->replace_token($x, '');
                        }
                        $phpcs_file->fixer->add_content($i, ' ');
                        $phpcs_file->fixer->end_changeset();
                    } else {
                        $phpcs_file->fixer->replace_token($i + 1, ' ');
                    }
                }
            }
            //end if
        }
        //end for
        for ($i = $opener + 1; $i < $closer; $i++) {
            if ($tokens[$i]['code'] === T_INSTEADOF) {
                $error = 'Expected 1 space before INSTEADOF in trait import statement; %s found';
                if ($tokens[$i - 1]['code'] !== T_WHITESPACE) {
                    $data = ['0'];
                    $fix = $phpcs_file->add_fixable_error($error, $i, 'SpaceBeforeInsteadof', $data);
                    if ($fix === true) {
                        $phpcs_file->fixer->add_content_before($i, ' ');
                    }
                } elseif ($tokens[$i - 1]['content'] !== ' ') {
                    $prev = $phpcs_file->find_previous(T_WHITESPACE, $i - 1, $opener, true);
                    if ($tokens[$prev]['line'] !== $tokens[$i]['line']) {
                        $found = 'newline';
                    } else {
                        $found = $tokens[$i - 1]['length'];
                    }
                    $data = [$found];
                    $prev_non_ws = $phpcs_file->find_previous(Tokens::$empty_tokens, $i - 1, $opener, true);
                    if ($prev_non_ws !== $prev) {
                        $phpcs_file->add_error($error, $i, 'SpaceBeforeInsteadof', $data);
                    } else {
                        $fix = $phpcs_file->add_fixable_error($error, $i, 'SpaceBeforeInsteadof', $data);
                        if ($fix === true) {
                            if ($found === 'newline') {
                                $phpcs_file->fixer->begin_changeset();
                                for ($x = $i - 1; $x > $prev; $x--) {
                                    $phpcs_file->fixer->replace_token($x, '');
                                }
                                $phpcs_file->fixer->add_content_before($i, ' ');
                                $phpcs_file->fixer->end_changeset();
                            } else {
                                $phpcs_file->fixer->replace_token($i - 1, ' ');
                            }
                        }
                    }
                }
                //end if
                $error = 'Expected 1 space after INSTEADOF in trait import statement; %s found';
                if ($tokens[$i + 1]['code'] !== T_WHITESPACE) {
                    $data = ['0'];
                    $fix = $phpcs_file->add_fixable_error($error, $i, 'SpaceAfterInsteadof', $data);
                    if ($fix === true) {
                        $phpcs_file->fixer->add_content($i, ' ');
                    }
                } elseif ($tokens[$i + 1]['content'] !== ' ') {
                    $next = $phpcs_file->find_next(T_WHITESPACE, $i + 1, $closer, true);
                    if ($tokens[$next]['line'] !== $tokens[$i]['line']) {
                        $found = 'newline';
                    } else {
                        $found = $tokens[$i + 1]['length'];
                    }
                    $data = [$found];
                    $next_non_ws = $phpcs_file->find_next(Tokens::$empty_tokens, $i + 1, $closer, true);
                    if ($next_non_ws !== $next) {
                        $phpcs_file->add_error($error, $i, 'SpaceAfterInsteadof', $data);
                    } else {
                        $fix = $phpcs_file->add_fixable_error($error, $i, 'SpaceAfterInsteadof', $data);
                        if ($fix === true) {
                            if ($found === 'newline') {
                                $phpcs_file->fixer->begin_changeset();
                                for ($x = $i + 1; $x < $next; $x++) {
                                    $phpcs_file->fixer->replace_token($x, '');
                                }
                                $phpcs_file->fixer->add_content($i, ' ');
                                $phpcs_file->fixer->end_changeset();
                            } else {
                                $phpcs_file->fixer->replace_token($i + 1, ' ');
                            }
                        }
                    }
                }
                //end if
            }
            //end if
            if ($tokens[$i]['code'] === T_AS) {
                $error = 'Expected 1 space before AS in trait import statement; %s found';
                if ($tokens[$i - 1]['code'] !== T_WHITESPACE) {
                    $data = ['0'];
                    $fix = $phpcs_file->add_fixable_error($error, $i, 'SpaceBeforeAs', $data);
                    if ($fix === true) {
                        $phpcs_file->fixer->add_content_before($i, ' ');
                    }
                } elseif ($tokens[$i - 1]['content'] !== ' ') {
                    $prev = $phpcs_file->find_previous(T_WHITESPACE, $i - 1, $opener, true);
                    if ($tokens[$prev]['line'] !== $tokens[$i]['line']) {
                        $found = 'newline';
                    } else {
                        $found = $tokens[$i - 1]['length'];
                    }
                    $data = [$found];
                    $prev_non_ws = $phpcs_file->find_previous(Tokens::$empty_tokens, $i - 1, $opener, true);
                    if ($prev_non_ws !== $prev) {
                        $phpcs_file->add_error($error, $i, 'SpaceBeforeAs', $data);
                    } else {
                        $fix = $phpcs_file->add_fixable_error($error, $i, 'SpaceBeforeAs', $data);
                        if ($fix === true) {
                            if ($found === 'newline') {
                                $phpcs_file->fixer->begin_changeset();
                                for ($x = $i - 1; $x > $prev; $x--) {
                                    $phpcs_file->fixer->replace_token($x, '');
                                }
                                $phpcs_file->fixer->add_content_before($i, ' ');
                                $phpcs_file->fixer->end_changeset();
                            } else {
                                $phpcs_file->fixer->replace_token($i - 1, ' ');
                            }
                        }
                    }
                }
                //end if
                $error = 'Expected 1 space after AS in trait import statement; %s found';
                if ($tokens[$i + 1]['code'] !== T_WHITESPACE) {
                    $data = ['0'];
                    $fix = $phpcs_file->add_fixable_error($error, $i, 'SpaceAfterAs', $data);
                    if ($fix === true) {
                        $phpcs_file->fixer->add_content($i, ' ');
                    }
                } elseif ($tokens[$i + 1]['content'] !== ' ') {
                    $next = $phpcs_file->find_next(T_WHITESPACE, $i + 1, $closer, true);
                    if ($tokens[$next]['line'] !== $tokens[$i]['line']) {
                        $found = 'newline';
                    } else {
                        $found = $tokens[$i + 1]['length'];
                    }
                    $data = [$found];
                    $next_non_ws = $phpcs_file->find_next(Tokens::$empty_tokens, $i + 1, $closer, true);
                    if ($next_non_ws !== $next) {
                        $phpcs_file->add_error($error, $i, 'SpaceAfterAs', $data);
                    } else {
                        $fix = $phpcs_file->add_fixable_error($error, $i, 'SpaceAfterAs', $data);
                        if ($fix === true) {
                            if ($found === 'newline') {
                                $phpcs_file->fixer->begin_changeset();
                                for ($x = $i + 1; $x < $next; $x++) {
                                    $phpcs_file->fixer->replace_token($x, '');
                                }
                                $phpcs_file->fixer->add_content($i, ' ');
                                $phpcs_file->fixer->end_changeset();
                            } else {
                                $phpcs_file->fixer->replace_token($i + 1, ' ');
                            }
                        }
                    }
                }
                //end if
            }
            //end if
            if ($tokens[$i]['code'] === T_SEMICOLON) {
                if ($tokens[$i - 1]['code'] === T_WHITESPACE) {
                    $error = 'Expected no space before semicolon in trait import statement; %s found';
                    $data = [$tokens[$i - 1]['length']];
                    $fix = $phpcs_file->add_fixable_error($error, $i, 'SpaceBeforeSemicolon', $data);
                    if ($fix === true) {
                        $phpcs_file->fixer->replace_token($i - 1, '');
                    }
                }
                $next = $phpcs_file->find_next(Tokens::$empty_tokens, $i + 1, $closer - 1, true);
                if ($next !== false && $tokens[$next]['line'] === $tokens[$i]['line']) {
                    $error = 'Each trait conflict resolution statement must be on a line by itself';
                    $next_non_ws = $phpcs_file->find_next(T_WHITESPACE, $i + 1, $closer - 1, true);
                    if ($next_non_ws !== $next) {
                        $phpcs_file->add_error($error, $i, 'ConflictSameLine');
                    } else {
                        $fix = $phpcs_file->add_fixable_error($error, $i, 'ConflictSameLine');
                        if ($fix === true) {
                            $phpcs_file->fixer->begin_changeset();
                            if ($tokens[$i + 1]['code'] === T_WHITESPACE) {
                                $phpcs_file->fixer->replace_token($i + 1, '');
                            }
                            $phpcs_file->fixer->add_newline($i);
                            $phpcs_file->fixer->end_changeset();
                        }
                    }
                }
            }
            //end if
        }
        //end for
        $prev = $phpcs_file->find_previous(Tokens::$empty_tokens, $closer - 1, $opener + 1, true);
        if ($prev !== false && $tokens[$prev]['line'] !== $tokens[$closer]['line'] - 1) {
            $error = 'Closing brace must be on the line after the last trait conflict resolution statement';
            $prev_non_ws = $phpcs_file->find_previous(T_WHITESPACE, $closer - 1, $opener + 1, true);
            if ($prev_non_ws !== $prev) {
                $phpcs_file->add_error($error, $closer, 'SpaceBeforeClosingBrace');
            } else {
                $fix = $phpcs_file->add_fixable_error($error, $closer, 'SpaceBeforeClosingBrace');
                if ($fix === true) {
                    $phpcs_file->fixer->begin_changeset();
                    for ($x = $closer - 1; $x > $prev; $x--) {
                        if ($tokens[$x]['line'] === $tokens[$closer]['line']) {
                            // Preserve indent.
                            continue;
                        }
                        $phpcs_file->fixer->replace_token($x, '');
                    }
                    $phpcs_file->fixer->add_newline($prev);
                    $phpcs_file->fixer->end_changeset();
                }
            }
        }
        //end if
    }
    //end processUseGroup()
    /**
     * Processes a single use statement.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token in the
     *                                               stack passed in $tokens.
     *
     * @return void
     */
    protected function process_use_statement(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        $error = 'Expected 1 space after USE in trait import statement; %s found';
        if ($tokens[$stack_ptr + 1]['code'] !== T_WHITESPACE) {
            $data = ['0'];
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'SpaceAfterAs', $data);
            if ($fix === true) {
                $phpcs_file->fixer->add_content($stack_ptr, ' ');
            }
        } elseif ($tokens[$stack_ptr + 1]['content'] !== ' ') {
            $next = $phpcs_file->find_next(T_WHITESPACE, $stack_ptr + 1, null, true);
            if ($tokens[$next]['line'] !== $tokens[$stack_ptr]['line']) {
                $found = 'newline';
            } else {
                $found = $tokens[$stack_ptr + 1]['length'];
            }
            $data = [$found];
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'SpaceAfterAs', $data);
            if ($fix === true) {
                if ($found === 'newline') {
                    $phpcs_file->fixer->begin_changeset();
                    for ($x = $stack_ptr + 1; $x < $next; $x++) {
                        $phpcs_file->fixer->replace_token($x, '');
                    }
                    $phpcs_file->fixer->add_content($stack_ptr, ' ');
                    $phpcs_file->fixer->end_changeset();
                } else {
                    $phpcs_file->fixer->replace_token($stack_ptr + 1, ' ');
                }
            }
        }
        //end if
        $next = $phpcs_file->find_next([T_COMMA, T_SEMICOLON], $stack_ptr + 1);
        if ($next !== false && $tokens[$next]['code'] === T_COMMA) {
            $error = 'Each imported trait must have its own "use" import statement';
            $fix = $phpcs_file->add_fixable_error($error, $next, 'MultipleImport');
            if ($fix === true) {
                $padding = str_repeat(' ', $tokens[$stack_ptr]['column'] - 1);
                $phpcs_file->fixer->replace_token($next, ';' . $phpcs_file->eol_char . $padding . 'use ');
            }
        }
    }
    //end processUseStatement()
}
//end class