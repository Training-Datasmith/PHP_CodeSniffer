<?php

declare (strict_types=1);
/**
 * Checks for unused function parameters.
 *
 * This sniff checks that all function parameters are used in the function body.
 * One exception is made for empty function bodies or function bodies that only
 * contain comments. This could be useful for the classes that implement an
 * interface that defines multiple methods but the implementation only needs some
 * of them.
 *
 * @author    Manuel Pichler <mapi@manuel-pichler.de>
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2007-2014 Manuel Pichler. All rights reserved.
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Generic\Sniffs\Code_Analysis;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Unused_Function_Parameter_Sniff implements Sniff
{
    /**
     * The list of class type hints which will be ignored.
     *
     * @var array
     */
    public $ignore_type_hints = [];
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
     *                                               in the stack passed in $tokens.
     *
     * @return void
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        $token = $tokens[$stack_ptr];
        // Skip broken function declarations.
        if (isset($token['scope_opener']) === false || isset($token['parenthesis_opener']) === false) {
            return;
        }
        $error_code = 'Found';
        $implements = false;
        $extends = false;
        $class_ptr = $phpcs_file->get_condition($stack_ptr, T_CLASS);
        if ($class_ptr !== false) {
            $implements = $phpcs_file->find_implemented_interface_names($class_ptr);
            $extends = $phpcs_file->find_extended_class_name($class_ptr);
            if ($extends !== false) {
                $error_code .= 'InExtendedClass';
            } elseif ($implements !== false) {
                $error_code .= 'InImplementedInterface';
            }
        }
        $params = [];
        $method_params = $phpcs_file->get_method_parameters($stack_ptr);
        // Skip when no parameters found.
        $method_params_count = count($method_params);
        if ($method_params_count === 0) {
            return;
        }
        foreach ($method_params as $param) {
            if (isset($param['property_visibility']) === true) {
                // Ignore constructor property promotion.
                continue;
            }
            $params[$param['name']] = $stack_ptr;
        }
        $next = ++$token['scope_opener'];
        $end = --$token['scope_closer'];
        // Check the end token for arrow functions as
        // they can end at a content token due to not having
        // a clearly defined closing token.
        if ($token['code'] === T_FN) {
            ++$end;
        }
        $found_content = false;
        $valid_tokens = [T_HEREDOC => T_HEREDOC, T_NOWDOC => T_NOWDOC, T_END_HEREDOC => T_END_HEREDOC, T_END_NOWDOC => T_END_NOWDOC, T_DOUBLE_QUOTED_STRING => T_DOUBLE_QUOTED_STRING];
        $valid_tokens += Tokens::$empty_tokens;
        for (; $next <= $end; ++$next) {
            $token = $tokens[$next];
            $code = $token['code'];
            // Ignorable tokens.
            if (isset(Tokens::$empty_tokens[$code]) === true) {
                continue;
            }
            if ($found_content === false) {
                // A throw statement as the first content indicates an interface method.
                if ($code === T_THROW && $implements !== false) {
                    return;
                }
                // A return statement as the first content indicates an interface method.
                if ($code === T_RETURN) {
                    $tmp = $phpcs_file->find_next(Tokens::$empty_tokens, $next + 1, null, true);
                    if ($tmp === false && $implements !== false) {
                        return;
                    }
                    // There is a return.
                    if ($tokens[$tmp]['code'] === T_SEMICOLON && $implements !== false) {
                        return;
                    }
                    $tmp = $phpcs_file->find_next(Tokens::$empty_tokens, $tmp + 1, null, true);
                    if ($tmp !== false && $tokens[$tmp]['code'] === T_SEMICOLON && $implements !== false) {
                        // There is a return <token>.
                        return;
                    }
                }
                //end if
            }
            //end if
            $found_content = true;
            if ($code === T_VARIABLE && isset($params[$token['content']]) === true) {
                unset($params[$token['content']]);
            } elseif ($code === T_DOLLAR) {
                $next_token = $phpcs_file->find_next(T_WHITESPACE, $next + 1, null, true);
                if ($tokens[$next_token]['code'] === T_OPEN_CURLY_BRACKET) {
                    $next_token = $phpcs_file->find_next(T_WHITESPACE, $next_token + 1, null, true);
                    if ($tokens[$next_token]['code'] === T_STRING) {
                        $var_content = '$' . $tokens[$next_token]['content'];
                        if (isset($params[$var_content]) === true) {
                            unset($params[$var_content]);
                        }
                    }
                }
            } elseif ($code === T_DOUBLE_QUOTED_STRING || $code === T_START_HEREDOC || $code === T_START_NOWDOC) {
                // Tokenize strings that can contain variables.
                // Make sure the string is re-joined if it occurs over multiple lines.
                $content = $token['content'];
                for ($i = $next + 1; $i <= $end; $i++) {
                    if (isset($valid_tokens[$tokens[$i]['code']]) === true) {
                        $content .= $tokens[$i]['content'];
                        $next++;
                    } else {
                        break;
                    }
                }
                $string_tokens = token_get_all(sprintf('<?php %s;?>', $content));
                foreach ($string_tokens as $string_ptr => $string_token) {
                    if (is_array($string_token) === false) {
                        continue;
                    }
                    $var_content = '';
                    if ($string_token[0] === T_DOLLAR_OPEN_CURLY_BRACES) {
                        $var_content = '$' . $string_tokens[$string_ptr + 1][1];
                    } elseif ($string_token[0] === T_VARIABLE) {
                        $var_content = $string_token[1];
                    }
                    if ($var_content !== '' && isset($params[$var_content]) === true) {
                        unset($params[$var_content]);
                    }
                }
            }
            //end if
        }
        //end for
        if ($found_content === true && count($params) > 0) {
            $error = 'The method parameter %s is never used';
            // If there is only one parameter and it is unused, no need for additional errorcode toggling logic.
            if ($method_params_count === 1) {
                foreach ($params as $param_name => $position) {
                    if (in_array($method_params[0]['type_hint'], $this->ignore_type_hints, true) === true) {
                        continue;
                    }
                    $data = [$param_name];
                    $phpcs_file->add_warning($error, $position, $error_code, $data);
                }
                return;
            }
            $found_last_used = false;
            $last_index = $method_params_count - 1;
            $error_info = [];
            for ($i = $last_index; $i >= 0; --$i) {
                if ($found_last_used !== false) {
                    if (isset($params[$method_params[$i]['name']]) === true) {
                        $error_info[$method_params[$i]['name']] = ['position' => $params[$method_params[$i]['name']], 'errorcode' => $error_code . 'BeforeLastUsed', 'typehint' => $method_params[$i]['type_hint']];
                    }
                } else if (isset($params[$method_params[$i]['name']]) === false) {
                    $found_last_used = true;
                } else {
                    $error_info[$method_params[$i]['name']] = ['position' => $params[$method_params[$i]['name']], 'errorcode' => $error_code . 'AfterLastUsed', 'typehint' => $method_params[$i]['type_hint']];
                }
            }
            //end for
            if (count($error_info) > 0) {
                $error_info = array_reverse($error_info);
                foreach ($error_info as $param_name => $info) {
                    if (in_array($info['typehint'], $this->ignore_type_hints, true) === true) {
                        continue;
                    }
                    $data = [$param_name];
                    $phpcs_file->add_warning($error, $info['position'], $info['errorcode'], $data);
                }
            }
        }
        //end if
    }
    //end process()
}
//end class