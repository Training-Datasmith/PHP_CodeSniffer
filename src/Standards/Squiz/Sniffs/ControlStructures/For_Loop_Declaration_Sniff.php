<?php

declare (strict_types=1);
/**
 * Verifies that there is a space between each condition of for loops.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\Control_Structures;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class For_Loop_Declaration_Sniff implements Sniff
{
    /**
     * How many spaces should follow the opening bracket.
     *
     * @var integer
     */
    public $required_spaces_after_open = 0;
    /**
     * How many spaces should precede the closing bracket.
     *
     * @var integer
     */
    public $required_spaces_before_close = 0;
    /**
     * Allow newlines instead of spaces.
     *
     * @var boolean
     */
    public $ignore_newlines = false;
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
        return [T_FOR];
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
        $this->required_spaces_after_open = (int) $this->required_spaces_after_open;
        $this->required_spaces_before_close = (int) $this->required_spaces_before_close;
        $tokens = $phpcs_file->get_tokens();
        $opening_bracket = $phpcs_file->find_next(T_OPEN_PARENTHESIS, $stack_ptr);
        if ($opening_bracket === false) {
            $error = 'Possible parse error: no opening parenthesis for FOR keyword';
            $phpcs_file->add_warning($error, $stack_ptr, 'NoOpenBracket');
            return;
        }
        $closing_bracket = $tokens[$opening_bracket]['parenthesis_closer'];
        if ($this->required_spaces_after_open === 0 && $tokens[$opening_bracket + 1]['code'] === T_WHITESPACE) {
            $next_non_white_space = $phpcs_file->find_next(T_WHITESPACE, $opening_bracket + 1, $closing_bracket, true);
            if ($this->ignore_newlines === false || $tokens[$next_non_white_space]['line'] === $tokens[$opening_bracket]['line']) {
                $error = 'Whitespace found after opening bracket of FOR loop';
                $fix = $phpcs_file->add_fixable_error($error, $opening_bracket, 'SpacingAfterOpen');
                if ($fix === true) {
                    $phpcs_file->fixer->begin_changeset();
                    for ($i = $opening_bracket + 1; $i < $closing_bracket; $i++) {
                        if ($tokens[$i]['code'] !== T_WHITESPACE) {
                            break;
                        }
                        $phpcs_file->fixer->replace_token($i, '');
                    }
                    $phpcs_file->fixer->end_changeset();
                }
            }
        } elseif ($this->required_spaces_after_open > 0) {
            $next_non_white_space = $phpcs_file->find_next(T_WHITESPACE, $opening_bracket + 1, $closing_bracket, true);
            $space_after_open = 0;
            if ($tokens[$opening_bracket]['line'] !== $tokens[$next_non_white_space]['line']) {
                $space_after_open = 'newline';
            } elseif ($tokens[$opening_bracket + 1]['code'] === T_WHITESPACE) {
                $space_after_open = $tokens[$opening_bracket + 1]['length'];
            }
            if ($space_after_open !== $this->required_spaces_after_open && ($this->ignore_newlines === false || $space_after_open !== 'newline')) {
                $error = 'Expected %s spaces after opening bracket; %s found';
                $data = [$this->required_spaces_after_open, $space_after_open];
                $fix = $phpcs_file->add_fixable_error($error, $opening_bracket, 'SpacingAfterOpen', $data);
                if ($fix === true) {
                    $padding = str_repeat(' ', $this->required_spaces_after_open);
                    if ($space_after_open === 0) {
                        $phpcs_file->fixer->add_content($opening_bracket, $padding);
                    } else {
                        $phpcs_file->fixer->begin_changeset();
                        $phpcs_file->fixer->replace_token($opening_bracket + 1, $padding);
                        for ($i = $opening_bracket + 2; $i < $next_non_white_space; $i++) {
                            $phpcs_file->fixer->replace_token($i, '');
                        }
                        $phpcs_file->fixer->end_changeset();
                    }
                }
            }
            //end if
        }
        //end if
        $prev_non_white_space = $phpcs_file->find_previous(T_WHITESPACE, $closing_bracket - 1, $opening_bracket, true);
        $before_closefixable = true;
        if ($tokens[$prev_non_white_space]['line'] !== $tokens[$closing_bracket]['line'] && isset(Tokens::$empty_tokens[$tokens[$prev_non_white_space]['code']]) === true) {
            $before_closefixable = false;
        }
        if ($this->required_spaces_before_close === 0 && $tokens[$closing_bracket - 1]['code'] === T_WHITESPACE && ($this->ignore_newlines === false || $tokens[$prev_non_white_space]['line'] === $tokens[$closing_bracket]['line'])) {
            $error = 'Whitespace found before closing bracket of FOR loop';
            if ($before_closefixable === false) {
                $phpcs_file->add_error($error, $closing_bracket, 'SpacingBeforeClose');
            } else {
                $fix = $phpcs_file->add_fixable_error($error, $closing_bracket, 'SpacingBeforeClose');
                if ($fix === true) {
                    $phpcs_file->fixer->begin_changeset();
                    for ($i = $closing_bracket - 1; $i > $opening_bracket; $i--) {
                        if ($tokens[$i]['code'] !== T_WHITESPACE) {
                            break;
                        }
                        $phpcs_file->fixer->replace_token($i, '');
                    }
                    $phpcs_file->fixer->end_changeset();
                }
            }
        } elseif ($this->required_spaces_before_close > 0) {
            $space_before_close = 0;
            if ($tokens[$closing_bracket]['line'] !== $tokens[$prev_non_white_space]['line']) {
                $space_before_close = 'newline';
            } elseif ($tokens[$closing_bracket - 1]['code'] === T_WHITESPACE) {
                $space_before_close = $tokens[$closing_bracket - 1]['length'];
            }
            if ($this->required_spaces_before_close !== $space_before_close && ($this->ignore_newlines === false || $space_before_close !== 'newline')) {
                $error = 'Expected %s spaces before closing bracket; %s found';
                $data = [$this->required_spaces_before_close, $space_before_close];
                if ($before_closefixable === false) {
                    $phpcs_file->add_error($error, $closing_bracket, 'SpacingBeforeClose', $data);
                } else {
                    $fix = $phpcs_file->add_fixable_error($error, $closing_bracket, 'SpacingBeforeClose', $data);
                    if ($fix === true) {
                        $padding = str_repeat(' ', $this->required_spaces_before_close);
                        if ($space_before_close === 0) {
                            $phpcs_file->fixer->add_content_before($closing_bracket, $padding);
                        } else {
                            $phpcs_file->fixer->begin_changeset();
                            $phpcs_file->fixer->replace_token($closing_bracket - 1, $padding);
                            for ($i = $closing_bracket - 2; $i > $prev_non_white_space; $i--) {
                                $phpcs_file->fixer->replace_token($i, '');
                            }
                            $phpcs_file->fixer->end_changeset();
                        }
                    }
                }
            }
            //end if
        }
        //end if
        /*
         * Check whitespace around each of the semicolon tokens.
         */
        $semicolon_count = 0;
        $semicolon = $opening_bracket;
        $target_nestinglevel = 0;
        if (isset($tokens[$opening_bracket]['conditions']) === true) {
            $target_nestinglevel = count($tokens[$opening_bracket]['conditions']);
        }
        do {
            $semicolon = $phpcs_file->find_next(T_SEMICOLON, $semicolon + 1, $closing_bracket);
            if ($semicolon === false) {
                break;
            }
            if (isset($tokens[$semicolon]['conditions']) === true && count($tokens[$semicolon]['conditions']) > $target_nestinglevel) {
                // Semicolon doesn't belong to the for().
                continue;
            }
            ++$semicolon_count;
            $human_readable_count = 'first';
            if ($semicolon_count !== 1) {
                $human_readable_count = 'second';
            }
            $human_readable_code = ucfirst($human_readable_count);
            $data = [$human_readable_count];
            // Only examine the space before the first semicolon if the first expression is not empty.
            // If it *is* empty, leave it up to the `SpacingAfterOpen` logic.
            $prev_non_white_space = $phpcs_file->find_previous(T_WHITESPACE, $semicolon - 1, $opening_bracket, true);
            if ($semicolon_count !== 1 || $prev_non_white_space !== $opening_bracket) {
                if ($tokens[$semicolon - 1]['code'] === T_WHITESPACE) {
                    $error = 'Whitespace found before %s semicolon of FOR loop';
                    $error_code = 'SpacingBefore' . $human_readable_code;
                    $fix = $phpcs_file->add_fixable_error($error, $semicolon, $error_code, $data);
                    if ($fix === true) {
                        $phpcs_file->fixer->begin_changeset();
                        for ($i = $semicolon - 1; $i > $prev_non_white_space; $i--) {
                            $phpcs_file->fixer->replace_token($i, '');
                        }
                        $phpcs_file->fixer->end_changeset();
                    }
                }
            }
            // Only examine the space after the second semicolon if the last expression is not empty.
            // If it *is* empty, leave it up to the `SpacingBeforeClose` logic.
            $next_non_white_space = $phpcs_file->find_next(T_WHITESPACE, $semicolon + 1, $closing_bracket + 1, true);
            if ($semicolon_count !== 2 || $next_non_white_space !== $closing_bracket) {
                if ($tokens[$semicolon + 1]['code'] !== T_WHITESPACE && $tokens[$semicolon + 1]['code'] !== T_SEMICOLON) {
                    $error = 'Expected 1 space after %s semicolon of FOR loop; 0 found';
                    $error_code = 'NoSpaceAfter' . $human_readable_code;
                    $fix = $phpcs_file->add_fixable_error($error, $semicolon, $error_code, $data);
                    if ($fix === true) {
                        $phpcs_file->fixer->add_content($semicolon, ' ');
                    }
                } elseif ($tokens[$semicolon + 1]['code'] === T_WHITESPACE && $tokens[$next_non_white_space]['code'] !== T_SEMICOLON) {
                    $spaces = $tokens[$semicolon + 1]['length'];
                    if ($tokens[$semicolon]['line'] !== $tokens[$next_non_white_space]['line']) {
                        $spaces = 'newline';
                    }
                    if ($spaces !== 1 && ($this->ignore_newlines === false || $spaces !== 'newline')) {
                        $error = 'Expected 1 space after %s semicolon of FOR loop; %s found';
                        $error_code = 'SpacingAfter' . $human_readable_code;
                        $data[] = $spaces;
                        $fix = $phpcs_file->add_fixable_error($error, $semicolon, $error_code, $data);
                        if ($fix === true) {
                            $phpcs_file->fixer->begin_changeset();
                            $phpcs_file->fixer->replace_token($semicolon + 1, ' ');
                            for ($i = $semicolon + 2; $i < $next_non_white_space; $i++) {
                                $phpcs_file->fixer->replace_token($i, '');
                            }
                            $phpcs_file->fixer->end_changeset();
                        }
                    }
                }
                //end if
            }
            //end if
        } while ($semicolon_count < 2);
    }
    //end process()
}
//end class