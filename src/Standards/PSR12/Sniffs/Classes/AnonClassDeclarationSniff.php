<?php

declare (strict_types=1);
/**
 * Checks that the declaration of an anon class is correct.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2019 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\PSR12\Sniffs\Classes;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Standards\Generic\Sniffs\Functions\Function_Call_Argument_Spacing_Sniff;
use Php_code_Sniffer\Standards\PSR2\Sniffs\Classes\Class_Declaration_Sniff;
use Php_code_Sniffer\Standards\Squiz\Sniffs\Functions\Multi_Line_Function_Declaration_Sniff;
use Php_code_Sniffer\Util\Tokens;
class Anon_Class_Declaration_Sniff extends Class_Declaration_Sniff
{
    /**
     * The PSR2 MultiLineFunctionDeclarations sniff.
     *
     * @var \PHP_CodeSniffer\Standards\Squiz\Sniffs\Functions\MultiLineFunctionDeclarationSniff
     */
    private $multi_line_sniff;
    /**
     * The Generic FunctionCallArgumentSpacing sniff.
     *
     * @var \PHP_CodeSniffer\Standards\Generic\Sniffs\Functions\FunctionCallArgumentSpacingSniff
     */
    private $function_call_sniff;
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_ANON_CLASS];
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
        if (isset($tokens[$stack_ptr]['scope_opener']) === false) {
            return;
        }
        $this->multi_line_sniff = new Multi_Line_Function_Declaration_Sniff();
        $this->function_call_sniff = new Function_Call_Argument_Spacing_Sniff();
        $this->process_open($phpcs_file, $stack_ptr);
        $this->process_close($phpcs_file, $stack_ptr);
        if (isset($tokens[$stack_ptr]['parenthesis_opener']) === true) {
            $open_bracket = $tokens[$stack_ptr]['parenthesis_opener'];
            if ($this->multi_line_sniff->is_multi_line_declaration($phpcs_file, $stack_ptr, $open_bracket, $tokens) === true) {
                $this->process_multi_line_argument_list($phpcs_file, $stack_ptr);
            } else {
                $this->process_single_line_argument_list($phpcs_file, $stack_ptr);
            }
            $this->function_call_sniff->check_spacing($phpcs_file, $stack_ptr, $open_bracket);
        }
        $opener = $tokens[$stack_ptr]['scope_opener'];
        if ($tokens[$opener]['line'] === $tokens[$stack_ptr]['line']) {
            return;
        }
        $prev = $phpcs_file->find_previous(T_WHITESPACE, $opener - 1, $stack_ptr, true);
        $implements = $phpcs_file->find_previous(T_IMPLEMENTS, $opener - 1, $stack_ptr);
        if ($implements !== false && $tokens[$opener]['line'] !== $tokens[$implements]['line'] && $tokens[$opener]['line'] === $tokens[$prev]['line']) {
            // Opening brace must be on a new line as implements list wraps.
            $error = 'Opening brace must be on the line after the last implemented interface';
            $fix = $phpcs_file->add_fixable_error($error, $opener, 'OpenBraceSameLine');
            if ($fix === true) {
                $first = $phpcs_file->find_first_on_line(T_WHITESPACE, $stack_ptr, true);
                $indent = str_repeat(' ', $tokens[$first]['column'] - 1);
                $phpcs_file->fixer->begin_changeset();
                $phpcs_file->fixer->replace_token($prev + 1, '');
                $phpcs_file->fixer->add_newline($prev);
                $phpcs_file->fixer->add_content_before($opener, $indent);
                $phpcs_file->fixer->end_changeset();
            }
        }
        if ($tokens[$opener]['line'] > $tokens[$prev]['line'] + 1) {
            // Opening brace is on a new line, so there must be no blank line before it.
            $error = 'Opening brace must not be preceded by a blank line';
            $fix = $phpcs_file->add_fixable_error($error, $opener, 'OpenBraceLine');
            if ($fix === true) {
                $phpcs_file->fixer->begin_changeset();
                for ($x = $prev + 1; $x < $opener; $x++) {
                    if ($tokens[$x]['line'] === $tokens[$prev]['line']) {
                        // Maintain existing newline.
                        continue;
                    }
                    if ($tokens[$x]['line'] === $tokens[$opener]['line']) {
                        // Maintain existing indent.
                        break;
                    }
                    $phpcs_file->fixer->replace_token($x, '');
                }
                $phpcs_file->fixer->end_changeset();
            }
        }
        //end if
    }
    //end process()
    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token
     *                                               in the stack passed in $tokens.
     *
     * @return void
     */
    public function process_single_line_argument_list(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        $open_bracket = $tokens[$stack_ptr]['parenthesis_opener'];
        $close_bracket = $tokens[$open_bracket]['parenthesis_closer'];
        if ($open_bracket === $close_bracket - 1) {
            return;
        }
        if ($tokens[$open_bracket + 1]['code'] === T_WHITESPACE) {
            $error = 'Space after opening parenthesis of single-line argument list prohibited';
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'SpaceAfterOpenBracket');
            if ($fix === true) {
                $phpcs_file->fixer->replace_token($open_bracket + 1, '');
            }
        }
        $space_before_close = 0;
        $prev = $phpcs_file->find_previous(T_WHITESPACE, $close_bracket - 1, $open_bracket, true);
        if ($tokens[$prev]['code'] === T_END_HEREDOC || $tokens[$prev]['code'] === T_END_NOWDOC) {
            // Need a newline after these tokens, so ignore this rule.
            return;
        }
        if ($tokens[$prev]['line'] !== $tokens[$close_bracket]['line']) {
            $space_before_close = 'newline';
        } elseif ($tokens[$close_bracket - 1]['code'] === T_WHITESPACE) {
            $space_before_close = $tokens[$close_bracket - 1]['length'];
        }
        if ($space_before_close !== 0) {
            $error = 'Space before closing parenthesis of single-line argument list prohibited';
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'SpaceBeforeCloseBracket');
            if ($fix === true) {
                if ($space_before_close === 'newline') {
                    $phpcs_file->fixer->begin_changeset();
                    $closing_content = ')';
                    $next = $phpcs_file->find_next(T_WHITESPACE, $close_bracket + 1, null, true);
                    if ($tokens[$next]['code'] === T_SEMICOLON) {
                        $closing_content .= ';';
                        for ($i = $close_bracket + 1; $i <= $next; $i++) {
                            $phpcs_file->fixer->replace_token($i, '');
                        }
                    }
                    // We want to jump over any whitespace or inline comment and
                    // move the closing parenthesis after any other token.
                    $prev = $close_bracket - 1;
                    while (isset(Tokens::$empty_tokens[$tokens[$prev]['code']]) === true) {
                        if ($tokens[$prev]['code'] === T_COMMENT && strpos($tokens[$prev]['content'], '*/') !== false) {
                            break;
                        }
                        $prev--;
                    }
                    $phpcs_file->fixer->add_content($prev, $closing_content);
                    $prev_non_whitespace = $phpcs_file->find_previous(T_WHITESPACE, $close_bracket - 1, null, true);
                    for ($i = $prev_non_whitespace + 1; $i <= $close_bracket; $i++) {
                        $phpcs_file->fixer->replace_token($i, '');
                    }
                    $phpcs_file->fixer->end_changeset();
                } else {
                    $phpcs_file->fixer->replace_token($close_bracket - 1, '');
                }
                //end if
            }
            //end if
        }
        //end if
    }
    //end processSingleLineArgumentList()
    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token
     *                                               in the stack passed in $tokens.
     *
     * @return void
     */
    public function process_multi_line_argument_list(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        $open_bracket = $tokens[$stack_ptr]['parenthesis_opener'];
        $this->multi_line_sniff->process_bracket($phpcs_file, $open_bracket, $tokens, 'argument');
        $this->multi_line_sniff->process_argument_list($phpcs_file, $stack_ptr, $this->indent, 'argument');
    }
    //end processMultiLineArgumentList()
}
//end class