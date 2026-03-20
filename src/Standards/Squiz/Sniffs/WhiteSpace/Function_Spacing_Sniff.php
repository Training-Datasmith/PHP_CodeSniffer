<?php

declare (strict_types=1);
/**
 * Checks the separation between functions and methods.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\White_Space;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Function_Spacing_Sniff implements Sniff
{
    /**
     * The number of blank lines between functions.
     *
     * @var integer
     */
    public $spacing = 2;
    /**
     * The number of blank lines before the first function in a class.
     *
     * @var integer
     */
    public $spacing_before_first = 2;
    /**
     * The number of blank lines after the last function in a class.
     *
     * @var integer
     */
    public $spacing_after_last = 2;
    /**
     * Original properties as set in a custom ruleset (if any).
     *
     * @var array|null
     */
    private $ruleset_properties;
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_FUNCTION];
    }
    //end register()
    /**
     * Processes this sniff when one of its tokens is encountered.
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
        $previous_non_empty = $phpcs_file->find_previous(Tokens::$empty_tokens, $stack_ptr - 1, null, true);
        if ($previous_non_empty !== false && $tokens[$previous_non_empty]['code'] === T_OPEN_TAG && $tokens[$previous_non_empty]['line'] !== 1) {
            // Ignore functions at the start of an embedded PHP block.
            return;
        }
        // If the ruleset has only overridden the spacing property, use
        // that value for all spacing rules.
        if ($this->ruleset_properties === null) {
            $this->ruleset_properties = [];
            if (isset($phpcs_file->ruleset->ruleset['Squiz.WhiteSpace.FunctionSpacing']) === true && isset($phpcs_file->ruleset->ruleset['Squiz.WhiteSpace.FunctionSpacing']['properties']) === true) {
                $this->ruleset_properties = $phpcs_file->ruleset->ruleset['Squiz.WhiteSpace.FunctionSpacing']['properties'];
                if (isset($this->ruleset_properties['spacing']) === true) {
                    if (isset($this->ruleset_properties['spacingBeforeFirst']) === false) {
                        $this->spacing_before_first = $this->spacing;
                    }
                    if (isset($this->ruleset_properties['spacingAfterLast']) === false) {
                        $this->spacing_after_last = $this->spacing;
                    }
                }
            }
        }
        $this->spacing = (int) $this->spacing;
        $this->spacing_before_first = (int) $this->spacing_before_first;
        $this->spacing_after_last = (int) $this->spacing_after_last;
        if (isset($tokens[$stack_ptr]['scope_closer']) === false) {
            // Must be an interface method, so the closer is the semicolon.
            $closer = $phpcs_file->find_next(T_SEMICOLON, $stack_ptr);
        } else {
            $closer = $tokens[$stack_ptr]['scope_closer'];
        }
        $is_first = false;
        $is_last = false;
        $ignore = [T_WHITESPACE => T_WHITESPACE] + Tokens::$method_prefixes;
        $prev = $phpcs_file->find_previous($ignore, $stack_ptr - 1, null, true);
        while ($tokens[$prev]['code'] === T_ATTRIBUTE_END) {
            // Skip past function attributes.
            $prev = $phpcs_file->find_previous($ignore, $tokens[$prev]['attribute_opener'] - 1, null, true);
        }
        if ($tokens[$prev]['code'] === T_DOC_COMMENT_CLOSE_TAG) {
            // Skip past function docblocks.
            $prev = $phpcs_file->find_previous($ignore, $tokens[$prev]['comment_opener'] - 1, null, true);
        }
        if ($tokens[$prev]['code'] === T_OPEN_CURLY_BRACKET) {
            $is_first = true;
        }
        $next = $phpcs_file->find_next($ignore, $closer + 1, null, true);
        if (isset(Tokens::$empty_tokens[$tokens[$next]['code']]) === true && $tokens[$next]['line'] === $tokens[$closer]['line']) {
            // Skip past "end" comments.
            $next = $phpcs_file->find_next($ignore, $next + 1, null, true);
        }
        if ($tokens[$next]['code'] === T_CLOSE_CURLY_BRACKET) {
            $is_last = true;
        }
        /*
            Check the number of blank lines
            after the function.
        */
        // Allow for comments on the same line as the closer.
        for ($next_line_token = $closer + 1; $next_line_token < $phpcs_file->num_tokens; $next_line_token++) {
            if ($tokens[$next_line_token]['line'] !== $tokens[$closer]['line']) {
                break;
            }
        }
        $required_spacing = $this->spacing;
        $error_code = 'After';
        if ($is_last === true) {
            $required_spacing = $this->spacing_after_last;
            $error_code = 'AfterLast';
        }
        $found_lines = 0;
        if ($next_line_token === $phpcs_file->num_tokens - 1) {
            // We are at the end of the file.
            // Don't check spacing after the function because this
            // should be done by an EOF sniff.
            $found_lines = $required_spacing;
        } else {
            $next_content = $phpcs_file->find_next(T_WHITESPACE, $next_line_token, null, true);
            if ($next_content === false) {
                // We are at the end of the file.
                // Don't check spacing after the function because this
                // should be done by an EOF sniff.
                $found_lines = $required_spacing;
            } else {
                $found_lines = $tokens[$next_content]['line'] - $tokens[$next_line_token]['line'];
            }
        }
        if ($is_last === true) {
            $phpcs_file->record_metric($stack_ptr, 'Function spacing after last', $found_lines);
        } else {
            $phpcs_file->record_metric($stack_ptr, 'Function spacing after', $found_lines);
        }
        if ($found_lines !== $required_spacing) {
            $error = 'Expected %s blank line';
            if ($required_spacing !== 1) {
                $error .= 's';
            }
            $error .= ' after function; %s found';
            $data = [$required_spacing, $found_lines];
            $fix = $phpcs_file->add_fixable_error($error, $closer, $error_code, $data);
            if ($fix === true) {
                $phpcs_file->fixer->begin_changeset();
                for ($i = $next_line_token; $i <= $next_content; $i++) {
                    if ($tokens[$i]['line'] === $tokens[$next_content]['line']) {
                        $phpcs_file->fixer->add_content_before($i, str_repeat($phpcs_file->eol_char, $required_spacing));
                        break;
                    }
                    $phpcs_file->fixer->replace_token($i, '');
                }
                $phpcs_file->fixer->end_changeset();
            }
            //end if
        }
        //end if
        /*
            Check the number of blank lines
            before the function.
        */
        $prev_line_token = null;
        for ($i = $stack_ptr; $i >= 0; $i--) {
            if ($tokens[$i]['line'] === $tokens[$stack_ptr]['line']) {
                continue;
            }
            $prev_line_token = $i;
            break;
        }
        if ($prev_line_token === null) {
            // Never found the previous line, which means
            // there are 0 blank lines before the function.
            $found_lines = 0;
            $prev_content = 0;
            $prev_line_token = 0;
        } else {
            $current_line = $tokens[$stack_ptr]['line'];
            $prev_content = $phpcs_file->find_previous(T_WHITESPACE, $prev_line_token, null, true);
            if ($tokens[$prev_content]['code'] === T_COMMENT || isset(Tokens::$phpcs_comment_tokens[$tokens[$prev_content]['code']]) === true) {
                // Ignore comments as they can have different spacing rules, and this
                // isn't a proper function comment anyway.
                return;
            }
            while ($tokens[$prev_content]['code'] === T_ATTRIBUTE_END && $tokens[$prev_content]['line'] === $current_line - 1) {
                // Account for function attributes.
                $current_line = $tokens[$tokens[$prev_content]['attribute_opener']]['line'];
                $prev_content = $phpcs_file->find_previous(T_WHITESPACE, $tokens[$prev_content]['attribute_opener'] - 1, null, true);
            }
            if ($tokens[$prev_content]['code'] === T_DOC_COMMENT_CLOSE_TAG && $tokens[$prev_content]['line'] === $current_line - 1) {
                // Account for function comments.
                $prev_content = $phpcs_file->find_previous(T_WHITESPACE, $tokens[$prev_content]['comment_opener'] - 1, null, true);
            }
            $prev_line_token = $prev_content;
            // Before we throw an error, check that we are not throwing an error
            // for another function. We don't want to error for no blank lines after
            // the previous function and no blank lines before this one as well.
            $prev_line = $tokens[$prev_content]['line'] - 1;
            $i = $stack_ptr - 1;
            $found_lines = 0;
            $stop_at = 0;
            if (isset($tokens[$stack_ptr]['conditions']) === true) {
                $conditions = $tokens[$stack_ptr]['conditions'];
                $conditions = array_keys($conditions);
                $stop_at = array_pop($conditions);
            }
            while ($current_line !== $prev_line && $current_line > 1 && $i > $stop_at) {
                if ($tokens[$i]['code'] === T_FUNCTION) {
                    // Found another interface or abstract function.
                    return;
                }
                if ($tokens[$i]['code'] === T_CLOSE_CURLY_BRACKET && $tokens[$tokens[$i]['scope_condition']]['code'] === T_FUNCTION) {
                    // Found a previous function.
                    return;
                }
                $current_line = $tokens[$i]['line'];
                if ($current_line === $prev_line) {
                    break;
                }
                if ($tokens[$i - 1]['line'] < $current_line && $tokens[$i + 1]['line'] > $current_line) {
                    // This token is on a line by itself. If it is whitespace, the line is empty.
                    if ($tokens[$i]['code'] === T_WHITESPACE) {
                        $found_lines++;
                    }
                }
                $i--;
            }
            //end while
        }
        //end if
        $required_spacing = $this->spacing;
        $error_code = 'Before';
        if ($is_first === true) {
            $required_spacing = $this->spacing_before_first;
            $error_code = 'BeforeFirst';
            $phpcs_file->record_metric($stack_ptr, 'Function spacing before first', $found_lines);
        } else {
            $phpcs_file->record_metric($stack_ptr, 'Function spacing before', $found_lines);
        }
        if ($found_lines !== $required_spacing) {
            $error = 'Expected %s blank line';
            if ($required_spacing !== 1) {
                $error .= 's';
            }
            $error .= ' before function; %s found';
            $data = [$required_spacing, $found_lines];
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, $error_code, $data);
            if ($fix === true) {
                $next_space = $phpcs_file->find_next(T_WHITESPACE, $prev_content + 1, $stack_ptr);
                if ($next_space === false) {
                    $next_space = $stack_ptr - 1;
                }
                if ($found_lines < $required_spacing) {
                    $padding = str_repeat($phpcs_file->eol_char, $required_spacing - $found_lines);
                    $phpcs_file->fixer->add_content($prev_line_token, $padding);
                } else {
                    $next_content = $phpcs_file->find_next(T_WHITESPACE, $next_space + 1, null, true);
                    $phpcs_file->fixer->begin_changeset();
                    for ($i = $next_space; $i < $next_content; $i++) {
                        if ($tokens[$i]['line'] === $tokens[$prev_content]['line']) {
                            continue;
                        }
                        if ($tokens[$i]['line'] === $tokens[$next_content]['line']) {
                            $phpcs_file->fixer->add_content_before($i, str_repeat($phpcs_file->eol_char, $required_spacing));
                            break;
                        }
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
    //end process()
}
//end class