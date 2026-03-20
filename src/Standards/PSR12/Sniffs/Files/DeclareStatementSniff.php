<?php

declare (strict_types=1);
/**
 * Checks the format of the declare statements.
 *
 * @author    Sertan Danis <sdanis@squiz.net>
 * @copyright 2006-2019 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\PSR12\Sniffs\Files;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Declare_Statement_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_DECLARE];
    }
    //end register()
    /**
     * Processes this sniff, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token in
     *                                               the stack passed in $tokens.
     *
     * @return void
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        // Allow a byte-order mark.
        $tokens = $phpcs_file->get_tokens();
        // There should be no space between declare keyword and opening parenthesis.
        $parenthesis = $stack_ptr + 1;
        if ($tokens[$stack_ptr + 1]['type'] !== 'T_OPEN_PARENTHESIS') {
            $parenthesis = $phpcs_file->find_next(T_WHITESPACE, $stack_ptr + 1, null, true);
            $error = 'Expected no space between declare keyword and opening parenthesis in a declare statement';
            if ($tokens[$parenthesis]['type'] === 'T_OPEN_PARENTHESIS') {
                $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'SpaceFoundAfterDeclare');
                if ($fix === true) {
                    $phpcs_file->fixer->replace_token($stack_ptr + 1, '');
                }
            } else {
                $phpcs_file->add_error($error, $parenthesis, 'SpaceFoundAfterDeclare');
                $parenthesis = $phpcs_file->find_next(T_OPEN_PARENTHESIS, $parenthesis + 1);
            }
        }
        // There should be no space between open parenthesis and the directive.
        $string = $phpcs_file->find_next(T_WHITESPACE, $parenthesis + 1, null, true);
        if ($parenthesis !== false) {
            if ($tokens[$parenthesis + 1]['type'] !== 'T_STRING') {
                $error = 'Expected no space between opening parenthesis and directive in a declare statement';
                if ($tokens[$string]['type'] === 'T_STRING') {
                    $fix = $phpcs_file->add_fixable_error($error, $parenthesis, 'SpaceFoundBeforeDirective');
                    if ($fix === true) {
                        $phpcs_file->fixer->replace_token($parenthesis + 1, '');
                    }
                } else {
                    $phpcs_file->add_error($error, $string, 'SpaceFoundBeforeDirective');
                    $string = $phpcs_file->find_next(T_STRING, $string + 1);
                }
            }
        }
        // There should be no space between directive and the equal sign.
        $equals = $phpcs_file->find_next(T_WHITESPACE, $string + 1, null, true);
        if ($string !== false) {
            // The directive must be in lowercase.
            if ($tokens[$string]['content'] !== strtolower($tokens[$string]['content'])) {
                $error = 'The directive of a declare statement must be in lowercase';
                $fix = $phpcs_file->add_fixable_error($error, $string, 'DirectiveNotLowercase');
                if ($fix === true) {
                    $phpcs_file->fixer->replace_token($string, strtolower($tokens[$string]['content']));
                }
            }
            if ($tokens[$string + 1]['type'] !== 'T_EQUAL') {
                $error = 'Expected no space between directive and the equals sign in a declare statement';
                if ($tokens[$equals]['type'] === 'T_EQUAL') {
                    $fix = $phpcs_file->add_fixable_error($error, $equals, 'SpaceFoundAfterDirective');
                    if ($fix === true) {
                        $phpcs_file->fixer->replace_token($string + 1, '');
                    }
                } else {
                    $phpcs_file->add_error($error, $equals, 'SpaceFoundAfterDirective');
                    $equals = $phpcs_file->find_next(T_EQUAL, $equals + 1);
                }
            }
        }
        //end if
        // There should be no space between equal sign and directive value.
        $value = $phpcs_file->find_next(T_WHITESPACE, $equals + 1, null, true);
        if ($equals !== false) {
            if ($tokens[$equals + 1]['type'] !== 'T_LNUMBER') {
                $error = 'Expected no space between equal sign and the directive value in a declare statement';
                if ($tokens[$value]['type'] === 'T_LNUMBER') {
                    $fix = $phpcs_file->add_fixable_error($error, $value, 'SpaceFoundBeforeDirectiveValue');
                    if ($fix === true) {
                        $phpcs_file->fixer->replace_token($equals + 1, '');
                    }
                } else {
                    $phpcs_file->add_error($error, $value, 'SpaceFoundBeforeDirectiveValue');
                    $value = $phpcs_file->find_next(T_LNUMBER, $value + 1);
                }
            }
        }
        $parenthesis = $phpcs_file->find_next(T_WHITESPACE, $value + 1, null, true);
        if ($value !== false) {
            if ($tokens[$value + 1]['type'] !== 'T_CLOSE_PARENTHESIS') {
                $error = 'Expected no space between the directive value and closing parenthesis in a declare statement';
                if ($tokens[$parenthesis]['type'] === 'T_CLOSE_PARENTHESIS') {
                    $fix = $phpcs_file->add_fixable_error($error, $parenthesis, 'SpaceFoundAfterDirectiveValue');
                    if ($fix === true) {
                        $phpcs_file->fixer->replace_token($value + 1, '');
                    }
                } else {
                    $phpcs_file->add_error($error, $parenthesis, 'SpaceFoundAfterDirectiveValue');
                    $parenthesis = $phpcs_file->find_next(T_CLOSE_PARENTHESIS, $parenthesis + 1);
                }
            }
        }
        // Check for semicolon.
        $curly_bracket = false;
        if ($tokens[$parenthesis + 1]['type'] !== 'T_SEMICOLON') {
            $token = $phpcs_file->find_next(T_WHITESPACE, $parenthesis + 1, null, true);
            if ($tokens[$token]['type'] === 'T_OPEN_CURLY_BRACKET') {
                // Block declaration.
                $curly_bracket = $token;
            } elseif ($tokens[$token]['type'] === 'T_SEMICOLON') {
                $error = 'Expected no space between the closing parenthesis and the semicolon in a declare statement';
                $fix = $phpcs_file->add_fixable_error($error, $parenthesis, 'SpaceFoundBeforeSemicolon');
                if ($fix === true) {
                    $phpcs_file->fixer->replace_token($parenthesis + 1, '');
                }
            } elseif ($tokens[$token]['type'] === 'T_CLOSE_TAG') {
                if ($tokens[$parenthesis]['line'] !== $tokens[$token]['line']) {
                    // Close tag must be on the same line..
                    $error = 'The close tag must be on the same line as the declare statement';
                    $fix = $phpcs_file->add_fixable_error($error, $parenthesis, 'CloseTagOnNewLine');
                    if ($fix === true) {
                        $phpcs_file->fixer->replace_token($parenthesis + 1, ' ');
                    }
                }
            } else {
                $error = 'Expected no space between the closing parenthesis and the semicolon in a declare statement';
                $phpcs_file->add_error($error, $parenthesis, 'SpaceFoundBeforeSemicolon');
                // See if there is a semicolon or curly bracket after this token.
                $token = $phpcs_file->find_next([T_WHITESPACE, T_COMMENT], $token + 1, null, true);
                if ($tokens[$token]['type'] === 'T_OPEN_CURLY_BRACKET') {
                    $curly_bracket = $token;
                }
            }
            //end if
        }
        //end if
        if ($curly_bracket !== false) {
            $prev_token = $phpcs_file->find_previous(T_WHITESPACE, $curly_bracket - 1, null, true);
            $error = 'Expected one space between closing parenthesis and opening curly bracket in a declare statement';
            // The opening curly bracket must on the same line with a single space between closing bracket.
            if ($tokens[$prev_token]['type'] !== 'T_CLOSE_PARENTHESIS') {
                $phpcs_file->add_error($error, $curly_bracket, 'ExtraSpaceFoundAfterBracket');
            } elseif ($phpcs_file->get_tokens_as_string($prev_token + 1, $curly_bracket - $prev_token - 1) !== ' ') {
                $fix = $phpcs_file->add_fixable_error($error, $curly_bracket, 'ExtraSpaceFoundAfterBracket');
                if ($fix === true) {
                    $phpcs_file->fixer->begin_changeset();
                    $phpcs_file->fixer->replace_token($prev_token + 1, ' ');
                    $next_token = $prev_token + 2;
                    while ($next_token !== $curly_bracket) {
                        $phpcs_file->fixer->replace_token($next_token, '');
                        $next_token++;
                    }
                    $phpcs_file->fixer->end_changeset();
                }
            }
            //end if
            $close_curly_bracket = $tokens[$curly_bracket]['bracket_closer'];
            $prev_token = $phpcs_file->find_previous(T_WHITESPACE, $close_curly_bracket - 1, null, true);
            $next_token = $phpcs_file->find_next([T_WHITESPACE, T_COMMENT], $close_curly_bracket + 1, null, true);
            $line = $tokens[$close_curly_bracket]['line'];
            // The closing curly bracket must be on a new line.
            if ($tokens[$prev_token]['line'] === $line || $tokens[$next_token]['line'] === $line) {
                if ($tokens[$prev_token]['line'] === $line) {
                    $error = 'The closing curly bracket of a declare statement must be on a new line';
                    $fix = $phpcs_file->add_fixable_error($error, $prev_token, 'CurlyBracketNotOnNewLine');
                    if ($fix === true) {
                        $phpcs_file->fixer->add_newline($prev_token);
                    }
                }
            }
            //end if
            // Closing curly bracket must align with the declare keyword.
            if ($tokens[$stack_ptr]['column'] !== $tokens[$close_curly_bracket]['column']) {
                $error = 'The closing curly bracket of a declare statements must be aligned with the declare keyword';
                $fix = $phpcs_file->add_fixable_error($error, $close_curly_bracket, 'CloseBracketNotAligned');
                if ($fix === true) {
                    $phpcs_file->fixer->replace_token($close_curly_bracket - 1, str_repeat(' ', $tokens[$stack_ptr]['column'] - 1));
                }
            }
            // The open curly bracket must be the last code on the line.
            $token = $phpcs_file->find_next(Tokens::$empty_tokens, $curly_bracket + 1, null, true);
            if ($tokens[$curly_bracket]['line'] === $tokens[$token]['line']) {
                $error = 'The open curly bracket of a declare statement must be the last code on the line';
                $fix = $phpcs_file->add_fixable_error($error, $token, 'CodeFoundAfterCurlyBracket');
                if ($fix === true) {
                    $phpcs_file->fixer->begin_changeset();
                    $prev_token = $phpcs_file->find_previous(T_WHITESPACE, $token - 1, null, true);
                    for ($i = $prev_token + 1; $i < $token; $i++) {
                        $phpcs_file->fixer->replace_token($i, '');
                    }
                    $phpcs_file->fixer->add_new_line_before($token);
                    $phpcs_file->fixer->end_changeset();
                }
            }
        }
        //end if
    }
    //end process()
}
//end class