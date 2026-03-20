<?php

declare (strict_types=1);
/**
 * Verifies that there is a space between each condition of foreach loops.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\Control_Structures;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class For_Each_Loop_Declaration_Sniff implements Sniff
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
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_FOREACH];
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
            $error = 'Possible parse error: FOREACH has no opening parenthesis';
            $phpcs_file->add_warning($error, $stack_ptr, 'MissingOpenParenthesis');
            return;
        }
        if (isset($tokens[$opening_bracket]['parenthesis_closer']) === false) {
            $error = 'Possible parse error: FOREACH has no closing parenthesis';
            $phpcs_file->add_warning($error, $stack_ptr, 'MissingCloseParenthesis');
            return;
        }
        $closing_bracket = $tokens[$opening_bracket]['parenthesis_closer'];
        if ($this->required_spaces_after_open === 0 && $tokens[$opening_bracket + 1]['code'] === T_WHITESPACE) {
            $error = 'Space found after opening bracket of FOREACH loop';
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'SpaceAfterOpen');
            if ($fix === true) {
                $phpcs_file->fixer->replace_token($opening_bracket + 1, '');
            }
        } elseif ($this->required_spaces_after_open > 0) {
            $space_after_open = 0;
            if ($tokens[$opening_bracket + 1]['code'] === T_WHITESPACE) {
                $space_after_open = $tokens[$opening_bracket + 1]['length'];
            }
            if ($space_after_open !== $this->required_spaces_after_open) {
                $error = 'Expected %s spaces after opening bracket; %s found';
                $data = [$this->required_spaces_after_open, $space_after_open];
                $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'SpacingAfterOpen', $data);
                if ($fix === true) {
                    $padding = str_repeat(' ', $this->required_spaces_after_open);
                    if ($space_after_open === 0) {
                        $phpcs_file->fixer->add_content($opening_bracket, $padding);
                    } else {
                        $phpcs_file->fixer->replace_token($opening_bracket + 1, $padding);
                    }
                }
            }
        }
        //end if
        if ($this->required_spaces_before_close === 0 && $tokens[$closing_bracket - 1]['code'] === T_WHITESPACE) {
            $error = 'Space found before closing bracket of FOREACH loop';
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'SpaceBeforeClose');
            if ($fix === true) {
                $phpcs_file->fixer->replace_token($closing_bracket - 1, '');
            }
        } elseif ($this->required_spaces_before_close > 0) {
            $space_before_close = 0;
            if ($tokens[$closing_bracket - 1]['code'] === T_WHITESPACE) {
                $space_before_close = $tokens[$closing_bracket - 1]['length'];
            }
            if ($space_before_close !== $this->required_spaces_before_close) {
                $error = 'Expected %s spaces before closing bracket; %s found';
                $data = [$this->required_spaces_before_close, $space_before_close];
                $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'SpaceBeforeClose', $data);
                if ($fix === true) {
                    $padding = str_repeat(' ', $this->required_spaces_before_close);
                    if ($space_before_close === 0) {
                        $phpcs_file->fixer->add_content_before($closing_bracket, $padding);
                    } else {
                        $phpcs_file->fixer->replace_token($closing_bracket - 1, $padding);
                    }
                }
            }
        }
        //end if
        $as_token = $phpcs_file->find_next(T_AS, $opening_bracket);
        if ($as_token === false) {
            $error = 'Possible parse error: FOREACH has no AS statement';
            $phpcs_file->add_warning($error, $stack_ptr, 'MissingAs');
            return;
        }
        $content = $tokens[$as_token]['content'];
        if ($content !== strtolower($content)) {
            $expected = strtolower($content);
            $error = 'AS keyword must be lowercase; expected "%s" but found "%s"';
            $data = [$expected, $content];
            $fix = $phpcs_file->add_fixable_error($error, $as_token, 'AsNotLower', $data);
            if ($fix === true) {
                $phpcs_file->fixer->replace_token($as_token, $expected);
            }
        }
        $double_arrow = $phpcs_file->find_next(T_DOUBLE_ARROW, $as_token, $closing_bracket);
        if ($double_arrow !== false) {
            if ($tokens[$double_arrow - 1]['code'] !== T_WHITESPACE) {
                $error = 'Expected 1 space before "=>"; 0 found';
                $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'NoSpaceBeforeArrow');
                if ($fix === true) {
                    $phpcs_file->fixer->add_content_before($double_arrow, ' ');
                }
            } else if ($tokens[$double_arrow - 1]['length'] !== 1) {
                $spaces = $tokens[$double_arrow - 1]['length'];
                $error = 'Expected 1 space before "=>"; %s found';
                $data = [$spaces];
                $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'SpacingBeforeArrow', $data);
                if ($fix === true) {
                    $phpcs_file->fixer->replace_token($double_arrow - 1, ' ');
                }
            }
            if ($tokens[$double_arrow + 1]['code'] !== T_WHITESPACE) {
                $error = 'Expected 1 space after "=>"; 0 found';
                $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'NoSpaceAfterArrow');
                if ($fix === true) {
                    $phpcs_file->fixer->add_content($double_arrow, ' ');
                }
            } else if ($tokens[$double_arrow + 1]['length'] !== 1) {
                $spaces = $tokens[$double_arrow + 1]['length'];
                $error = 'Expected 1 space after "=>"; %s found';
                $data = [$spaces];
                $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'SpacingAfterArrow', $data);
                if ($fix === true) {
                    $phpcs_file->fixer->replace_token($double_arrow + 1, ' ');
                }
            }
        }
        //end if
        if ($tokens[$as_token - 1]['code'] !== T_WHITESPACE) {
            $error = 'Expected 1 space before "as"; 0 found';
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'NoSpaceBeforeAs');
            if ($fix === true) {
                $phpcs_file->fixer->add_content_before($as_token, ' ');
            }
        } else if ($tokens[$as_token - 1]['length'] !== 1) {
            $spaces = $tokens[$as_token - 1]['length'];
            $error = 'Expected 1 space before "as"; %s found';
            $data = [$spaces];
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'SpacingBeforeAs', $data);
            if ($fix === true) {
                $phpcs_file->fixer->replace_token($as_token - 1, ' ');
            }
        }
        if ($tokens[$as_token + 1]['code'] !== T_WHITESPACE) {
            $error = 'Expected 1 space after "as"; 0 found';
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'NoSpaceAfterAs');
            if ($fix === true) {
                $phpcs_file->fixer->add_content($as_token, ' ');
            }
        } else if ($tokens[$as_token + 1]['length'] !== 1) {
            $spaces = $tokens[$as_token + 1]['length'];
            $error = 'Expected 1 space after "as"; %s found';
            $data = [$spaces];
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'SpacingAfterAs', $data);
            if ($fix === true) {
                $phpcs_file->fixer->replace_token($as_token + 1, ' ');
            }
        }
    }
    //end process()
}
//end class