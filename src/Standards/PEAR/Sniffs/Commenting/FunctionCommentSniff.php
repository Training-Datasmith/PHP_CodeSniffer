<?php

declare (strict_types=1);
/**
 * Parses and verifies the doc comments for functions.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\PEAR\Sniffs\Commenting;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Function_Comment_Sniff implements Sniff
{
    /**
     * Disable the check for functions with a lower visibility than the value given.
     *
     * Allowed values are public, protected, and private.
     *
     * @var string
     */
    public $minimum_visibility = 'private';
    /**
     * Array of methods which do not require a return type.
     *
     * @var array
     */
    public $special_methods = ['__construct', '__destruct'];
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
        $scope_modifier = $phpcs_file->get_method_properties($stack_ptr)['scope'];
        if ($scope_modifier === 'protected' && $this->minimum_visibility === 'public' || $scope_modifier === 'private' && ($this->minimum_visibility === 'public' || $this->minimum_visibility === 'protected')) {
            return;
        }
        $tokens = $phpcs_file->get_tokens();
        $ignore = Tokens::$method_prefixes;
        $ignore[T_WHITESPACE] = T_WHITESPACE;
        for ($comment_end = $stack_ptr - 1; $comment_end >= 0; $comment_end--) {
            if (isset($ignore[$tokens[$comment_end]['code']]) === true) {
                continue;
            }
            if ($tokens[$comment_end]['code'] === T_ATTRIBUTE_END && isset($tokens[$comment_end]['attribute_opener']) === true) {
                $comment_end = $tokens[$comment_end]['attribute_opener'];
                continue;
            }
            break;
        }
        if ($tokens[$comment_end]['code'] === T_COMMENT) {
            // Inline comments might just be closing comments for
            // control structures or functions instead of function comments
            // using the wrong comment type. If there is other code on the line,
            // assume they relate to that code.
            $prev = $phpcs_file->find_previous($ignore, $comment_end - 1, null, true);
            if ($prev !== false && $tokens[$prev]['line'] === $tokens[$comment_end]['line']) {
                $comment_end = $prev;
            }
        }
        if ($tokens[$comment_end]['code'] !== T_DOC_COMMENT_CLOSE_TAG && $tokens[$comment_end]['code'] !== T_COMMENT) {
            $function = $phpcs_file->get_declaration_name($stack_ptr);
            $phpcs_file->add_error('Missing doc comment for function %s()', $stack_ptr, 'Missing', [$function]);
            $phpcs_file->record_metric($stack_ptr, 'Function has doc comment', 'no');
            return;
        }
        $phpcs_file->record_metric($stack_ptr, 'Function has doc comment', 'yes');
        if ($tokens[$comment_end]['code'] === T_COMMENT) {
            $phpcs_file->add_error('You must use "/**" style comments for a function comment', $stack_ptr, 'WrongStyle');
            return;
        }
        if ($tokens[$comment_end]['line'] !== $tokens[$stack_ptr]['line'] - 1) {
            for ($i = $comment_end + 1; $i < $stack_ptr; $i++) {
                if ($tokens[$i]['column'] !== 1) {
                    continue;
                }
                if ($tokens[$i]['code'] === T_WHITESPACE && $tokens[$i]['line'] !== $tokens[$i + 1]['line']) {
                    $error = 'There must be no blank lines after the function comment';
                    $fix = $phpcs_file->add_fixable_error($error, $comment_end, 'SpacingAfter');
                    if ($fix === true) {
                        $phpcs_file->fixer->begin_changeset();
                        while ($i < $stack_ptr && $tokens[$i]['code'] === T_WHITESPACE && $tokens[$i]['line'] !== $tokens[$i + 1]['line']) {
                            $phpcs_file->fixer->replace_token($i++, '');
                        }
                        $phpcs_file->fixer->end_changeset();
                    }
                    break;
                }
            }
            //end for
        }
        //end if
        $comment_start = $tokens[$comment_end]['comment_opener'];
        foreach ($tokens[$comment_start]['comment_tags'] as $tag) {
            if ($tokens[$tag]['content'] === '@see') {
                // Make sure the tag isn't empty.
                $string = $phpcs_file->find_next(T_DOC_COMMENT_STRING, $tag, $comment_end);
                if ($string === false || $tokens[$string]['line'] !== $tokens[$tag]['line']) {
                    $error = 'Content missing for @see tag in function comment';
                    $phpcs_file->add_error($error, $tag, 'EmptySees');
                }
            }
        }
        $this->process_return($phpcs_file, $stack_ptr, $comment_start);
        $this->process_throws($phpcs_file, $stack_ptr, $comment_start);
        $this->process_params($phpcs_file, $stack_ptr, $comment_start);
    }
    //end process()
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
        // Skip constructor and destructor.
        $method_name = $phpcs_file->get_declaration_name($stack_ptr);
        $is_special_method = in_array($method_name, $this->special_methods, true);
        $return = null;
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
        if ($return !== null) {
            $content = $tokens[$return + 2]['content'];
            if (empty($content) === true || $tokens[$return + 2]['code'] !== T_DOC_COMMENT_STRING) {
                $error = 'Return type missing for @return tag in function comment';
                $phpcs_file->add_error($error, $return, 'MissingReturnType');
            }
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
        foreach ($tokens[$comment_start]['comment_tags'] as $tag) {
            if ($tokens[$tag]['content'] !== '@throws') {
                continue;
            }
            $exception = null;
            if ($tokens[$tag + 2]['code'] === T_DOC_COMMENT_STRING) {
                $matches = [];
                preg_match('/([^\s]+)(?:\s+(.*))?/', $tokens[$tag + 2]['content'], $matches);
                $exception = $matches[1];
            }
            if ($exception === null) {
                $error = 'Exception type missing for @throws tag in function comment';
                $phpcs_file->add_error($error, $tag, 'InvalidThrows');
            }
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
        $tokens = $phpcs_file->get_tokens();
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
            $comment_end = 0;
            $comment_tokens = [];
            if ($tokens[$tag + 2]['code'] === T_DOC_COMMENT_STRING) {
                $matches = [];
                preg_match('/((?:(?![$.]|&(?=\$)).)*)(?:((?:\.\.\.)?(?:\$|&)[^\s]+)(?:(\s+)(.*))?)?/', $tokens[$tag + 2]['content'], $matches);
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
                        // Any strings until the next tag belong to this comment.
                        if (isset($tokens[$comment_start]['comment_tags'][$pos + 1]) === true) {
                            $end = $tokens[$comment_start]['comment_tags'][$pos + 1];
                        } else {
                            $end = $tokens[$comment_start]['comment_closer'];
                        }
                        for ($i = $tag + 3; $i < $end; $i++) {
                            if ($tokens[$i]['code'] === T_DOC_COMMENT_STRING) {
                                $comment .= ' ' . $tokens[$i]['content'];
                                $comment_end = $i;
                                $comment_tokens[] = $i;
                            }
                        }
                    } else {
                        $error = 'Missing parameter comment';
                        $phpcs_file->add_error($error, $tag, 'MissingParamComment');
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
            $params[] = ['tag' => $tag, 'type' => $type, 'var' => $var, 'comment' => $comment, 'comment_end' => $comment_end, 'comment_tokens' => $comment_tokens, 'type_space' => $type_space, 'var_space' => $var_space];
        }
        //end foreach
        $real_params = $phpcs_file->get_method_parameters($stack_ptr);
        $found_params = [];
        // We want to use ... for all variable length arguments, so add
        // this prefix to the variable name so comparisons are easier.
        foreach ($real_params as $pos => $param) {
            if ($param['variable_length'] === true) {
                $real_params[$pos]['name'] = '...' . $real_params[$pos]['name'];
            }
        }
        foreach ($params as $pos => $param) {
            if ($param['var'] === '') {
                continue;
            }
            $found_params[] = $param['var'];
            if (trim($param['type']) !== '') {
                // Check number of spaces after the type.
                $spaces = $max_type - strlen($param['type']) + 1;
                if ($param['type_space'] !== $spaces) {
                    $error = 'Expected %s spaces after parameter type; %s found';
                    $data = [$spaces, $param['type_space']];
                    $fix = $phpcs_file->add_fixable_error($error, $param['tag'], 'SpacingAfterParamType', $data);
                    if ($fix === true) {
                        $comment_token = $param['tag'] + 2;
                        $content = $param['type'];
                        $content .= str_repeat(' ', $spaces);
                        $content .= $param['var'];
                        $content .= str_repeat(' ', $param['var_space']);
                        $wrap_length = $tokens[$comment_token]['length'] - $param['type_space'] - $param['var_space'] - strlen($param['type']) - strlen($param['var']);
                        $star = $phpcs_file->find_previous(T_DOC_COMMENT_STAR, $param['tag']);
                        $space_length = strlen($content) + $tokens[$comment_token - 1]['length'] + $tokens[$comment_token - 2]['length'];
                        $padding = str_repeat(' ', $tokens[$star]['column'] - 1);
                        $padding .= '* ';
                        $padding .= str_repeat(' ', $space_length);
                        $content .= wordwrap($param['comment'], $wrap_length, $phpcs_file->eol_char . $padding);
                        $phpcs_file->fixer->replace_token($comment_token, $content);
                        for ($i = $comment_token + 1; $i <= $param['comment_end']; $i++) {
                            $phpcs_file->fixer->replace_token($i, '');
                        }
                    }
                    //end if
                }
                //end if
            }
            //end if
            // Make sure the param name is correct.
            if (isset($real_params[$pos]) === true) {
                $real_name = $real_params[$pos]['name'];
                if ($real_name !== $param['var']) {
                    $code = 'ParamNameNoMatch';
                    $data = [$param['var'], $real_name];
                    $error = 'Doc comment for parameter %s does not match ';
                    if (strtolower($param['var']) === strtolower($real_name)) {
                        $error .= 'case of ';
                        $code = 'ParamNameNoCaseMatch';
                    }
                    $error .= 'actual variable name %s';
                    $phpcs_file->add_error($error, $param['tag'], $code, $data);
                }
            } elseif (substr($param['var'], -4) !== ',...') {
                // We must have an extra parameter comment.
                $error = 'Superfluous parameter comment';
                $phpcs_file->add_error($error, $param['tag'], 'ExtraParamComment');
            }
            //end if
            if ($param['comment'] === '') {
                continue;
            }
            // Check number of spaces after the param name.
            $spaces = $max_var - strlen($param['var']) + 1;
            if ($param['var_space'] !== $spaces) {
                $error = 'Expected %s spaces after parameter name; %s found';
                $data = [$spaces, $param['var_space']];
                $fix = $phpcs_file->add_fixable_error($error, $param['tag'], 'SpacingAfterParamName', $data);
                if ($fix === true) {
                    $comment_token = $param['tag'] + 2;
                    $content = $param['type'];
                    $content .= str_repeat(' ', $param['type_space']);
                    $content .= $param['var'];
                    $content .= str_repeat(' ', $spaces);
                    $wrap_length = $tokens[$comment_token]['length'] - $param['type_space'] - $param['var_space'] - strlen($param['type']) - strlen($param['var']);
                    $star = $phpcs_file->find_previous(T_DOC_COMMENT_STAR, $param['tag']);
                    $space_length = strlen($content) + $tokens[$comment_token - 1]['length'] + $tokens[$comment_token - 2]['length'];
                    $padding = str_repeat(' ', $tokens[$star]['column'] - 1);
                    $padding .= '* ';
                    $padding .= str_repeat(' ', $space_length);
                    $content .= wordwrap($param['comment'], $wrap_length, $phpcs_file->eol_char . $padding);
                    $phpcs_file->fixer->replace_token($comment_token, $content);
                    for ($i = $comment_token + 1; $i <= $param['comment_end']; $i++) {
                        $phpcs_file->fixer->replace_token($i, '');
                    }
                }
                //end if
            }
            //end if
            // Check the alignment of multi-line param comments.
            if ($param['tag'] !== $param['comment_end']) {
                $wrap_length = $tokens[$param['tag'] + 2]['length'] - $param['type_space'] - $param['var_space'] - strlen($param['type']) - strlen($param['var']);
                $start_column = $tokens[$param['tag'] + 2]['column'] + $tokens[$param['tag'] + 2]['length'] - $wrap_length;
                $star = $phpcs_file->find_previous(T_DOC_COMMENT_STAR, $param['tag']);
                $expected = $start_column - $tokens[$star]['column'] - 1;
                foreach ($param['comment_tokens'] as $comment_token) {
                    if ($tokens[$comment_token]['column'] === $start_column) {
                        continue;
                    }
                    $found = 0;
                    if ($tokens[$comment_token - 1]['code'] === T_DOC_COMMENT_WHITESPACE) {
                        $found = $tokens[$comment_token - 1]['length'];
                    }
                    $error = 'Parameter comment not aligned correctly; expected %s spaces but found %s';
                    $data = [$expected, $found];
                    if ($found < $expected) {
                        $code = 'ParamCommentAlignment';
                    } else {
                        $code = 'ParamCommentAlignmentExceeded';
                    }
                    $fix = $phpcs_file->add_fixable_error($error, $comment_token, $code, $data);
                    if ($fix === true) {
                        $padding = str_repeat(' ', $expected);
                        if ($tokens[$comment_token - 1]['code'] === T_DOC_COMMENT_WHITESPACE) {
                            $phpcs_file->fixer->replace_token($comment_token - 1, $padding);
                        } else {
                            $phpcs_file->fixer->add_content_before($comment_token, $padding);
                        }
                    }
                }
                //end foreach
            }
            //end if
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
}
//end class