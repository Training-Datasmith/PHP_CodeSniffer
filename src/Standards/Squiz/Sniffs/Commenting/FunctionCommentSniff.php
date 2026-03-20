<?php

declare (strict_types=1);
/**
 * Parses and verifies the doc comments for functions.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\Commenting;

use Php_code_Sniffer\Config;
use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Standards\PEAR\Sniffs\Commenting\Function_Comment_Sniff as PEARFunctionCommentSniff;
use Php_code_Sniffer\Util\Common;
class Function_Comment_Sniff extends Pear_Function_Comment_Sniff
{
    /**
     * Whether to skip inheritdoc comments.
     *
     * @var boolean
     */
    public $skip_if_inheritdoc = false;
    /**
     * The current PHP version.
     *
     * @var integer
     */
    private $php_version;
    /**
     * Process the return comment of this function comment.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile    The file being scanned.
     * @param int                         $stackPtr     The position of the current token
     *                                                  in the stack passed in $tokens.
     * @param int                         $commentStart The position in the stack where the comment started.
     *
     * @return void
     */
    protected function process_return(File $phpcs_file, $stack_ptr, $comment_start)
    {
        $tokens = $phpcs_file->get_tokens();
        $return = null;
        if ($this->skip_if_inheritdoc === true) {
            if ($this->check_inheritdoc($phpcs_file, $stack_ptr, $comment_start) === true) {
                return;
            }
        }
        foreach ($tokens[$comment_start]['comment_tags'] as $tag) {
            if ($tokens[$tag]['content'] === '@return') {
                if ($return !== null) {
                    $error = 'Only 1 @return tag is allowed in a function comment';
                    $phpcs_file->add_error($error, $tag, 'DuplicateReturn');
                    return;
                }
                $return = $tag;
            }
        }
        // Skip constructor and destructor.
        $method_name = $phpcs_file->get_declaration_name($stack_ptr);
        $is_special_method = in_array($method_name, $this->special_methods, true);
        if ($return !== null) {
            $content = $tokens[$return + 2]['content'];
            if (empty($content) === true || $tokens[$return + 2]['code'] !== T_DOC_COMMENT_STRING) {
                $error = 'Return type missing for @return tag in function comment';
                $phpcs_file->add_error($error, $return, 'MissingReturnType');
            } else {
                // Support both a return type and a description.
                preg_match('`^((?:\|?(?:array\([^\)]*\)|[\\\\a-z0-9\[\]]+))*)( .*)?`i', $content, $return_parts);
                if (isset($return_parts[1]) === false) {
                    return;
                }
                $return_type = $return_parts[1];
                // Check return type (can be multiple, separated by '|').
                $type_names = explode('|', $return_type);
                $suggested_names = [];
                foreach ($type_names as $type_name) {
                    $suggested_name = Common::suggest_type($type_name);
                    if (in_array($suggested_name, $suggested_names, true) === false) {
                        $suggested_names[] = $suggested_name;
                    }
                }
                $suggested_type = implode('|', $suggested_names);
                if ($return_type !== $suggested_type) {
                    $error = 'Expected "%s" but found "%s" for function return type';
                    $data = [$suggested_type, $return_type];
                    $fix = $phpcs_file->add_fixable_error($error, $return, 'InvalidReturn', $data);
                    if ($fix === true) {
                        $replacement = $suggested_type;
                        if (empty($return_parts[2]) === false) {
                            $replacement .= $return_parts[2];
                        }
                        $phpcs_file->fixer->replace_token($return + 2, $replacement);
                        unset($replacement);
                    }
                }
                // If the return type is void, make sure there is
                // no return statement in the function.
                if ($return_type === 'void') {
                    if (isset($tokens[$stack_ptr]['scope_closer']) === true) {
                        $end_token = $tokens[$stack_ptr]['scope_closer'];
                        for ($return_token = $stack_ptr; $return_token < $end_token; $return_token++) {
                            if ($tokens[$return_token]['code'] === T_CLOSURE || $tokens[$return_token]['code'] === T_ANON_CLASS) {
                                $return_token = $tokens[$return_token]['scope_closer'];
                                continue;
                            }
                            if ($tokens[$return_token]['code'] === T_RETURN || $tokens[$return_token]['code'] === T_YIELD || $tokens[$return_token]['code'] === T_YIELD_FROM) {
                                break;
                            }
                        }
                        if ($return_token !== $end_token) {
                            // If the function is not returning anything, just
                            // exiting, then there is no problem.
                            $semicolon = $phpcs_file->find_next(T_WHITESPACE, $return_token + 1, null, true);
                            if ($tokens[$semicolon]['code'] !== T_SEMICOLON) {
                                $error = 'Function return type is void, but function contains return statement';
                                $phpcs_file->add_error($error, $return, 'InvalidReturnVoid');
                            }
                        }
                    }
                    //end if
                } elseif ($return_type !== 'mixed' && $return_type !== 'never' && in_array('void', $type_names, true) === false) {
                    // If return type is not void, never, or mixed, there needs to be a
                    // return statement somewhere in the function that returns something.
                    if (isset($tokens[$stack_ptr]['scope_closer']) === true) {
                        $end_token = $tokens[$stack_ptr]['scope_closer'];
                        for ($return_token = $stack_ptr; $return_token < $end_token; $return_token++) {
                            if ($tokens[$return_token]['code'] === T_CLOSURE || $tokens[$return_token]['code'] === T_ANON_CLASS) {
                                $return_token = $tokens[$return_token]['scope_closer'];
                                continue;
                            }
                            if ($tokens[$return_token]['code'] === T_RETURN || $tokens[$return_token]['code'] === T_YIELD || $tokens[$return_token]['code'] === T_YIELD_FROM) {
                                break;
                            }
                        }
                        if ($return_token === $end_token) {
                            $error = 'Function return type is not void, but function has no return statement';
                            $phpcs_file->add_error($error, $return, 'InvalidNoReturn');
                        } else {
                            $semicolon = $phpcs_file->find_next(T_WHITESPACE, $return_token + 1, null, true);
                            if ($tokens[$semicolon]['code'] === T_SEMICOLON) {
                                $error = 'Function return type is not void, but function is returning void here';
                                $phpcs_file->add_error($error, $return_token, 'InvalidReturnNotVoid');
                            }
                        }
                    }
                    //end if
                }
                //end if
            }
            //end if
        } else {
            if ($is_special_method === true) {
                return;
            }
            $error = 'Missing @return tag in function comment';
            $phpcs_file->add_error($error, $tokens[$comment_start]['comment_closer'], 'MissingReturn');
        }
        //end if
    }
    //end processReturn()
    /**
     * Process any throw tags that this function comment has.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile    The file being scanned.
     * @param int                         $stackPtr     The position of the current token
     *                                                  in the stack passed in $tokens.
     * @param int                         $commentStart The position in the stack where the comment started.
     *
     * @return void
     */
    protected function process_throws(File $phpcs_file, $stack_ptr, $comment_start)
    {
        $tokens = $phpcs_file->get_tokens();
        if ($this->skip_if_inheritdoc === true) {
            if ($this->check_inheritdoc($phpcs_file, $stack_ptr, $comment_start) === true) {
                return;
            }
        }
        foreach ($tokens[$comment_start]['comment_tags'] as $pos => $tag) {
            if ($tokens[$tag]['content'] !== '@throws') {
                continue;
            }
            $exception = null;
            $comment = null;
            if ($tokens[$tag + 2]['code'] === T_DOC_COMMENT_STRING) {
                $matches = [];
                preg_match('/([^\s]+)(?:\s+(.*))?/', $tokens[$tag + 2]['content'], $matches);
                $exception = $matches[1];
                if (isset($matches[2]) === true && trim($matches[2]) !== '') {
                    $comment = $matches[2];
                }
            }
            if ($exception === null) {
                $error = 'Exception type and comment missing for @throws tag in function comment';
                $phpcs_file->add_error($error, $tag, 'InvalidThrows');
            } elseif ($comment === null) {
                $error = 'Comment missing for @throws tag in function comment';
                $phpcs_file->add_error($error, $tag, 'EmptyThrows');
            } else {
                // Any strings until the next tag belong to this comment.
                if (isset($tokens[$comment_start]['comment_tags'][$pos + 1]) === true) {
                    $end = $tokens[$comment_start]['comment_tags'][$pos + 1];
                } else {
                    $end = $tokens[$comment_start]['comment_closer'];
                }
                for ($i = $tag + 3; $i < $end; $i++) {
                    if ($tokens[$i]['code'] === T_DOC_COMMENT_STRING) {
                        $comment .= ' ' . $tokens[$i]['content'];
                    }
                }
                $comment = trim($comment);
                // Starts with a capital letter and ends with a fullstop.
                $first_char = $comment[0];
                if (strtoupper($first_char) !== $first_char) {
                    $error = '@throws tag comment must start with a capital letter';
                    $phpcs_file->add_error($error, $tag + 2, 'ThrowsNotCapital');
                }
                $last_char = substr($comment, -1);
                if ($last_char !== '.') {
                    $error = '@throws tag comment must end with a full stop';
                    $phpcs_file->add_error($error, $tag + 2, 'ThrowsNoFullStop');
                }
            }
            //end if
        }
        //end foreach
    }
    //end processThrows()
    /**
     * Process the function parameter comments.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile    The file being scanned.
     * @param int                         $stackPtr     The position of the current token
     *                                                  in the stack passed in $tokens.
     * @param int                         $commentStart The position in the stack where the comment started.
     *
     * @return void
     */
    protected function process_params(File $phpcs_file, $stack_ptr, $comment_start)
    {
        if ($this->php_version === null) {
            $this->php_version = Config::get_config_data('php_version');
            if ($this->php_version === null) {
                $this->php_version = PHP_VERSION_ID;
            }
        }
        $tokens = $phpcs_file->get_tokens();
        if ($this->skip_if_inheritdoc === true) {
            if ($this->check_inheritdoc($phpcs_file, $stack_ptr, $comment_start) === true) {
                return;
            }
        }
        $params = [];
        $max_type = 0;
        $max_var = 0;
        foreach ($tokens[$comment_start]['comment_tags'] as $pos => $tag) {
            if ($tokens[$tag]['content'] !== '@param') {
                continue;
            }
            $type = '';
            $type_space = 0;
            $var = '';
            $var_space = 0;
            $comment = '';
            $comment_lines = [];
            if ($tokens[$tag + 2]['code'] === T_DOC_COMMENT_STRING) {
                $matches = [];
                preg_match('/([^$&.]+)(?:((?:\.\.\.)?(?:\$|&)[^\s]+)(?:(\s+)(.*))?)?/', $tokens[$tag + 2]['content'], $matches);
                if (empty($matches) === false) {
                    $type_len = strlen($matches[1]);
                    $type = trim($matches[1]);
                    $type_space = $type_len - strlen($type);
                    $type_len = strlen($type);
                    if ($type_len > $max_type) {
                        $max_type = $type_len;
                    }
                }
                if (isset($matches[2]) === true) {
                    $var = $matches[2];
                    $var_len = strlen($var);
                    if ($var_len > $max_var) {
                        $max_var = $var_len;
                    }
                    if (isset($matches[4]) === true) {
                        $var_space = strlen($matches[3]);
                        $comment = $matches[4];
                        $comment_lines[] = ['comment' => $comment, 'token' => $tag + 2, 'indent' => $var_space];
                        // Any strings until the next tag belong to this comment.
                        if (isset($tokens[$comment_start]['comment_tags'][$pos + 1]) === true) {
                            $end = $tokens[$comment_start]['comment_tags'][$pos + 1];
                        } else {
                            $end = $tokens[$comment_start]['comment_closer'];
                        }
                        for ($i = $tag + 3; $i < $end; $i++) {
                            if ($tokens[$i]['code'] === T_DOC_COMMENT_STRING) {
                                $indent = 0;
                                if ($tokens[$i - 1]['code'] === T_DOC_COMMENT_WHITESPACE) {
                                    $indent = $tokens[$i - 1]['length'];
                                }
                                $comment .= ' ' . $tokens[$i]['content'];
                                $comment_lines[] = ['comment' => $tokens[$i]['content'], 'token' => $i, 'indent' => $indent];
                            }
                        }
                    } else {
                        $error = 'Missing parameter comment';
                        $phpcs_file->add_error($error, $tag, 'MissingParamComment');
                        $comment_lines[] = ['comment' => ''];
                    }
                    //end if
                } else {
                    $error = 'Missing parameter name';
                    $phpcs_file->add_error($error, $tag, 'MissingParamName');
                }
                //end if
            } else {
                $error = 'Missing parameter type';
                $phpcs_file->add_error($error, $tag, 'MissingParamType');
            }
            //end if
            $params[] = ['tag' => $tag, 'type' => $type, 'var' => $var, 'comment' => $comment, 'commentLines' => $comment_lines, 'type_space' => $type_space, 'var_space' => $var_space];
        }
        //end foreach
        $real_params = $phpcs_file->get_method_parameters($stack_ptr);
        $found_params = [];
        // We want to use ... for all variable length arguments, so added
        // this prefix to the variable name so comparisons are easier.
        foreach ($real_params as $pos => $param) {
            if ($param['variable_length'] === true) {
                $real_params[$pos]['name'] = '...' . $real_params[$pos]['name'];
            }
        }
        foreach ($params as $pos => $param) {
            // If the type is empty, the whole line is empty.
            if ($param['type'] === '') {
                continue;
            }
            // Check the param type value.
            $type_names = explode('|', $param['type']);
            $suggested_type_names = [];
            foreach ($type_names as $type_name) {
                if ($type_name === '') {
                    continue;
                }
                // Strip nullable operator.
                if ($type_name[0] === '?') {
                    $type_name = substr($type_name, 1);
                }
                $suggested_name = Common::suggest_type($type_name);
                $suggested_type_names[] = $suggested_name;
                if (count($type_names) > 1) {
                    continue;
                }
                // Check type hint for array and custom type.
                $suggested_type_hint = '';
                if (strpos($suggested_name, 'array') !== false || substr($suggested_name, -2) === '[]') {
                    $suggested_type_hint = 'array';
                } elseif (strpos($suggested_name, 'callable') !== false) {
                    $suggested_type_hint = 'callable';
                } elseif (strpos($suggested_name, 'callback') !== false) {
                    $suggested_type_hint = 'callable';
                } elseif (in_array($suggested_name, Common::$allowed_types, true) === false) {
                    $suggested_type_hint = $suggested_name;
                }
                if ($this->php_version >= 70000) {
                    if ($suggested_name === 'string') {
                        $suggested_type_hint = 'string';
                    } elseif ($suggested_name === 'int' || $suggested_name === 'integer') {
                        $suggested_type_hint = 'int';
                    } elseif ($suggested_name === 'float') {
                        $suggested_type_hint = 'float';
                    } elseif ($suggested_name === 'bool' || $suggested_name === 'boolean') {
                        $suggested_type_hint = 'bool';
                    }
                }
                if ($this->php_version >= 70200) {
                    if ($suggested_name === 'object') {
                        $suggested_type_hint = 'object';
                    }
                }
                if ($this->php_version >= 80000) {
                    if ($suggested_name === 'mixed') {
                        $suggested_type_hint = 'mixed';
                    }
                }
                if ($suggested_type_hint !== '' && isset($real_params[$pos]) === true) {
                    $type_hint = $real_params[$pos]['type_hint'];
                    // Remove namespace prefixes when comparing.
                    $compare_type_hint = substr($suggested_type_hint, strlen($type_hint) * -1);
                    if ($type_hint === '') {
                        $error = 'Type hint "%s" missing for %s';
                        $data = [$suggested_type_hint, $param['var']];
                        $error_code = 'TypeHintMissing';
                        if ($suggested_type_hint === 'string' || $suggested_type_hint === 'int' || $suggested_type_hint === 'float' || $suggested_type_hint === 'bool') {
                            $error_code = 'Scalar' . $error_code;
                        }
                        $phpcs_file->add_error($error, $stack_ptr, $error_code, $data);
                    } elseif ($type_hint !== $compare_type_hint && $type_hint !== '?' . $compare_type_hint) {
                        $error = 'Expected type hint "%s"; found "%s" for %s';
                        $data = [$suggested_type_hint, $type_hint, $param['var']];
                        $phpcs_file->add_error($error, $stack_ptr, 'IncorrectTypeHint', $data);
                    }
                    //end if
                } elseif ($suggested_type_hint === '' && isset($real_params[$pos]) === true) {
                    $type_hint = $real_params[$pos]['type_hint'];
                    if ($type_hint !== '') {
                        $error = 'Unknown type hint "%s" found for %s';
                        $data = [$type_hint, $param['var']];
                        $phpcs_file->add_error($error, $stack_ptr, 'InvalidTypeHint', $data);
                    }
                }
                //end if
            }
            //end foreach
            $suggested_type = implode('|', $suggested_type_names);
            if ($param['type'] !== $suggested_type) {
                $error = 'Expected "%s" but found "%s" for parameter type';
                $data = [$suggested_type, $param['type']];
                $fix = $phpcs_file->add_fixable_error($error, $param['tag'], 'IncorrectParamVarName', $data);
                if ($fix === true) {
                    $phpcs_file->fixer->begin_changeset();
                    $content = $suggested_type;
                    $content .= str_repeat(' ', $param['type_space']);
                    $content .= $param['var'];
                    $content .= str_repeat(' ', $param['var_space']);
                    if (isset($param['commentLines'][0]) === true) {
                        $content .= $param['commentLines'][0]['comment'];
                    }
                    $phpcs_file->fixer->replace_token($param['tag'] + 2, $content);
                    // Fix up the indent of additional comment lines.
                    foreach ($param['commentLines'] as $line_num => $line) {
                        if ($line_num === 0) {
                            continue;
                        }
                        if ($param['commentLines'][$line_num]['indent'] === 0) {
                            continue;
                        }
                        $diff = strlen($param['type']) - strlen($suggested_type);
                        $new_indent = $param['commentLines'][$line_num]['indent'] - $diff;
                        $phpcs_file->fixer->replace_token($param['commentLines'][$line_num]['token'] - 1, str_repeat(' ', $new_indent));
                    }
                    $phpcs_file->fixer->end_changeset();
                }
                //end if
            }
            //end if
            if ($param['var'] === '') {
                continue;
            }
            $found_params[] = $param['var'];
            // Check number of spaces after the type.
            $this->check_spacing_after_param_type($phpcs_file, $param, $max_type);
            // Make sure the param name is correct.
            if (isset($real_params[$pos]) === true) {
                $real_name = $real_params[$pos]['name'];
                $param_var_name = $param['var'];
                if ($param['var'][0] === '&') {
                    // Even when passed by reference, the variable name in $realParams does not have
                    // a leading '&'. This sniff will accept both '&$var' and '$var' in these cases.
                    $param_var_name = substr($param['var'], 1);
                    // This makes sure that the 'MissingParamTag' check won't throw a false positive.
                    $found_params[count($found_params) - 1] = $param_var_name;
                    if ($real_params[$pos]['pass_by_reference'] !== true && $real_name === $param_var_name) {
                        // Don't complain about this unless the param name is otherwise correct.
                        $error = 'Doc comment for parameter %s is prefixed with "&" but parameter is not passed by reference';
                        $code = 'ParamNameUnexpectedAmpersandPrefix';
                        $data = [$param_var_name];
                        // We're not offering an auto-fix here because we can't tell if the docblock
                        // is wrong, or the parameter should be passed by reference.
                        $phpcs_file->add_error($error, $param['tag'], $code, $data);
                    }
                }
                if ($real_name !== $param_var_name) {
                    $code = 'ParamNameNoMatch';
                    $data = [$param_var_name, $real_name];
                    $error = 'Doc comment for parameter %s does not match ';
                    if (strtolower($param_var_name) === strtolower($real_name)) {
                        $error .= 'case of ';
                        $code = 'ParamNameNoCaseMatch';
                    }
                    $error .= 'actual variable name %s';
                    $phpcs_file->add_error($error, $param['tag'], $code, $data);
                }
                //end if
            } elseif (substr($param['var'], -4) !== ',...') {
                // We must have an extra parameter comment.
                $error = 'Superfluous parameter comment';
                $phpcs_file->add_error($error, $param['tag'], 'ExtraParamComment');
            }
            //end if
            if ($param['comment'] === '') {
                continue;
            }
            // Check number of spaces after the var name.
            $this->check_spacing_after_param_name($phpcs_file, $param, $max_var);
            // Param comments must start with a capital letter and end with a full stop.
            if (preg_match('/^(\p{Ll}|\P{L})/u', $param['comment']) === 1) {
                $error = 'Parameter comment must start with a capital letter';
                $phpcs_file->add_error($error, $param['tag'], 'ParamCommentNotCapital');
            }
            $last_char = substr($param['comment'], -1);
            if ($last_char !== '.') {
                $error = 'Parameter comment must end with a full stop';
                $phpcs_file->add_error($error, $param['tag'], 'ParamCommentFullStop');
            }
        }
        //end foreach
        $real_names = [];
        foreach ($real_params as $real_param) {
            $real_names[] = $real_param['name'];
        }
        // Report missing comments.
        $diff = array_diff($real_names, $found_params);
        foreach ($diff as $needed_param) {
            $error = 'Doc comment for parameter "%s" missing';
            $data = [$needed_param];
            $phpcs_file->add_error($error, $comment_start, 'MissingParamTag', $data);
        }
    }
    //end processParams()
    /**
     * Check the spacing after the type of a parameter.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param array                       $param     The parameter to be checked.
     * @param int                         $maxType   The maxlength of the longest parameter type.
     * @param int                         $spacing   The number of spaces to add after the type.
     *
     * @return void
     */
    protected function check_spacing_after_param_type(File $phpcs_file, array $param, $max_type, $spacing = 1)
    {
        // Check number of spaces after the type.
        $spaces = $max_type - strlen($param['type']) + $spacing;
        if ($param['type_space'] !== $spaces) {
            $error = 'Expected %s spaces after parameter type; %s found';
            $data = [$spaces, $param['type_space']];
            $fix = $phpcs_file->add_fixable_error($error, $param['tag'], 'SpacingAfterParamType', $data);
            if ($fix === true) {
                $phpcs_file->fixer->begin_changeset();
                $content = $param['type'];
                $content .= str_repeat(' ', $spaces);
                $content .= $param['var'];
                $content .= str_repeat(' ', $param['var_space']);
                $content .= $param['commentLines'][0]['comment'];
                $phpcs_file->fixer->replace_token($param['tag'] + 2, $content);
                // Fix up the indent of additional comment lines.
                $diff = $param['type_space'] - $spaces;
                foreach ($param['commentLines'] as $line_num => $line) {
                    if ($line_num === 0) {
                        continue;
                    }
                    if ($param['commentLines'][$line_num]['indent'] === 0) {
                        continue;
                    }
                    $new_indent = $param['commentLines'][$line_num]['indent'] - $diff;
                    if ($new_indent <= 0) {
                        continue;
                    }
                    $phpcs_file->fixer->replace_token($param['commentLines'][$line_num]['token'] - 1, str_repeat(' ', $new_indent));
                }
                $phpcs_file->fixer->end_changeset();
            }
            //end if
        }
        //end if
    }
    //end checkSpacingAfterParamType()
    /**
     * Check the spacing after the name of a parameter.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param array                       $param     The parameter to be checked.
     * @param int                         $maxVar    The maxlength of the longest parameter name.
     * @param int                         $spacing   The number of spaces to add after the type.
     *
     * @return void
     */
    protected function check_spacing_after_param_name(File $phpcs_file, array $param, $max_var, $spacing = 1)
    {
        // Check number of spaces after the var name.
        $spaces = $max_var - strlen($param['var']) + $spacing;
        if ($param['var_space'] !== $spaces) {
            $error = 'Expected %s spaces after parameter name; %s found';
            $data = [$spaces, $param['var_space']];
            $fix = $phpcs_file->add_fixable_error($error, $param['tag'], 'SpacingAfterParamName', $data);
            if ($fix === true) {
                $phpcs_file->fixer->begin_changeset();
                $content = $param['type'];
                $content .= str_repeat(' ', $param['type_space']);
                $content .= $param['var'];
                $content .= str_repeat(' ', $spaces);
                $content .= $param['commentLines'][0]['comment'];
                $phpcs_file->fixer->replace_token($param['tag'] + 2, $content);
                // Fix up the indent of additional comment lines.
                foreach ($param['commentLines'] as $line_num => $line) {
                    if ($line_num === 0) {
                        continue;
                    }
                    if ($param['commentLines'][$line_num]['indent'] === 0) {
                        continue;
                    }
                    $diff = $param['var_space'] - $spaces;
                    $new_indent = $param['commentLines'][$line_num]['indent'] - $diff;
                    if ($new_indent <= 0) {
                        continue;
                    }
                    $phpcs_file->fixer->replace_token($param['commentLines'][$line_num]['token'] - 1, str_repeat(' ', $new_indent));
                }
                $phpcs_file->fixer->end_changeset();
            }
            //end if
        }
        //end if
    }
    //end checkSpacingAfterParamName()
    /**
     * Determines whether the whole comment is an inheritdoc comment.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile    The file being scanned.
     * @param int                         $stackPtr     The position of the current token
     *                                                  in the stack passed in $tokens.
     * @param int                         $commentStart The position in the stack where the comment started.
     *
     * @return boolean TRUE if the docblock contains only {@inheritdoc} (case-insensitive).
     */
    protected function check_inheritdoc(File $phpcs_file, $stack_ptr, $comment_start)
    {
        $tokens = $phpcs_file->get_tokens();
        $allowed_tokens = [T_DOC_COMMENT_OPEN_TAG, T_DOC_COMMENT_WHITESPACE, T_DOC_COMMENT_STAR];
        for ($i = $comment_start; $i <= $tokens[$comment_start]['comment_closer']; $i++) {
            if (in_array($tokens[$i]['code'], $allowed_tokens) === false) {
                $trimmed_content = strtolower(trim($tokens[$i]['content']));
                if ($trimmed_content === '{@inheritdoc}') {
                    return true;
                }
                return false;
            }
        }
        return false;
    }
    //end checkInheritdoc()
}
//end class