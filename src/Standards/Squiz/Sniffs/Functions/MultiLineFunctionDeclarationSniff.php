<?php

declare (strict_types=1);
/**
 * Ensure single and multi-line function declarations are defined correctly.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\Functions;

use Php_code_Sniffer\Standards\PEAR\Sniffs\Functions\Function_Declaration_Sniff as PEARFunctionDeclarationSniff;
use Php_code_Sniffer\Util\Tokens;
class Multi_Line_Function_Declaration_Sniff extends Pear_Function_Declaration_Sniff
{
    /**
     * A list of tokenizers this sniff supports.
     *
     * @var array
     */
    public $supported_tokenizers = ['PHP', 'JS'];
    /**
     * Determine if this is a multi-line function declaration.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile   The file being scanned.
     * @param int                         $stackPtr    The position of the current token
     *                                                 in the stack passed in $tokens.
     * @param int                         $openBracket The position of the opening bracket
     *                                                 in the stack passed in $tokens.
     * @param array                       $tokens      The stack of tokens that make up
     *                                                 the file.
     *
     * @return void
     */
    public function is_multi_line_declaration($phpcs_file, $stack_ptr, $open_bracket, $tokens)
    {
        $brackets_to_check = [$stack_ptr => $open_bracket];
        // Closures may use the USE keyword and so be multi-line in this way.
        if ($tokens[$stack_ptr]['code'] === T_CLOSURE) {
            $use = $phpcs_file->find_next(T_USE, $tokens[$open_bracket]['parenthesis_closer'] + 1, $tokens[$stack_ptr]['scope_opener']);
            if ($use !== false) {
                $open = $phpcs_file->find_next(T_OPEN_PARENTHESIS, $use + 1);
                if ($open !== false) {
                    $brackets_to_check[$use] = $open;
                }
            }
        }
        foreach ($brackets_to_check as $stack_ptr => $open_bracket) {
            // If the first argument is on a new line, this is a multi-line
            // function declaration, even if there is only one argument.
            $next = $phpcs_file->find_next(Tokens::$empty_tokens, $open_bracket + 1, null, true);
            if ($tokens[$next]['line'] !== $tokens[$stack_ptr]['line']) {
                return true;
            }
            $close_bracket = $tokens[$open_bracket]['parenthesis_closer'];
            $end = $phpcs_file->find_end_of_statement($open_bracket + 1);
            while ($tokens[$end]['code'] === T_COMMA) {
                // If the next bit of code is not on the same line, this is a
                // multi-line function declaration.
                $next = $phpcs_file->find_next(Tokens::$empty_tokens, $end + 1, $close_bracket, true);
                if ($next === false) {
                    continue 2;
                }
                if ($tokens[$next]['line'] !== $tokens[$end]['line']) {
                    return true;
                }
                $end = $phpcs_file->find_end_of_statement($next);
            }
            // We've reached the last argument, so see if the next content
            // (should be the close bracket) is also on the same line.
            $next = $phpcs_file->find_next(Tokens::$empty_tokens, $end + 1, $close_bracket, true);
            if ($next !== false && $tokens[$next]['line'] !== $tokens[$end]['line']) {
                return true;
            }
        }
        //end foreach
        return false;
    }
    //end isMultiLineDeclaration()
    /**
     * Processes single-line declarations.
     *
     * Just uses the Generic BSD-Allman brace sniff.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token
     *                                               in the stack passed in $tokens.
     * @param array                       $tokens    The stack of tokens that make up
     *                                               the file.
     *
     * @return void
     */
    public function process_single_line_declaration($phpcs_file, $stack_ptr, array $tokens)
    {
        // We do everything the parent sniff does, and a bit more because we
        // define multi-line declarations a bit differently.
        parent::process_single_line_declaration($phpcs_file, $stack_ptr, $tokens);
        $opening_bracket = $tokens[$stack_ptr]['parenthesis_opener'];
        $closing_bracket = $tokens[$stack_ptr]['parenthesis_closer'];
        $prev_non_white_space = $phpcs_file->find_previous(T_WHITESPACE, $closing_bracket - 1, $opening_bracket, true);
        if ($tokens[$prev_non_white_space]['line'] !== $tokens[$closing_bracket]['line']) {
            $error = 'There must not be a newline before the closing parenthesis of a single-line function declaration';
            if (isset(Tokens::$empty_tokens[$tokens[$prev_non_white_space]['code']]) === true) {
                $phpcs_file->add_error($error, $closing_bracket, 'CloseBracketNewLine');
            } else {
                $fix = $phpcs_file->add_fixable_error($error, $closing_bracket, 'CloseBracketNewLine');
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
        }
        //end if
    }
    //end processSingleLineDeclaration()
    /**
     * Processes multi-line declarations.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token
     *                                               in the stack passed in $tokens.
     * @param array                       $tokens    The stack of tokens that make up
     *                                               the file.
     *
     * @return void
     */
    public function process_multi_line_declaration($phpcs_file, $stack_ptr, array $tokens)
    {
        // We do everything the parent sniff does, and a bit more.
        parent::process_multi_line_declaration($phpcs_file, $stack_ptr, $tokens);
        $open_bracket = $tokens[$stack_ptr]['parenthesis_opener'];
        $this->process_bracket($phpcs_file, $open_bracket, $tokens, 'function');
        if ($tokens[$stack_ptr]['code'] !== T_CLOSURE) {
            return;
        }
        $use = $phpcs_file->find_next(T_USE, $tokens[$stack_ptr]['parenthesis_closer'] + 1, $tokens[$stack_ptr]['scope_opener']);
        if ($use === false) {
            return;
        }
        $open_bracket = $phpcs_file->find_next(T_OPEN_PARENTHESIS, $use + 1);
        $this->process_bracket($phpcs_file, $open_bracket, $tokens, 'use');
    }
    //end processMultiLineDeclaration()
    /**
     * Processes the contents of a single set of brackets.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile   The file being scanned.
     * @param int                         $openBracket The position of the open bracket
     *                                                 in the stack passed in $tokens.
     * @param array                       $tokens      The stack of tokens that make up
     *                                                 the file.
     * @param string                      $type        The type of the token the brackets
     *                                                 belong to (function or use).
     *
     * @return void
     */
    public function process_bracket($phpcs_file, $open_bracket, array $tokens, $type = 'function')
    {
        $error_prefix = '';
        if ($type === 'use') {
            $error_prefix = 'Use';
        }
        $close_bracket = $tokens[$open_bracket]['parenthesis_closer'];
        // The open bracket should be the last thing on the line.
        if ($tokens[$open_bracket]['line'] !== $tokens[$close_bracket]['line']) {
            $next = $phpcs_file->find_next(Tokens::$empty_tokens, $open_bracket + 1, null, true);
            if ($tokens[$next]['line'] === $tokens[$open_bracket]['line']) {
                $error = 'The first parameter of a multi-line ' . $type . ' declaration must be on the line after the opening bracket';
                $fix = $phpcs_file->add_fixable_error($error, $next, $error_prefix . 'FirstParamSpacing');
                if ($fix === true) {
                    if ($tokens[$next]['line'] === $tokens[$open_bracket]['line']) {
                        $phpcs_file->fixer->add_newline($open_bracket);
                    } else {
                        $phpcs_file->fixer->begin_changeset();
                        for ($x = $open_bracket; $x < $next; $x++) {
                            if ($tokens[$x]['line'] === $tokens[$open_bracket]['line']) {
                                continue;
                            }
                            if ($tokens[$x]['line'] === $tokens[$next]['line']) {
                                break;
                            }
                        }
                        $phpcs_file->fixer->end_changeset();
                    }
                }
            }
            //end if
        }
        for ($i = $open_bracket + 1; $i < $close_bracket; $i++) {
            // Skip brackets, like arrays, as they can contain commas.
            if (isset($tokens[$i]['bracket_opener']) === true) {
                $i = $tokens[$i]['bracket_closer'];
                continue;
            }
            if (isset($tokens[$i]['parenthesis_opener']) === true) {
                $i = $tokens[$i]['parenthesis_closer'];
                continue;
            }
            if ($tokens[$i]['code'] !== T_COMMA) {
                continue;
            }
            $next = $phpcs_file->find_next(Tokens::$empty_tokens, $i + 1, null, true);
            if ($tokens[$next]['line'] === $tokens[$i]['line']) {
                $error = 'Multi-line ' . $type . ' declarations must define one parameter per line';
                $fix = $phpcs_file->add_fixable_error($error, $next, $error_prefix . 'OneParamPerLine');
                if ($fix === true) {
                    $phpcs_file->fixer->add_newline($i);
                }
            }
        }
        //end for
    }
    //end processBracket()
}
//end class