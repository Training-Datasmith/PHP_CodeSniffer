<?php

declare (strict_types=1);
/**
 * Checks that control structures have the correct spacing.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2019 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\PSR12\Sniffs\Control_Structures;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Standards\PSR2\Sniffs\Control_Structures\Control_Structure_Spacing_Sniff as PSR2Spacing;
use Php_code_Sniffer\Util\Tokens;
class Control_Structure_Spacing_Sniff implements Sniff
{
    /**
     * The number of spaces code should be indented.
     *
     * @var integer
     */
    public $indent = 4;
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_IF, T_WHILE, T_FOREACH, T_FOR, T_SWITCH, T_ELSE, T_ELSEIF, T_CATCH, T_MATCH];
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
        if (isset($tokens[$stack_ptr]['parenthesis_opener']) === false || isset($tokens[$stack_ptr]['parenthesis_closer']) === false) {
            return;
        }
        $paren_opener = $tokens[$stack_ptr]['parenthesis_opener'];
        $paren_closer = $tokens[$stack_ptr]['parenthesis_closer'];
        if ($tokens[$paren_opener]['line'] === $tokens[$paren_closer]['line']) {
            // Conditions are all on the same line, so follow PSR2.
            $sniff = new PSR2Spacing();
            return $sniff->process($phpcs_file, $stack_ptr);
        }
        $next = $phpcs_file->find_next(T_WHITESPACE, $paren_opener + 1, $paren_closer, true);
        if ($next === false) {
            // No conditions; parse error.
            return;
        }
        // Check the first expression.
        if ($tokens[$next]['line'] !== $tokens[$paren_opener]['line'] + 1) {
            $error = 'The first expression of a multi-line control structure must be on the line after the opening parenthesis';
            $fix = $phpcs_file->add_fixable_error($error, $next, 'FirstExpressionLine');
            if ($fix === true) {
                $phpcs_file->fixer->add_newline($paren_opener);
            }
        }
        // Check the indent of each line.
        $first = $phpcs_file->find_first_on_line(T_WHITESPACE, $stack_ptr, true);
        $required_indent = $tokens[$first]['column'] + $this->indent - 1;
        for ($i = $paren_opener; $i < $paren_closer; $i++) {
            if ($tokens[$i]['column'] !== 1) {
                continue;
            }
            if ($tokens[$i + 1]['line'] > $tokens[$i]['line']) {
                continue;
            }
            if (isset(Tokens::$comment_tokens[$tokens[$i]['code']]) === true) {
                continue;
            }
            if ($i + 1 === $paren_closer) {
                break;
            }
            // Leave indentation inside multi-line strings.
            if (isset(Tokens::$text_string_tokens[$tokens[$i]['code']]) === true) {
                continue;
            }
            if (isset(Tokens::$heredoc_tokens[$tokens[$i]['code']]) === true) {
                continue;
            }
            if ($tokens[$i]['code'] !== T_WHITESPACE) {
                $found_indent = 0;
            } else {
                $found_indent = $tokens[$i]['length'];
            }
            if ($found_indent < $required_indent) {
                $error = 'Each line in a multi-line control structure must be indented at least once; expected at least %s spaces, but found %s';
                $data = [$required_indent, $found_indent];
                $fix = $phpcs_file->add_fixable_error($error, $i, 'LineIndent', $data);
                if ($fix === true) {
                    $padding = str_repeat(' ', $required_indent);
                    if ($found_indent === 0) {
                        $phpcs_file->fixer->add_content_before($i, $padding);
                    } else {
                        $phpcs_file->fixer->replace_token($i, $padding);
                    }
                }
            }
        }
        //end for
        // Check the closing parenthesis.
        $prev = $phpcs_file->find_previous(T_WHITESPACE, $paren_closer - 1, $paren_opener, true);
        if ($tokens[$paren_closer]['line'] !== $tokens[$prev]['line'] + 1) {
            $error = 'The closing parenthesis of a multi-line control structure must be on the line after the last expression';
            $fix = $phpcs_file->add_fixable_error($error, $paren_closer, 'CloseParenthesisLine');
            if ($fix === true) {
                if ($tokens[$paren_closer]['line'] === $tokens[$prev]['line']) {
                    $phpcs_file->fixer->add_newline_before($paren_closer);
                } else {
                    $phpcs_file->fixer->begin_changeset();
                    for ($i = $prev + 1; $i < $paren_closer; $i++) {
                        // Maintain existing newline.
                        if ($tokens[$i]['line'] === $tokens[$prev]['line']) {
                            continue;
                        }
                        // Maintain existing indent.
                        if ($tokens[$i]['line'] === $tokens[$paren_closer]['line']) {
                            break;
                        }
                        $phpcs_file->fixer->replace_token($i, '');
                    }
                    $phpcs_file->fixer->end_changeset();
                }
            }
            //end if
        }
        //end if
        if ($tokens[$paren_closer]['line'] !== $tokens[$prev]['line']) {
            $required_indent = $tokens[$first]['column'] - 1;
            $found_indent = $tokens[$paren_closer]['column'] - 1;
            if ($found_indent !== $required_indent) {
                $error = 'The closing parenthesis of a multi-line control structure must be indented to the same level as start of the control structure; expected %s spaces but found %s';
                $data = [$required_indent, $found_indent];
                $fix = $phpcs_file->add_fixable_error($error, $paren_closer, 'CloseParenthesisIndent', $data);
                if ($fix === true) {
                    $padding = str_repeat(' ', $required_indent);
                    if ($found_indent === 0) {
                        $phpcs_file->fixer->add_content_before($paren_closer, $padding);
                    } else {
                        $phpcs_file->fixer->replace_token($paren_closer - 1, $padding);
                    }
                }
            }
        }
    }
    //end process()
}
//end class