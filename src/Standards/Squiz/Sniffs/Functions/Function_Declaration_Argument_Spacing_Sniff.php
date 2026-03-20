<?php

declare (strict_types=1);
/**
 * Checks that arguments in function declarations are spaced correctly.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\Functions;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Function_Declaration_Argument_Spacing_Sniff implements Sniff
{
    /**
     * How many spaces should surround the equals signs.
     *
     * @var integer
     */
    public $equals_spacing = 0;
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
        return [T_FUNCTION, T_CLOSURE, T_FN];
    }
    //end register()
    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token
     *                                               in the stack.
     *
     * @return void
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        if (isset($tokens[$stack_ptr]['parenthesis_opener']) === false || isset($tokens[$stack_ptr]['parenthesis_closer']) === false || $tokens[$stack_ptr]['parenthesis_opener'] === null || $tokens[$stack_ptr]['parenthesis_closer'] === null) {
            return;
        }
        $this->equals_spacing = (int) $this->equals_spacing;
        $this->required_spaces_after_open = (int) $this->required_spaces_after_open;
        $this->required_spaces_before_close = (int) $this->required_spaces_before_close;
        $this->process_bracket($phpcs_file, $tokens[$stack_ptr]['parenthesis_opener']);
        if ($tokens[$stack_ptr]['code'] === T_CLOSURE) {
            $use = $phpcs_file->find_next(T_USE, $tokens[$stack_ptr]['parenthesis_closer'] + 1, $tokens[$stack_ptr]['scope_opener']);
            if ($use !== false) {
                $open_bracket = $phpcs_file->find_next(T_OPEN_PARENTHESIS, $use + 1);
                $this->process_bracket($phpcs_file, $open_bracket);
            }
        }
    }
    //end process()
    /**
     * Processes the contents of a single set of brackets.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile   The file being scanned.
     * @param int                         $openBracket The position of the open bracket
     *                                                 in the stack.
     *
     * @return void
     */
    public function process_bracket($phpcs_file, $open_bracket)
    {
        $tokens = $phpcs_file->get_tokens();
        $close_bracket = $tokens[$open_bracket]['parenthesis_closer'];
        $multi_line = $tokens[$open_bracket]['line'] !== $tokens[$close_bracket]['line'];
        if (isset($tokens[$open_bracket]['parenthesis_owner']) === true) {
            $stack_ptr = $tokens[$open_bracket]['parenthesis_owner'];
        } else {
            $stack_ptr = $phpcs_file->find_previous(T_USE, $open_bracket - 1);
        }
        $params = $phpcs_file->get_method_parameters($stack_ptr);
        if (empty($params) === true) {
            // Check spacing around parenthesis.
            $next = $phpcs_file->find_next(T_WHITESPACE, $open_bracket + 1, $close_bracket, true);
            if ($next === false) {
                if ($close_bracket - $open_bracket !== 1) {
                    if ($tokens[$open_bracket]['line'] !== $tokens[$close_bracket]['line']) {
                        $found = 'newline';
                    } else {
                        $found = $tokens[$open_bracket + 1]['length'];
                    }
                    $error = 'Expected 0 spaces between parenthesis of function declaration; %s found';
                    $data = [$found];
                    $fix = $phpcs_file->add_fixable_error($error, $open_bracket, 'SpacingBetween', $data);
                    if ($fix === true) {
                        $phpcs_file->fixer->replace_token($open_bracket + 1, '');
                    }
                }
                // No params, so we don't check normal spacing rules.
                return;
            }
        }
        //end if
        foreach ($params as $param_number => $param) {
            if ($param['pass_by_reference'] === true) {
                $ref_token = $param['reference_token'];
                $gap = 0;
                if ($tokens[$ref_token + 1]['code'] === T_WHITESPACE) {
                    $gap = $tokens[$ref_token + 1]['length'];
                }
                if ($gap !== 0) {
                    $error = 'Expected 0 spaces after reference operator for argument "%s"; %s found';
                    $data = [$param['name'], $gap];
                    $fix = $phpcs_file->add_fixable_error($error, $ref_token, 'SpacingAfterReference', $data);
                    if ($fix === true) {
                        $phpcs_file->fixer->replace_token($ref_token + 1, '');
                    }
                }
            }
            //end if
            if ($param['variable_length'] === true) {
                $variadic_token = $param['variadic_token'];
                $gap = 0;
                if ($tokens[$variadic_token + 1]['code'] === T_WHITESPACE) {
                    $gap = $tokens[$variadic_token + 1]['length'];
                }
                if ($gap !== 0) {
                    $error = 'Expected 0 spaces after variadic operator for argument "%s"; %s found';
                    $data = [$param['name'], $gap];
                    $fix = $phpcs_file->add_fixable_error($error, $variadic_token, 'SpacingAfterVariadic', $data);
                    if ($fix === true) {
                        $phpcs_file->fixer->replace_token($variadic_token + 1, '');
                    }
                }
            }
            //end if
            if (isset($param['default_equal_token']) === true) {
                $equal_token = $param['default_equal_token'];
                $spaces_before = 0;
                if ($equal_token - $param['token'] > 1) {
                    $spaces_before = $tokens[$param['token'] + 1]['length'];
                }
                if ($spaces_before !== $this->equals_spacing) {
                    $error = 'Incorrect spacing between argument "%s" and equals sign; expected ' . $this->equals_spacing . ' but found %s';
                    $data = [$param['name'], $spaces_before];
                    $fix = $phpcs_file->add_fixable_error($error, $equal_token, 'SpaceBeforeEquals', $data);
                    if ($fix === true) {
                        $padding = str_repeat(' ', $this->equals_spacing);
                        if ($spaces_before === 0) {
                            $phpcs_file->fixer->add_content_before($equal_token, $padding);
                        } else {
                            $phpcs_file->fixer->replace_token($equal_token - 1, $padding);
                        }
                    }
                }
                //end if
                $spaces_after = 0;
                if ($tokens[$equal_token + 1]['code'] === T_WHITESPACE) {
                    $spaces_after = $tokens[$equal_token + 1]['length'];
                }
                if ($spaces_after !== $this->equals_spacing) {
                    $error = 'Incorrect spacing between default value and equals sign for argument "%s"; expected ' . $this->equals_spacing . ' but found %s';
                    $data = [$param['name'], $spaces_after];
                    $fix = $phpcs_file->add_fixable_error($error, $equal_token, 'SpaceAfterEquals', $data);
                    if ($fix === true) {
                        $padding = str_repeat(' ', $this->equals_spacing);
                        if ($spaces_after === 0) {
                            $phpcs_file->fixer->add_content($equal_token, $padding);
                        } else {
                            $phpcs_file->fixer->replace_token($equal_token + 1, $padding);
                        }
                    }
                }
                //end if
            }
            //end if
            if ($param['type_hint_token'] !== false) {
                $type_hint_token = $param['type_hint_end_token'];
                $gap = 0;
                if ($tokens[$type_hint_token + 1]['code'] === T_WHITESPACE) {
                    $gap = $tokens[$type_hint_token + 1]['length'];
                }
                if ($gap !== 1) {
                    $error = 'Expected 1 space between type hint and argument "%s"; %s found';
                    $data = [$param['name'], $gap];
                    $fix = $phpcs_file->add_fixable_error($error, $type_hint_token, 'SpacingAfterHint', $data);
                    if ($fix === true) {
                        if ($gap === 0) {
                            $phpcs_file->fixer->add_content($type_hint_token, ' ');
                        } else {
                            $phpcs_file->fixer->replace_token($type_hint_token + 1, ' ');
                        }
                    }
                }
            }
            //end if
            $comma_token = false;
            if ($param_number > 0 && $params[$param_number - 1]['comma_token'] !== false) {
                $comma_token = $params[$param_number - 1]['comma_token'];
            }
            if ($comma_token !== false) {
                if ($tokens[$comma_token - 1]['code'] === T_WHITESPACE) {
                    $error = 'Expected 0 spaces between argument "%s" and comma; %s found';
                    $data = [$params[$param_number - 1]['name'], $tokens[$comma_token - 1]['length']];
                    $fix = $phpcs_file->add_fixable_error($error, $comma_token, 'SpaceBeforeComma', $data);
                    if ($fix === true) {
                        $phpcs_file->fixer->replace_token($comma_token - 1, '');
                    }
                }
                // Don't check spacing after the comma if it is the last content on the line.
                $check_comma = true;
                if ($multi_line === true) {
                    $next = $phpcs_file->find_next(Tokens::$empty_tokens, $comma_token + 1, $close_bracket, true);
                    if ($tokens[$next]['line'] !== $tokens[$comma_token]['line']) {
                        $check_comma = false;
                    }
                }
                if ($check_comma === true) {
                    if ($param['type_hint_token'] === false) {
                        $spaces_after = 0;
                        if ($tokens[$comma_token + 1]['code'] === T_WHITESPACE) {
                            $spaces_after = $tokens[$comma_token + 1]['length'];
                        }
                        if ($spaces_after === 0) {
                            $error = 'Expected 1 space between comma and argument "%s"; 0 found';
                            $data = [$param['name']];
                            $fix = $phpcs_file->add_fixable_error($error, $comma_token, 'NoSpaceBeforeArg', $data);
                            if ($fix === true) {
                                $phpcs_file->fixer->add_content($comma_token, ' ');
                            }
                        } elseif ($spaces_after !== 1) {
                            $error = 'Expected 1 space between comma and argument "%s"; %s found';
                            $data = [$param['name'], $spaces_after];
                            $fix = $phpcs_file->add_fixable_error($error, $comma_token, 'SpacingBeforeArg', $data);
                            if ($fix === true) {
                                $phpcs_file->fixer->replace_token($comma_token + 1, ' ');
                            }
                        }
                        //end if
                    } else {
                        $hint = $phpcs_file->get_tokens_as_string($param['type_hint_token'], $param['type_hint_end_token'] - $param['type_hint_token'] + 1);
                        if ($param['nullable_type'] === true) {
                            $hint = '?' . $hint;
                        }
                        if ($tokens[$comma_token + 1]['code'] !== T_WHITESPACE) {
                            $error = 'Expected 1 space between comma and type hint "%s"; 0 found';
                            $data = [$hint];
                            $fix = $phpcs_file->add_fixable_error($error, $comma_token, 'NoSpaceBeforeHint', $data);
                            if ($fix === true) {
                                $phpcs_file->fixer->add_content($comma_token, ' ');
                            }
                        } else {
                            $gap = $tokens[$comma_token + 1]['length'];
                            if ($gap !== 1) {
                                $error = 'Expected 1 space between comma and type hint "%s"; %s found';
                                $data = [$hint, $gap];
                                $fix = $phpcs_file->add_fixable_error($error, $comma_token, 'SpacingBeforeHint', $data);
                                if ($fix === true) {
                                    $phpcs_file->fixer->replace_token($comma_token + 1, ' ');
                                }
                            }
                        }
                        //end if
                    }
                    //end if
                }
                //end if
            }
            //end if
        }
        //end foreach
        // Only check spacing around parenthesis for single line definitions.
        if ($multi_line === true) {
            return;
        }
        $gap = 0;
        if ($tokens[$close_bracket - 1]['code'] === T_WHITESPACE) {
            $gap = $tokens[$close_bracket - 1]['length'];
        }
        if ($gap !== $this->required_spaces_before_close) {
            $error = 'Expected %s spaces before closing parenthesis; %s found';
            $data = [$this->required_spaces_before_close, $gap];
            $fix = $phpcs_file->add_fixable_error($error, $close_bracket, 'SpacingBeforeClose', $data);
            if ($fix === true) {
                $padding = str_repeat(' ', $this->required_spaces_before_close);
                if ($gap === 0) {
                    $phpcs_file->fixer->add_content_before($close_bracket, $padding);
                } else {
                    $phpcs_file->fixer->replace_token($close_bracket - 1, $padding);
                }
            }
        }
        $gap = 0;
        if ($tokens[$open_bracket + 1]['code'] === T_WHITESPACE) {
            $gap = $tokens[$open_bracket + 1]['length'];
        }
        if ($gap !== $this->required_spaces_after_open) {
            $error = 'Expected %s spaces after opening parenthesis; %s found';
            $data = [$this->required_spaces_after_open, $gap];
            $fix = $phpcs_file->add_fixable_error($error, $open_bracket, 'SpacingAfterOpen', $data);
            if ($fix === true) {
                $padding = str_repeat(' ', $this->required_spaces_after_open);
                if ($gap === 0) {
                    $phpcs_file->fixer->add_content($open_bracket, $padding);
                } else {
                    $phpcs_file->fixer->replace_token($open_bracket + 1, $padding);
                }
            }
        }
    }
    //end processBracket()
}
//end class