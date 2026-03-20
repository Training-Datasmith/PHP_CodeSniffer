<?php

declare (strict_types=1);
/**
 * Ensure single and multi-line function declarations are defined correctly.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\PEAR\Sniffs\Functions;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Standards\Generic\Sniffs\Functions\Opening_Function_Brace_Bsd_Allman_Sniff;
use Php_code_Sniffer\Standards\Generic\Sniffs\Functions\Opening_Function_Brace_Kernighan_Ritchie_Sniff;
use Php_code_Sniffer\Util\Tokens;
class Function_Declaration_Sniff implements Sniff
{
    /**
     * A list of tokenizers this sniff supports.
     *
     * @var array
     */
    public $supported_tokenizers = ['PHP', 'JS'];
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
        return [T_FUNCTION, T_CLOSURE];
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
        if (isset($tokens[$stack_ptr]['parenthesis_opener']) === false || isset($tokens[$stack_ptr]['parenthesis_closer']) === false || $tokens[$stack_ptr]['parenthesis_opener'] === null || $tokens[$stack_ptr]['parenthesis_closer'] === null) {
            return;
        }
        $open_bracket = $tokens[$stack_ptr]['parenthesis_opener'];
        $close_bracket = $tokens[$stack_ptr]['parenthesis_closer'];
        if (strtolower($tokens[$stack_ptr]['content']) === 'function') {
            // Must be one space after the FUNCTION keyword.
            if ($tokens[$stack_ptr + 1]['content'] === $phpcs_file->eol_char) {
                $spaces = 'newline';
            } elseif ($tokens[$stack_ptr + 1]['code'] === T_WHITESPACE) {
                $spaces = $tokens[$stack_ptr + 1]['length'];
            } else {
                $spaces = 0;
            }
            if ($spaces !== 1) {
                $error = 'Expected 1 space after FUNCTION keyword; %s found';
                $data = [$spaces];
                $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'SpaceAfterFunction', $data);
                if ($fix === true) {
                    if ($spaces === 0) {
                        $phpcs_file->fixer->add_content($stack_ptr, ' ');
                    } else {
                        $phpcs_file->fixer->replace_token($stack_ptr + 1, ' ');
                    }
                }
            }
        }
        //end if
        // Must be no space before the opening parenthesis. For closures, this is
        // enforced by the previous check because there is no content between the keywords
        // and the opening parenthesis.
        // Unfinished closures are tokenized as T_FUNCTION however, and can be excluded
        // by checking for the scope_opener.
        if ($tokens[$stack_ptr]['code'] === T_FUNCTION && (isset($tokens[$stack_ptr]['scope_opener']) === true || $phpcs_file->get_method_properties($stack_ptr)['has_body'] === false)) {
            if ($tokens[$open_bracket - 1]['content'] === $phpcs_file->eol_char) {
                $spaces = 'newline';
            } elseif ($tokens[$open_bracket - 1]['code'] === T_WHITESPACE) {
                $spaces = $tokens[$open_bracket - 1]['length'];
            } else {
                $spaces = 0;
            }
            if ($spaces !== 0) {
                $error = 'Expected 0 spaces before opening parenthesis; %s found';
                $data = [$spaces];
                $fix = $phpcs_file->add_fixable_error($error, $open_bracket, 'SpaceBeforeOpenParen', $data);
                if ($fix === true) {
                    $phpcs_file->fixer->replace_token($open_bracket - 1, '');
                }
            }
            // Must be no space before semicolon in abstract/interface methods.
            if ($phpcs_file->get_method_properties($stack_ptr)['has_body'] === false) {
                $end = $phpcs_file->find_next(T_SEMICOLON, $close_bracket);
                if ($tokens[$end - 1]['content'] === $phpcs_file->eol_char) {
                    $spaces = 'newline';
                } elseif ($tokens[$end - 1]['code'] === T_WHITESPACE) {
                    $spaces = $tokens[$end - 1]['length'];
                } else {
                    $spaces = 0;
                }
                if ($spaces !== 0) {
                    $error = 'Expected 0 spaces before semicolon; %s found';
                    $data = [$spaces];
                    $fix = $phpcs_file->add_fixable_error($error, $end, 'SpaceBeforeSemicolon', $data);
                    if ($fix === true) {
                        $phpcs_file->fixer->replace_token($end - 1, '');
                    }
                }
            }
        }
        //end if
        // Must be one space before and after USE keyword for closures.
        if ($tokens[$stack_ptr]['code'] === T_CLOSURE) {
            $use = $phpcs_file->find_next(T_USE, $close_bracket + 1, $tokens[$stack_ptr]['scope_opener']);
            if ($use !== false) {
                if ($tokens[$use + 1]['code'] !== T_WHITESPACE) {
                    $length = 0;
                } elseif ($tokens[$use + 1]['content'] === "\t") {
                    $length = '\t';
                } else {
                    $length = $tokens[$use + 1]['length'];
                }
                if ($length !== 1) {
                    $error = 'Expected 1 space after USE keyword; found %s';
                    $data = [$length];
                    $fix = $phpcs_file->add_fixable_error($error, $use, 'SpaceAfterUse', $data);
                    if ($fix === true) {
                        if ($length === 0) {
                            $phpcs_file->fixer->add_content($use, ' ');
                        } else {
                            $phpcs_file->fixer->replace_token($use + 1, ' ');
                        }
                    }
                }
                if ($tokens[$use - 1]['code'] !== T_WHITESPACE) {
                    $length = 0;
                } elseif ($tokens[$use - 1]['content'] === "\t") {
                    $length = '\t';
                } else {
                    $length = $tokens[$use - 1]['length'];
                }
                if ($length !== 1) {
                    $error = 'Expected 1 space before USE keyword; found %s';
                    $data = [$length];
                    $fix = $phpcs_file->add_fixable_error($error, $use, 'SpaceBeforeUse', $data);
                    if ($fix === true) {
                        if ($length === 0) {
                            $phpcs_file->fixer->add_content_before($use, ' ');
                        } else {
                            $phpcs_file->fixer->replace_token($use - 1, ' ');
                        }
                    }
                }
            }
            //end if
        }
        //end if
        if ($this->is_multi_line_declaration($phpcs_file, $stack_ptr, $open_bracket, $tokens) === true) {
            $this->process_multi_line_declaration($phpcs_file, $stack_ptr, $tokens);
        } else {
            $this->process_single_line_declaration($phpcs_file, $stack_ptr, $tokens);
        }
    }
    //end process()
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
     * @return bool
     */
    public function is_multi_line_declaration($phpcs_file, $stack_ptr, $open_bracket, array $tokens)
    {
        $close_bracket = $tokens[$open_bracket]['parenthesis_closer'];
        if ($tokens[$open_bracket]['line'] !== $tokens[$close_bracket]['line']) {
            return true;
        }
        // Closures may use the USE keyword and so be multi-line in this way.
        if ($tokens[$stack_ptr]['code'] === T_CLOSURE) {
            $use = $phpcs_file->find_next(T_USE, $close_bracket + 1, $tokens[$stack_ptr]['scope_opener']);
            if ($use !== false) {
                // If the opening and closing parenthesis of the use statement
                // are also on the same line, this is a single line declaration.
                $open = $phpcs_file->find_next(T_OPEN_PARENTHESIS, $use + 1);
                $close = $tokens[$open]['parenthesis_closer'];
                if ($tokens[$open]['line'] !== $tokens[$close]['line']) {
                    return true;
                }
            }
        }
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
        if ($tokens[$stack_ptr]['code'] === T_CLOSURE) {
            $sniff = new Opening_Function_Brace_Kernighan_Ritchie_Sniff();
        } else {
            $sniff = new Opening_Function_Brace_Bsd_Allman_Sniff();
        }
        $sniff->check_closures = true;
        $sniff->process($phpcs_file, $stack_ptr);
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
        $this->process_argument_list($phpcs_file, $stack_ptr, $this->indent);
        $close_bracket = $tokens[$stack_ptr]['parenthesis_closer'];
        if ($tokens[$stack_ptr]['code'] === T_CLOSURE) {
            $use = $phpcs_file->find_next(T_USE, $close_bracket + 1, $tokens[$stack_ptr]['scope_opener']);
            if ($use !== false) {
                $open = $phpcs_file->find_next(T_OPEN_PARENTHESIS, $use + 1);
                $close_bracket = $tokens[$open]['parenthesis_closer'];
            }
        }
        if (isset($tokens[$stack_ptr]['scope_opener']) === false) {
            return;
        }
        // The opening brace needs to be one space away from the closing parenthesis.
        $opener = $tokens[$stack_ptr]['scope_opener'];
        if ($tokens[$opener]['line'] !== $tokens[$close_bracket]['line']) {
            $error = 'The closing parenthesis and the opening brace of a multi-line function declaration must be on the same line';
            $fix = $phpcs_file->add_fixable_error($error, $opener, 'NewlineBeforeOpenBrace');
            if ($fix === true) {
                $prev = $phpcs_file->find_previous(Tokens::$empty_tokens, $opener - 1, $close_bracket, true);
                $phpcs_file->fixer->begin_changeset();
                $phpcs_file->fixer->add_content($prev, ' {');
                // If the opener is on a line by itself, removing it will create
                // an empty line, so just remove the entire line instead.
                $prev = $phpcs_file->find_previous(T_WHITESPACE, $opener - 1, $close_bracket, true);
                $next = $phpcs_file->find_next(T_WHITESPACE, $opener + 1, null, true);
                if ($tokens[$prev]['line'] < $tokens[$opener]['line'] && $tokens[$next]['line'] > $tokens[$opener]['line']) {
                    // Clear the whole line.
                    for ($i = $prev + 1; $i < $next; $i++) {
                        if ($tokens[$i]['line'] === $tokens[$opener]['line']) {
                            $phpcs_file->fixer->replace_token($i, '');
                        }
                    }
                } else {
                    // Just remove the opener.
                    $phpcs_file->fixer->replace_token($opener, '');
                    if ($tokens[$next]['line'] === $tokens[$opener]['line']) {
                        $phpcs_file->fixer->replace_token($opener + 1, '');
                    }
                }
                $phpcs_file->fixer->end_changeset();
            }
            //end if
        } else {
            $prev = $tokens[$opener - 1];
            if ($prev['code'] !== T_WHITESPACE) {
                $length = 0;
            } else {
                $length = strlen($prev['content']);
            }
            if ($length !== 1) {
                $error = 'There must be a single space between the closing parenthesis and the opening brace of a multi-line function declaration; found %s spaces';
                $fix = $phpcs_file->add_fixable_error($error, $opener - 1, 'SpaceBeforeOpenBrace', [$length]);
                if ($fix === true) {
                    if ($length === 0) {
                        $phpcs_file->fixer->add_content_before($opener, ' ');
                    } else {
                        $phpcs_file->fixer->replace_token($opener - 1, ' ');
                    }
                }
                return;
            }
            //end if
        }
        //end if
    }
    //end processMultiLineDeclaration()
    /**
     * Processes multi-line argument list declarations.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token
     *                                               in the stack passed in $tokens.
     * @param int                         $indent    The number of spaces code should be indented.
     * @param string                      $type      The type of the token the brackets
     *                                               belong to.
     *
     * @return void
     */
    public function process_argument_list($phpcs_file, $stack_ptr, $indent, $type = 'function')
    {
        $tokens = $phpcs_file->get_tokens();
        // We need to work out how far indented the function
        // declaration itself is, so we can work out how far to
        // indent parameters.
        $function_indent = 0;
        for ($i = $stack_ptr - 1; $i >= 0; $i--) {
            if ($tokens[$i]['line'] !== $tokens[$stack_ptr]['line']) {
                break;
            }
        }
        // Move $i back to the line the function is or to 0.
        $i++;
        if ($tokens[$i]['code'] === T_WHITESPACE) {
            $function_indent = $tokens[$i]['length'];
        }
        // The closing parenthesis must be on a new line, even
        // when checking abstract function definitions.
        $close_bracket = $tokens[$stack_ptr]['parenthesis_closer'];
        $prev = $phpcs_file->find_previous(T_WHITESPACE, $close_bracket - 1, null, true);
        if ($tokens[$close_bracket]['line'] !== $tokens[$tokens[$close_bracket]['parenthesis_opener']]['line'] && $tokens[$prev]['line'] === $tokens[$close_bracket]['line']) {
            $error = 'The closing parenthesis of a multi-line ' . $type . ' declaration must be on a new line';
            $fix = $phpcs_file->add_fixable_error($error, $close_bracket, 'CloseBracketLine');
            if ($fix === true) {
                $phpcs_file->fixer->add_newline_before($close_bracket);
            }
        }
        // If this is a closure and is using a USE statement, the closing
        // parenthesis we need to look at from now on is the closing parenthesis
        // of the USE statement.
        if ($tokens[$stack_ptr]['code'] === T_CLOSURE) {
            $use = $phpcs_file->find_next(T_USE, $close_bracket + 1, $tokens[$stack_ptr]['scope_opener']);
            if ($use !== false) {
                $open = $phpcs_file->find_next(T_OPEN_PARENTHESIS, $use + 1);
                $close_bracket = $tokens[$open]['parenthesis_closer'];
                $prev = $phpcs_file->find_previous(T_WHITESPACE, $close_bracket - 1, null, true);
                if ($tokens[$close_bracket]['line'] !== $tokens[$tokens[$close_bracket]['parenthesis_opener']]['line'] && $tokens[$prev]['line'] === $tokens[$close_bracket]['line']) {
                    $error = 'The closing parenthesis of a multi-line use declaration must be on a new line';
                    $fix = $phpcs_file->add_fixable_error($error, $close_bracket, 'UseCloseBracketLine');
                    if ($fix === true) {
                        $phpcs_file->fixer->add_newline_before($close_bracket);
                    }
                }
            }
            //end if
        }
        //end if
        // Each line between the parenthesis should be indented 4 spaces.
        $open_bracket = $tokens[$stack_ptr]['parenthesis_opener'];
        $last_line = $tokens[$open_bracket]['line'];
        for ($i = $open_bracket + 1; $i < $close_bracket; $i++) {
            if ($tokens[$i]['line'] !== $last_line) {
                if ($i === $tokens[$stack_ptr]['parenthesis_closer'] || $tokens[$i]['code'] === T_WHITESPACE && ($i + 1 === $close_bracket || $i + 1 === $tokens[$stack_ptr]['parenthesis_closer'])) {
                    // Closing braces need to be indented to the same level
                    // as the function.
                    $expected_indent = $function_indent;
                } else {
                    $expected_indent = $function_indent + $indent;
                }
                // We changed lines, so this should be a whitespace indent token.
                $found_indent = 0;
                if ($tokens[$i]['code'] === T_WHITESPACE && $tokens[$i]['line'] !== $tokens[$i + 1]['line']) {
                    $error = 'Blank lines are not allowed in a multi-line ' . $type . ' declaration';
                    $fix = $phpcs_file->add_fixable_error($error, $i, 'EmptyLine');
                    if ($fix === true) {
                        $phpcs_file->fixer->replace_token($i, '');
                    }
                    // This is an empty line, so don't check the indent.
                    continue;
                }
                if ($tokens[$i]['code'] === T_WHITESPACE) {
                    $found_indent = $tokens[$i]['length'];
                } elseif ($tokens[$i]['code'] === T_DOC_COMMENT_WHITESPACE) {
                    $found_indent = $tokens[$i]['length'];
                    ++$expected_indent;
                }
                if ($expected_indent !== $found_indent) {
                    $error = 'Multi-line ' . $type . ' declaration not indented correctly; expected %s spaces but found %s';
                    $data = [$expected_indent, $found_indent];
                    $fix = $phpcs_file->add_fixable_error($error, $i, 'Indent', $data);
                    if ($fix === true) {
                        $spaces = str_repeat(' ', $expected_indent);
                        if ($found_indent === 0) {
                            $phpcs_file->fixer->add_content_before($i, $spaces);
                        } else {
                            $phpcs_file->fixer->replace_token($i, $spaces);
                        }
                    }
                }
                $last_line = $tokens[$i]['line'];
            }
            //end if
            if ($tokens[$i]['code'] === T_OPEN_PARENTHESIS && isset($tokens[$i]['parenthesis_closer']) === true) {
                $prev_non_empty = $phpcs_file->find_previous(Tokens::$empty_tokens, $i - 1, null, true);
                if ($tokens[$prev_non_empty]['code'] !== T_USE) {
                    // Since PHP 8.1, a default value can contain a class instantiation.
                    // Skip over these "function calls" as they have their own indentation rules.
                    $i = $tokens[$i]['parenthesis_closer'];
                    $last_line = $tokens[$i]['line'];
                    continue;
                }
            }
            if ($tokens[$i]['code'] === T_ARRAY || $tokens[$i]['code'] === T_OPEN_SHORT_ARRAY) {
                // Skip arrays as they have their own indentation rules.
                if ($tokens[$i]['code'] === T_OPEN_SHORT_ARRAY) {
                    $i = $tokens[$i]['bracket_closer'];
                } else {
                    $i = $tokens[$i]['parenthesis_closer'];
                }
                $last_line = $tokens[$i]['line'];
                continue;
            }
            if ($tokens[$i]['code'] === T_ATTRIBUTE) {
                // Skip attributes as they have their own indentation rules.
                $i = $tokens[$i]['attribute_closer'];
                $last_line = $tokens[$i]['line'];
                continue;
            }
        }
        //end for
    }
    //end processArgumentList()
}
//end class