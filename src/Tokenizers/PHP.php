<?php

declare (strict_types=1);
/**
 * Tokenizes PHP code.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Tokenizers;

use Php_code_Sniffer\Util;
class PHP extends Tokenizer
{
    /**
     * A list of tokens that are allowed to open a scope.
     *
     * This array also contains information about what kind of token the scope
     * opener uses to open and close the scope, if the token strictly requires
     * an opener, if the token can share a scope closer, and who it can be shared
     * with. An example of a token that shares a scope closer is a CASE scope.
     *
     * @var array
     */
    public $scope_openers = [T_IF => ['start' => [T_OPEN_CURLY_BRACKET => T_OPEN_CURLY_BRACKET, T_COLON => T_COLON], 'end' => [T_CLOSE_CURLY_BRACKET => T_CLOSE_CURLY_BRACKET, T_ENDIF => T_ENDIF, T_ELSE => T_ELSE, T_ELSEIF => T_ELSEIF], 'strict' => false, 'shared' => false, 'with' => [T_ELSE => T_ELSE, T_ELSEIF => T_ELSEIF]], T_TRY => ['start' => [T_OPEN_CURLY_BRACKET => T_OPEN_CURLY_BRACKET], 'end' => [T_CLOSE_CURLY_BRACKET => T_CLOSE_CURLY_BRACKET], 'strict' => true, 'shared' => false, 'with' => []], T_CATCH => ['start' => [T_OPEN_CURLY_BRACKET => T_OPEN_CURLY_BRACKET], 'end' => [T_CLOSE_CURLY_BRACKET => T_CLOSE_CURLY_BRACKET], 'strict' => true, 'shared' => false, 'with' => []], T_FINALLY => ['start' => [T_OPEN_CURLY_BRACKET => T_OPEN_CURLY_BRACKET], 'end' => [T_CLOSE_CURLY_BRACKET => T_CLOSE_CURLY_BRACKET], 'strict' => true, 'shared' => false, 'with' => []], T_ELSE => ['start' => [T_OPEN_CURLY_BRACKET => T_OPEN_CURLY_BRACKET, T_COLON => T_COLON], 'end' => [T_CLOSE_CURLY_BRACKET => T_CLOSE_CURLY_BRACKET, T_ENDIF => T_ENDIF], 'strict' => false, 'shared' => false, 'with' => [T_IF => T_IF, T_ELSEIF => T_ELSEIF]], T_ELSEIF => ['start' => [T_OPEN_CURLY_BRACKET => T_OPEN_CURLY_BRACKET, T_COLON => T_COLON], 'end' => [T_CLOSE_CURLY_BRACKET => T_CLOSE_CURLY_BRACKET, T_ENDIF => T_ENDIF, T_ELSE => T_ELSE, T_ELSEIF => T_ELSEIF], 'strict' => false, 'shared' => false, 'with' => [T_IF => T_IF, T_ELSE => T_ELSE]], T_FOR => ['start' => [T_OPEN_CURLY_BRACKET => T_OPEN_CURLY_BRACKET, T_COLON => T_COLON], 'end' => [T_CLOSE_CURLY_BRACKET => T_CLOSE_CURLY_BRACKET, T_ENDFOR => T_ENDFOR], 'strict' => false, 'shared' => false, 'with' => []], T_FOREACH => ['start' => [T_OPEN_CURLY_BRACKET => T_OPEN_CURLY_BRACKET, T_COLON => T_COLON], 'end' => [T_CLOSE_CURLY_BRACKET => T_CLOSE_CURLY_BRACKET, T_ENDFOREACH => T_ENDFOREACH], 'strict' => false, 'shared' => false, 'with' => []], T_INTERFACE => ['start' => [T_OPEN_CURLY_BRACKET => T_OPEN_CURLY_BRACKET], 'end' => [T_CLOSE_CURLY_BRACKET => T_CLOSE_CURLY_BRACKET], 'strict' => true, 'shared' => false, 'with' => []], T_FUNCTION => ['start' => [T_OPEN_CURLY_BRACKET => T_OPEN_CURLY_BRACKET], 'end' => [T_CLOSE_CURLY_BRACKET => T_CLOSE_CURLY_BRACKET], 'strict' => true, 'shared' => false, 'with' => []], T_CLASS => ['start' => [T_OPEN_CURLY_BRACKET => T_OPEN_CURLY_BRACKET], 'end' => [T_CLOSE_CURLY_BRACKET => T_CLOSE_CURLY_BRACKET], 'strict' => true, 'shared' => false, 'with' => []], T_TRAIT => ['start' => [T_OPEN_CURLY_BRACKET => T_OPEN_CURLY_BRACKET], 'end' => [T_CLOSE_CURLY_BRACKET => T_CLOSE_CURLY_BRACKET], 'strict' => true, 'shared' => false, 'with' => []], T_ENUM => ['start' => [T_OPEN_CURLY_BRACKET => T_OPEN_CURLY_BRACKET], 'end' => [T_CLOSE_CURLY_BRACKET => T_CLOSE_CURLY_BRACKET], 'strict' => true, 'shared' => false, 'with' => []], T_USE => ['start' => [T_OPEN_CURLY_BRACKET => T_OPEN_CURLY_BRACKET], 'end' => [T_CLOSE_CURLY_BRACKET => T_CLOSE_CURLY_BRACKET], 'strict' => false, 'shared' => false, 'with' => []], T_DECLARE => ['start' => [T_OPEN_CURLY_BRACKET => T_OPEN_CURLY_BRACKET, T_COLON => T_COLON], 'end' => [T_CLOSE_CURLY_BRACKET => T_CLOSE_CURLY_BRACKET, T_ENDDECLARE => T_ENDDECLARE], 'strict' => false, 'shared' => false, 'with' => []], T_NAMESPACE => ['start' => [T_OPEN_CURLY_BRACKET => T_OPEN_CURLY_BRACKET], 'end' => [T_CLOSE_CURLY_BRACKET => T_CLOSE_CURLY_BRACKET], 'strict' => false, 'shared' => false, 'with' => []], T_WHILE => ['start' => [T_OPEN_CURLY_BRACKET => T_OPEN_CURLY_BRACKET, T_COLON => T_COLON], 'end' => [T_CLOSE_CURLY_BRACKET => T_CLOSE_CURLY_BRACKET, T_ENDWHILE => T_ENDWHILE], 'strict' => false, 'shared' => false, 'with' => []], T_DO => ['start' => [T_OPEN_CURLY_BRACKET => T_OPEN_CURLY_BRACKET], 'end' => [T_CLOSE_CURLY_BRACKET => T_CLOSE_CURLY_BRACKET], 'strict' => true, 'shared' => false, 'with' => []], T_SWITCH => ['start' => [T_OPEN_CURLY_BRACKET => T_OPEN_CURLY_BRACKET, T_COLON => T_COLON], 'end' => [T_CLOSE_CURLY_BRACKET => T_CLOSE_CURLY_BRACKET, T_ENDSWITCH => T_ENDSWITCH], 'strict' => true, 'shared' => false, 'with' => []], T_CASE => ['start' => [T_COLON => T_COLON, T_SEMICOLON => T_SEMICOLON], 'end' => [T_BREAK => T_BREAK, T_RETURN => T_RETURN, T_CONTINUE => T_CONTINUE, T_THROW => T_THROW, T_EXIT => T_EXIT], 'strict' => true, 'shared' => true, 'with' => [T_DEFAULT => T_DEFAULT, T_CASE => T_CASE, T_SWITCH => T_SWITCH]], T_DEFAULT => ['start' => [T_COLON => T_COLON, T_SEMICOLON => T_SEMICOLON], 'end' => [T_BREAK => T_BREAK, T_RETURN => T_RETURN, T_CONTINUE => T_CONTINUE, T_THROW => T_THROW, T_EXIT => T_EXIT], 'strict' => true, 'shared' => true, 'with' => [T_CASE => T_CASE, T_SWITCH => T_SWITCH]], T_MATCH => ['start' => [T_OPEN_CURLY_BRACKET => T_OPEN_CURLY_BRACKET], 'end' => [T_CLOSE_CURLY_BRACKET => T_CLOSE_CURLY_BRACKET], 'strict' => true, 'shared' => false, 'with' => []], T_START_HEREDOC => ['start' => [T_START_HEREDOC => T_START_HEREDOC], 'end' => [T_END_HEREDOC => T_END_HEREDOC], 'strict' => true, 'shared' => false, 'with' => []], T_START_NOWDOC => ['start' => [T_START_NOWDOC => T_START_NOWDOC], 'end' => [T_END_NOWDOC => T_END_NOWDOC], 'strict' => true, 'shared' => false, 'with' => []]];
    /**
     * A list of tokens that end the scope.
     *
     * This array is just a unique collection of the end tokens
     * from the scopeOpeners array. The data is duplicated here to
     * save time during parsing of the file.
     *
     * @var array
     */
    public $end_scope_tokens = [T_CLOSE_CURLY_BRACKET => T_CLOSE_CURLY_BRACKET, T_ENDIF => T_ENDIF, T_ENDFOR => T_ENDFOR, T_ENDFOREACH => T_ENDFOREACH, T_ENDWHILE => T_ENDWHILE, T_ENDSWITCH => T_ENDSWITCH, T_ENDDECLARE => T_ENDDECLARE, T_BREAK => T_BREAK, T_END_HEREDOC => T_END_HEREDOC, T_END_NOWDOC => T_END_NOWDOC];
    /**
     * Known lengths of tokens.
     *
     * @var array<int, int>
     */
    public $known_lengths = [T_ABSTRACT => 8, T_AND_EQUAL => 2, T_ARRAY => 5, T_AS => 2, T_BOOLEAN_AND => 2, T_BOOLEAN_OR => 2, T_BREAK => 5, T_CALLABLE => 8, T_CASE => 4, T_CATCH => 5, T_CLASS => 5, T_CLASS_C => 9, T_CLONE => 5, T_CONCAT_EQUAL => 2, T_CONST => 5, T_CONTINUE => 8, T_CURLY_OPEN => 2, T_DEC => 2, T_DECLARE => 7, T_DEFAULT => 7, T_DIR => 7, T_DIV_EQUAL => 2, T_DO => 2, T_DOLLAR_OPEN_CURLY_BRACES => 2, T_DOUBLE_ARROW => 2, T_DOUBLE_COLON => 2, T_ECHO => 4, T_ELLIPSIS => 3, T_ELSE => 4, T_ELSEIF => 6, T_EMPTY => 5, T_ENDDECLARE => 10, T_ENDFOR => 6, T_ENDFOREACH => 10, T_ENDIF => 5, T_ENDSWITCH => 9, T_ENDWHILE => 8, T_ENUM => 4, T_ENUM_CASE => 4, T_EVAL => 4, T_EXTENDS => 7, T_FILE => 8, T_FINAL => 5, T_FINALLY => 7, T_FN => 2, T_FOR => 3, T_FOREACH => 7, T_FUNCTION => 8, T_FUNC_C => 12, T_GLOBAL => 6, T_GOTO => 4, T_HALT_COMPILER => 15, T_IF => 2, T_IMPLEMENTS => 10, T_INC => 2, T_INCLUDE => 7, T_INCLUDE_ONCE => 12, T_INSTANCEOF => 10, T_INSTEADOF => 9, T_INTERFACE => 9, T_ISSET => 5, T_IS_EQUAL => 2, T_IS_GREATER_OR_EQUAL => 2, T_IS_IDENTICAL => 3, T_IS_NOT_EQUAL => 2, T_IS_NOT_IDENTICAL => 3, T_IS_SMALLER_OR_EQUAL => 2, T_LINE => 8, T_LIST => 4, T_LOGICAL_AND => 3, T_LOGICAL_OR => 2, T_LOGICAL_XOR => 3, T_MATCH => 5, T_MATCH_ARROW => 2, T_MATCH_DEFAULT => 7, T_METHOD_C => 10, T_MINUS_EQUAL => 2, T_POW_EQUAL => 3, T_MOD_EQUAL => 2, T_MUL_EQUAL => 2, T_NAMESPACE => 9, T_NS_C => 13, T_NS_SEPARATOR => 1, T_NEW => 3, T_NULLSAFE_OBJECT_OPERATOR => 3, T_OBJECT_OPERATOR => 2, T_OPEN_TAG_WITH_ECHO => 3, T_OR_EQUAL => 2, T_PLUS_EQUAL => 2, T_PRINT => 5, T_PRIVATE => 7, T_PUBLIC => 6, T_PROTECTED => 9, T_READONLY => 8, T_REQUIRE => 7, T_REQUIRE_ONCE => 12, T_RETURN => 6, T_STATIC => 6, T_SWITCH => 6, T_THROW => 5, T_TRAIT => 5, T_TRAIT_C => 9, T_TRY => 3, T_UNSET => 5, T_USE => 3, T_VAR => 3, T_WHILE => 5, T_XOR_EQUAL => 2, T_YIELD => 5, T_OPEN_CURLY_BRACKET => 1, T_CLOSE_CURLY_BRACKET => 1, T_OPEN_SQUARE_BRACKET => 1, T_CLOSE_SQUARE_BRACKET => 1, T_OPEN_PARENTHESIS => 1, T_CLOSE_PARENTHESIS => 1, T_COLON => 1, T_STRING_CONCAT => 1, T_INLINE_THEN => 1, T_INLINE_ELSE => 1, T_NULLABLE => 1, T_NULL => 4, T_FALSE => 5, T_TRUE => 4, T_SEMICOLON => 1, T_EQUAL => 1, T_MULTIPLY => 1, T_DIVIDE => 1, T_PLUS => 1, T_MINUS => 1, T_MODULUS => 1, T_POW => 2, T_SPACESHIP => 3, T_COALESCE => 2, T_COALESCE_EQUAL => 3, T_BITWISE_AND => 1, T_BITWISE_OR => 1, T_BITWISE_XOR => 1, T_SL => 2, T_SR => 2, T_SL_EQUAL => 3, T_SR_EQUAL => 3, T_GREATER_THAN => 1, T_LESS_THAN => 1, T_BOOLEAN_NOT => 1, T_SELF => 4, T_PARENT => 6, T_COMMA => 1, T_THIS => 4, T_CLOSURE => 8, T_BACKTICK => 1, T_OPEN_SHORT_ARRAY => 1, T_CLOSE_SHORT_ARRAY => 1, T_TYPE_UNION => 1, T_TYPE_INTERSECTION => 1];
    /**
     * Contexts in which keywords should always be tokenized as T_STRING.
     *
     * @var array
     */
    protected $tstring_contexts = [T_OBJECT_OPERATOR => true, T_NULLSAFE_OBJECT_OPERATOR => true, T_FUNCTION => true, T_CLASS => true, T_INTERFACE => true, T_TRAIT => true, T_ENUM => true, T_ENUM_CASE => true, T_EXTENDS => true, T_IMPLEMENTS => true, T_ATTRIBUTE => true, T_NEW => true, T_CONST => true, T_NS_SEPARATOR => true, T_USE => true, T_NAMESPACE => true, T_PAAMAYIM_NEKUDOTAYIM => true];
    /**
     * A cache of different token types, resolved into arrays.
     *
     * @var array
     * @see standardiseToken()
     */
    private static $resolve_token_cache = [];
    /**
     * Creates an array of tokens when given some PHP code.
     *
     * Starts by using token_get_all() but does a lot of extra processing
     * to insert information about the context of the token.
     *
     * @param string $string The string to tokenize.
     *
     * @return array
     */
    protected function tokenize($string)
    {
        if (PHP_CODESNIFFER_VERBOSITY > 1) {
            echo "\t*** START PHP TOKENIZING ***" . PHP_EOL;
            $is_win = false;
            if (stripos(PHP_OS, 'WIN') === 0) {
                $is_win = true;
            }
        }
        $tokens = @token_get_all($string);
        $final_tokens = [];
        $new_stack_ptr = 0;
        $num_tokens = count($tokens);
        $last_not_empty_token = 0;
        $inside_inline_if = [];
        $inside_use_group = false;
        $comment_tokenizer = new Comment();
        for ($stack_ptr = 0; $stack_ptr < $num_tokens; $stack_ptr++) {
            // Special case for tokens we have needed to blank out.
            if ($tokens[$stack_ptr] === null) {
                continue;
            }
            $token = (array) $tokens[$stack_ptr];
            $token_is_array = isset($token[1]);
            if (PHP_CODESNIFFER_VERBOSITY > 1) {
                if ($token_is_array === true) {
                    $type = Util\Tokens::token_name($token[0]);
                    $content = Util\Common::prepare_for_output($token[1]);
                } else {
                    $new_token = self::resolve_simple_token($token[0]);
                    $type = $new_token['type'];
                    $content = Util\Common::prepare_for_output($token[0]);
                }
                echo "\tProcess token ";
                if ($token_is_array === true) {
                    echo "[{$stack_ptr}]";
                } else {
                    echo " {$stack_ptr} ";
                }
                echo ": {$type} => {$content}";
            }
            //end if
            if ($new_stack_ptr > 0 && isset(Util\Tokens::$empty_tokens[$final_tokens[$new_stack_ptr - 1]['code']]) === false) {
                $last_not_empty_token = $new_stack_ptr - 1;
            }
            /*
                If we are using \r\n newline characters, the \r and \n are sometimes
                split over two tokens. This normally occurs after comments. We need
                to merge these two characters together so that our line endings are
                consistent for all lines.
            */
            if ($token_is_array === true && substr($token[1], -1) === "\r") {
                if (isset($tokens[$stack_ptr + 1]) === true && is_array($tokens[$stack_ptr + 1]) === true && $tokens[$stack_ptr + 1][1][0] === "\n") {
                    $token[1] .= "\n";
                    if (PHP_CODESNIFFER_VERBOSITY > 1) {
                        if ($is_win === true) {
                            echo '\n';
                        } else {
                            echo "\x1b[30;1m\\n\x1b[0m";
                        }
                    }
                    if ($tokens[$stack_ptr + 1][1] === "\n") {
                        // This token's content has been merged into the previous,
                        // so we can skip it.
                        $tokens[$stack_ptr + 1] = '';
                    } else {
                        $tokens[$stack_ptr + 1][1] = substr($tokens[$stack_ptr + 1][1], 1);
                    }
                }
            }
            //end if
            if (PHP_CODESNIFFER_VERBOSITY > 1) {
                echo PHP_EOL;
            }
            /*
                Tokenize context sensitive keyword as string when it should be string.
            */
            if ($token_is_array === true && isset(Util\Tokens::$context_sensitive_keywords[$token[0]]) === true && (isset($this->tstring_contexts[$final_tokens[$last_not_empty_token]['code']]) === true || $final_tokens[$last_not_empty_token]['content'] === '&')) {
                if (isset($this->tstring_contexts[$final_tokens[$last_not_empty_token]['code']]) === true) {
                    $preserve_keyword = false;
                    // `new class`, and `new static` should be preserved.
                    if ($final_tokens[$last_not_empty_token]['code'] === T_NEW && ($token[0] === T_CLASS || $token[0] === T_STATIC)) {
                        $preserve_keyword = true;
                    }
                    // `new class extends` `new class implements` should be preserved
                    if (($token[0] === T_EXTENDS || $token[0] === T_IMPLEMENTS) && $final_tokens[$last_not_empty_token]['code'] === T_CLASS) {
                        $preserve_keyword = true;
                    }
                    // `namespace\` should be preserved
                    if ($token[0] === T_NAMESPACE) {
                        for ($i = $stack_ptr + 1; $i < $num_tokens; $i++) {
                            if (is_array($tokens[$i]) === false) {
                                break;
                            }
                            if (isset(Util\Tokens::$empty_tokens[$tokens[$i][0]]) === true) {
                                continue;
                            }
                            if ($tokens[$i][0] === T_NS_SEPARATOR) {
                                $preserve_keyword = true;
                            }
                            break;
                        }
                    }
                }
                //end if
                if ($final_tokens[$last_not_empty_token]['content'] === '&') {
                    $preserve_keyword = true;
                    for ($i = $last_not_empty_token - 1; $i >= 0; $i--) {
                        if (isset(Util\Tokens::$empty_tokens[$final_tokens[$i]['code']]) === true) {
                            continue;
                        }
                        if ($final_tokens[$i]['code'] === T_FUNCTION) {
                            $preserve_keyword = false;
                        }
                        break;
                    }
                }
                if ($preserve_keyword === false) {
                    if (PHP_CODESNIFFER_VERBOSITY > 1) {
                        $type = Util\Tokens::token_name($token[0]);
                        echo "\t\t* token {$stack_ptr} changed from {$type} to T_STRING" . PHP_EOL;
                    }
                    $final_tokens[$new_stack_ptr] = ['code' => T_STRING, 'type' => 'T_STRING', 'content' => $token[1]];
                    $new_stack_ptr++;
                    continue;
                }
            }
            //end if
            /*
                Special case for `static` used as a function name, i.e. `static()`.
            */
            if ($token_is_array === true && $token[0] === T_STATIC && $final_tokens[$last_not_empty_token]['code'] !== T_NEW) {
                for ($i = $stack_ptr + 1; $i < $num_tokens; $i++) {
                    if (is_array($tokens[$i]) === true && isset(Util\Tokens::$empty_tokens[$tokens[$i][0]]) === true) {
                        continue;
                    }
                    if ($tokens[$i][0] === '(') {
                        $final_tokens[$new_stack_ptr] = ['code' => T_STRING, 'type' => 'T_STRING', 'content' => $token[1]];
                        $new_stack_ptr++;
                        continue 2;
                    }
                    break;
                }
            }
            //end if
            /*
                Parse doc blocks into something that can be easily iterated over.
            */
            if ($token_is_array === true && ($token[0] === T_DOC_COMMENT || $token[0] === T_COMMENT && strpos($token[1], '/**') === 0)) {
                $comment_tokens = $comment_tokenizer->tokenize_string($token[1], $this->eol_char, $new_stack_ptr);
                foreach ($comment_tokens as $comment_token) {
                    $final_tokens[$new_stack_ptr] = $comment_token;
                    $new_stack_ptr++;
                }
                continue;
            }
            /*
                PHP 8 tokenizes a new line after a slash and hash comment to the next whitespace token.
            */
            if (PHP_VERSION_ID >= 80000 && $token_is_array === true && ($token[0] === T_COMMENT && (strpos($token[1], '//') === 0 || strpos($token[1], '#') === 0)) && isset($tokens[$stack_ptr + 1]) === true && is_array($tokens[$stack_ptr + 1]) === true && $tokens[$stack_ptr + 1][0] === T_WHITESPACE) {
                $next_token = $tokens[$stack_ptr + 1];
                // If the next token is a single new line, merge it into the comment token
                // and set to it up to be skipped.
                if ($next_token[1] === "\n" || $next_token[1] === "\r\n" || $next_token[1] === "\n\r") {
                    $token[1] .= $next_token[1];
                    $tokens[$stack_ptr + 1] = null;
                    if (PHP_CODESNIFFER_VERBOSITY > 1) {
                        echo "\t\t* merged newline after comment into comment token {$stack_ptr}" . PHP_EOL;
                    }
                } else {
                    // This may be a whitespace token consisting of multiple new lines.
                    if (strpos($next_token[1], "\r\n") === 0) {
                        $token[1] .= "\r\n";
                        $tokens[$stack_ptr + 1][1] = substr($next_token[1], 2);
                    } elseif (strpos($next_token[1], "\n\r") === 0) {
                        $token[1] .= "\n\r";
                        $tokens[$stack_ptr + 1][1] = substr($next_token[1], 2);
                    } elseif (strpos($next_token[1], "\n") === 0) {
                        $token[1] .= "\n";
                        $tokens[$stack_ptr + 1][1] = substr($next_token[1], 1);
                    }
                    if (PHP_CODESNIFFER_VERBOSITY > 1) {
                        echo "\t\t* stripped first newline after comment and added it to comment token {$stack_ptr}" . PHP_EOL;
                    }
                }
                //end if
            }
            //end if
            /*
                For Explicit Octal Notation prior to PHP 8.1 we need to combine the
                T_LNUMBER and T_STRING token values into a single token value, and
                then ignore the T_STRING token.
            */
            if (PHP_VERSION_ID < 80100 && $token_is_array === true && $token[1] === '0' && (isset($tokens[$stack_ptr + 1]) === true && is_array($tokens[$stack_ptr + 1]) === true && $tokens[$stack_ptr + 1][0] === T_STRING && isset($tokens[$stack_ptr + 1][1][0], $tokens[$stack_ptr + 1][1][1]) === true && strtolower($tokens[$stack_ptr + 1][1][0]) === 'o' && $tokens[$stack_ptr + 1][1][1] !== '_') && preg_match('`^(o[0-7]+(?:_[0-7]+)?)([0-9_]*)$`i', $tokens[$stack_ptr + 1][1], $matches) === 1) {
                $final_tokens[$new_stack_ptr] = ['code' => T_LNUMBER, 'type' => 'T_LNUMBER', 'content' => $token[1] .= $matches[1]];
                $new_stack_ptr++;
                if (isset($matches[2]) === true && $matches[2] !== '') {
                    $type = 'T_LNUMBER';
                    if ($matches[2][0] === '_') {
                        $type = 'T_STRING';
                    }
                    $final_tokens[$new_stack_ptr] = ['code' => constant($type), 'type' => $type, 'content' => $matches[2]];
                    $new_stack_ptr++;
                }
                $stack_ptr++;
                continue;
            }
            //end if
            /*
                PHP 8.1 introduced two dedicated tokens for the & character.
                Retokenizing both of these to T_BITWISE_AND, which is the
                token PHPCS already tokenized them as.
            */
            if ($token_is_array === true && ($token[0] === T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG || $token[0] === T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG)) {
                $final_tokens[$new_stack_ptr] = ['code' => T_BITWISE_AND, 'type' => 'T_BITWISE_AND', 'content' => $token[1]];
                $new_stack_ptr++;
                continue;
            }
            /*
                If this is a double quoted string, PHP will tokenize the whole
                thing which causes problems with the scope map when braces are
                within the string. So we need to merge the tokens together to
                provide a single string.
            */
            if ($token_is_array === false && ($token[0] === '"' || $token[0] === 'b"')) {
                // Binary casts need a special token.
                if ($token[0] === 'b"') {
                    $final_tokens[$new_stack_ptr] = ['code' => T_BINARY_CAST, 'type' => 'T_BINARY_CAST', 'content' => 'b'];
                    $new_stack_ptr++;
                }
                $token_content = '"';
                $nested_vars = [];
                for ($i = $stack_ptr + 1; $i < $num_tokens; $i++) {
                    $sub_token = (array) $tokens[$i];
                    $sub_token_is_array = isset($sub_token[1]);
                    if ($sub_token_is_array === true) {
                        $token_content .= $sub_token[1];
                        if (($sub_token[1] === '{' || $sub_token[1] === '${') && $sub_token[0] !== T_ENCAPSED_AND_WHITESPACE) {
                            $nested_vars[] = $i;
                        }
                    } else {
                        $token_content .= $sub_token[0];
                        if ($sub_token[0] === '}') {
                            array_pop($nested_vars);
                        }
                    }
                    if ($sub_token_is_array === false && $sub_token[0] === '"' && empty($nested_vars) === true) {
                        // We found the other end of the double quoted string.
                        break;
                    }
                }
                //end for
                $stack_ptr = $i;
                // Convert each line within the double quoted string to a
                // new token, so it conforms with other multiple line tokens.
                $token_lines = explode($this->eol_char, $token_content);
                $num_lines = count($token_lines);
                $new_token = [];
                for ($j = 0; $j < $num_lines; $j++) {
                    $new_token['content'] = $token_lines[$j];
                    if ($j === $num_lines - 1) {
                        if ($token_lines[$j] === '') {
                            break;
                        }
                    } else {
                        $new_token['content'] .= $this->eol_char;
                    }
                    $new_token['code'] = T_DOUBLE_QUOTED_STRING;
                    $new_token['type'] = 'T_DOUBLE_QUOTED_STRING';
                    $final_tokens[$new_stack_ptr] = $new_token;
                    $new_stack_ptr++;
                }
                // Continue, as we're done with this token.
                continue;
            }
            //end if
            /*
                Detect binary casting and assign the casts their own token.
            */
            if ($token_is_array === true && $token[0] === T_CONSTANT_ENCAPSED_STRING && (substr($token[1], 0, 2) === 'b"' || substr($token[1], 0, 2) === "b'")) {
                $final_tokens[$new_stack_ptr] = ['code' => T_BINARY_CAST, 'type' => 'T_BINARY_CAST', 'content' => 'b'];
                $new_stack_ptr++;
                $token[1] = substr($token[1], 1);
            }
            if ($token_is_array === true && $token[0] === T_STRING_CAST && preg_match('`^\(\s*binary\s*\)$`i', $token[1]) === 1) {
                $final_tokens[$new_stack_ptr] = ['code' => T_BINARY_CAST, 'type' => 'T_BINARY_CAST', 'content' => $token[1]];
                $new_stack_ptr++;
                continue;
            }
            /*
                If this is a heredoc, PHP will tokenize the whole
                thing which causes problems when heredocs don't
                contain real PHP code, which is almost never.
                We want to leave the start and end heredoc tokens
                alone though.
            */
            if ($token_is_array === true && $token[0] === T_START_HEREDOC) {
                // Add the start heredoc token to the final array.
                $final_tokens[$new_stack_ptr] = self::standardise_token($token);
                // Check if this is actually a nowdoc and use a different token
                // to help the sniffs.
                $nowdoc = false;
                if (strpos($token[1], "'") !== false) {
                    $final_tokens[$new_stack_ptr]['code'] = T_START_NOWDOC;
                    $final_tokens[$new_stack_ptr]['type'] = 'T_START_NOWDOC';
                    $nowdoc = true;
                }
                $token_content = '';
                for ($i = $stack_ptr + 1; $i < $num_tokens; $i++) {
                    $sub_token_is_array = is_array($tokens[$i]);
                    if ($sub_token_is_array === true && $tokens[$i][0] === T_END_HEREDOC) {
                        // We found the other end of the heredoc.
                        break;
                    }
                    if ($sub_token_is_array === true) {
                        $token_content .= $tokens[$i][1];
                    } else {
                        $token_content .= $tokens[$i];
                    }
                }
                if ($i === $num_tokens) {
                    // We got to the end of the file and never
                    // found the closing token, so this probably wasn't
                    // a heredoc.
                    if (PHP_CODESNIFFER_VERBOSITY > 1) {
                        $type = $final_tokens[$new_stack_ptr]['type'];
                        echo "\t\t* failed to find the end of the here/nowdoc" . PHP_EOL;
                        echo "\t\t* token {$stack_ptr} changed from {$type} to T_STRING" . PHP_EOL;
                    }
                    $final_tokens[$new_stack_ptr]['code'] = T_STRING;
                    $final_tokens[$new_stack_ptr]['type'] = 'T_STRING';
                    $new_stack_ptr++;
                    continue;
                }
                $stack_ptr = $i;
                $new_stack_ptr++;
                // Convert each line within the heredoc to a
                // new token, so it conforms with other multiple line tokens.
                $token_lines = explode($this->eol_char, $token_content);
                $num_lines = count($token_lines);
                $new_token = [];
                for ($j = 0; $j < $num_lines; $j++) {
                    $new_token['content'] = $token_lines[$j];
                    if ($j === $num_lines - 1) {
                        if ($token_lines[$j] === '') {
                            break;
                        }
                    } else {
                        $new_token['content'] .= $this->eol_char;
                    }
                    if ($nowdoc === true) {
                        $new_token['code'] = T_NOWDOC;
                        $new_token['type'] = 'T_NOWDOC';
                    } else {
                        $new_token['code'] = T_HEREDOC;
                        $new_token['type'] = 'T_HEREDOC';
                    }
                    $final_tokens[$new_stack_ptr] = $new_token;
                    $new_stack_ptr++;
                }
                //end for
                // Add the end heredoc token to the final array.
                $final_tokens[$new_stack_ptr] = self::standardise_token($tokens[$stack_ptr]);
                if ($nowdoc === true) {
                    $final_tokens[$new_stack_ptr]['code'] = T_END_NOWDOC;
                    $final_tokens[$new_stack_ptr]['type'] = 'T_END_NOWDOC';
                }
                $new_stack_ptr++;
                // Continue, as we're done with this token.
                continue;
            }
            //end if
            /*
                Enum keyword for PHP < 8.1
            */
            if ($token_is_array === true && $token[0] === T_STRING && strtolower($token[1]) === 'enum') {
                // Get the next non-empty token.
                for ($i = $stack_ptr + 1; $i < $num_tokens; $i++) {
                    if (is_array($tokens[$i]) === false || isset(Util\Tokens::$empty_tokens[$tokens[$i][0]]) === false) {
                        break;
                    }
                }
                if (isset($tokens[$i]) === true && is_array($tokens[$i]) === true && $tokens[$i][0] === T_STRING) {
                    // Modify $tokens directly so we can use it later when converting enum "case".
                    $tokens[$stack_ptr][0] = T_ENUM;
                    $new_token = [];
                    $new_token['code'] = T_ENUM;
                    $new_token['type'] = 'T_ENUM';
                    $new_token['content'] = $token[1];
                    $final_tokens[$new_stack_ptr] = $new_token;
                    if (PHP_CODESNIFFER_VERBOSITY > 1) {
                        echo "\t\t* token {$stack_ptr} changed from T_STRING to T_ENUM" . PHP_EOL;
                    }
                    $new_stack_ptr++;
                    continue;
                }
            }
            //end if
            /*
                Convert enum "case" to T_ENUM_CASE
            */
            if ($token_is_array === true && $token[0] === T_CASE && isset($this->tstring_contexts[$final_tokens[$last_not_empty_token]['code']]) === false) {
                $is_enum_case = false;
                $scope = 1;
                for ($i = $stack_ptr - 1; $i > 0; $i--) {
                    if ($tokens[$i] === '}') {
                        $scope++;
                        continue;
                    }
                    if ($tokens[$i] === '{') {
                        $scope--;
                        continue;
                    }
                    if (is_array($tokens[$i]) === false) {
                        continue;
                    }
                    if ($scope !== 0) {
                        continue;
                    }
                    if ($tokens[$i][0] === T_SWITCH) {
                        break;
                    }
                    if ($tokens[$i][0] === T_ENUM || $tokens[$i][0] === T_ENUM_CASE) {
                        $is_enum_case = true;
                        break;
                    }
                }
                //end for
                if ($is_enum_case === true) {
                    // Modify $tokens directly so we can use it as optimisation for other enum "case".
                    $tokens[$stack_ptr][0] = T_ENUM_CASE;
                    $new_token = [];
                    $new_token['code'] = T_ENUM_CASE;
                    $new_token['type'] = 'T_ENUM_CASE';
                    $new_token['content'] = $token[1];
                    $final_tokens[$new_stack_ptr] = $new_token;
                    if (PHP_CODESNIFFER_VERBOSITY > 1) {
                        echo "\t\t* token {$stack_ptr} changed from T_CASE to T_ENUM_CASE" . PHP_EOL;
                    }
                    $new_stack_ptr++;
                    continue;
                }
            }
            //end if
            /*
                As of PHP 8.0 fully qualified, partially qualified and namespace relative
                identifier names are tokenized differently.
                This "undoes" the new tokenization so the tokenization will be the same in
                in PHP 5, 7 and 8.
            */
            if (PHP_VERSION_ID >= 80000 && $token_is_array === true && ($token[0] === T_NAME_QUALIFIED || $token[0] === T_NAME_FULLY_QUALIFIED || $token[0] === T_NAME_RELATIVE)) {
                $name = $token[1];
                if ($token[0] === T_NAME_FULLY_QUALIFIED) {
                    $new_token = [];
                    $new_token['code'] = T_NS_SEPARATOR;
                    $new_token['type'] = 'T_NS_SEPARATOR';
                    $new_token['content'] = '\\';
                    $final_tokens[$new_stack_ptr] = $new_token;
                    ++$new_stack_ptr;
                    $name = ltrim($name, '\\');
                }
                if ($token[0] === T_NAME_RELATIVE) {
                    $new_token = [];
                    $new_token['code'] = T_NAMESPACE;
                    $new_token['type'] = 'T_NAMESPACE';
                    $new_token['content'] = substr($name, 0, 9);
                    $final_tokens[$new_stack_ptr] = $new_token;
                    ++$new_stack_ptr;
                    $new_token = [];
                    $new_token['code'] = T_NS_SEPARATOR;
                    $new_token['type'] = 'T_NS_SEPARATOR';
                    $new_token['content'] = '\\';
                    $final_tokens[$new_stack_ptr] = $new_token;
                    ++$new_stack_ptr;
                    $name = substr($name, 10);
                }
                $parts = explode('\\', $name);
                $part_count = count($parts);
                $last_part = $part_count - 1;
                foreach ($parts as $i => $part) {
                    $new_token = [];
                    $new_token['code'] = T_STRING;
                    $new_token['type'] = 'T_STRING';
                    $new_token['content'] = $part;
                    $final_tokens[$new_stack_ptr] = $new_token;
                    ++$new_stack_ptr;
                    if ($i !== $last_part) {
                        $new_token = [];
                        $new_token['code'] = T_NS_SEPARATOR;
                        $new_token['type'] = 'T_NS_SEPARATOR';
                        $new_token['content'] = '\\';
                        $final_tokens[$new_stack_ptr] = $new_token;
                        ++$new_stack_ptr;
                    }
                }
                if (PHP_CODESNIFFER_VERBOSITY > 1) {
                    $type = Util\Tokens::token_name($token[0]);
                    $content = Util\Common::prepare_for_output($token[1]);
                    echo "\t\t* token {$stack_ptr} split into individual tokens; was: {$type} => {$content}" . PHP_EOL;
                }
                continue;
            }
            //end if
            /*
                PHP 8.0 Attributes
            */
            if (PHP_VERSION_ID < 80000 && $token[0] === T_COMMENT && strpos($token[1], '#[') === 0) {
                $sub_tokens = $this->parse_php_attribute($tokens, $stack_ptr);
                if ($sub_tokens !== null) {
                    array_splice($tokens, $stack_ptr, 1, $sub_tokens);
                    $num_tokens = count($tokens);
                    $token_is_array = true;
                    $token = $tokens[$stack_ptr];
                } else {
                    $token[0] = T_ATTRIBUTE;
                }
            }
            if ($token_is_array === true && $token[0] === T_ATTRIBUTE) {
                // Go looking for the close bracket.
                $bracket_closer = $this->find_closer($tokens, $stack_ptr + 1, ['[', '#['], ']');
                $new_token = [];
                $new_token['code'] = T_ATTRIBUTE;
                $new_token['type'] = 'T_ATTRIBUTE';
                $new_token['content'] = '#[';
                $final_tokens[$new_stack_ptr] = $new_token;
                $tokens[$bracket_closer] = [];
                $tokens[$bracket_closer][0] = T_ATTRIBUTE_END;
                $tokens[$bracket_closer][1] = ']';
                if (PHP_CODESNIFFER_VERBOSITY > 1) {
                    echo "\t\t* token {$bracket_closer} changed from T_CLOSE_SQUARE_BRACKET to T_ATTRIBUTE_END" . PHP_EOL;
                }
                $new_stack_ptr++;
                continue;
            }
            //end if
            /*
                Tokenize the parameter labels for PHP 8.0 named parameters as a special T_PARAM_NAME
                token and ensures that the colon after it is always T_COLON.
            */
            if ($token_is_array === true && ($token[0] === T_STRING || preg_match('`^[a-zA-Z_\x80-\xff]`', $token[1]) === 1)) {
                // Get the next non-empty token.
                for ($i = $stack_ptr + 1; $i < $num_tokens; $i++) {
                    if (is_array($tokens[$i]) === false || isset(Util\Tokens::$empty_tokens[$tokens[$i][0]]) === false) {
                        break;
                    }
                }
                if (isset($tokens[$i]) === true && is_array($tokens[$i]) === false && $tokens[$i] === ':') {
                    // Get the previous non-empty token.
                    for ($j = $stack_ptr - 1; $j > 0; $j--) {
                        if (is_array($tokens[$j]) === false || isset(Util\Tokens::$empty_tokens[$tokens[$j][0]]) === false) {
                            break;
                        }
                    }
                    if (is_array($tokens[$j]) === false && ($tokens[$j] === '(' || $tokens[$j] === ',')) {
                        $new_token = [];
                        $new_token['code'] = T_PARAM_NAME;
                        $new_token['type'] = 'T_PARAM_NAME';
                        $new_token['content'] = $token[1];
                        $final_tokens[$new_stack_ptr] = $new_token;
                        $new_stack_ptr++;
                        // Modify the original token stack so that future checks, like
                        // determining T_COLON vs T_INLINE_ELSE can handle this correctly.
                        $tokens[$stack_ptr][0] = T_PARAM_NAME;
                        if (PHP_CODESNIFFER_VERBOSITY > 1) {
                            $type = Util\Tokens::token_name($token[0]);
                            echo "\t\t* token {$stack_ptr} changed from {$type} to T_PARAM_NAME" . PHP_EOL;
                        }
                        continue;
                    }
                }
                //end if
            }
            //end if
            /*
                "readonly" keyword for PHP < 8.1
            */
            if (PHP_VERSION_ID < 80100 && $token_is_array === true && strtolower($token[1]) === 'readonly' && isset($this->tstring_contexts[$final_tokens[$last_not_empty_token]['code']]) === false) {
                // Get the next non-whitespace token.
                for ($i = $stack_ptr + 1; $i < $num_tokens; $i++) {
                    if (is_array($tokens[$i]) === false || $tokens[$i][0] !== T_WHITESPACE) {
                        break;
                    }
                }
                if (isset($tokens[$i]) === false || $tokens[$i] !== '(') {
                    $final_tokens[$new_stack_ptr] = ['code' => T_READONLY, 'type' => 'T_READONLY', 'content' => $token[1]];
                    $new_stack_ptr++;
                    continue;
                }
            }
            //end if
            /*
                Before PHP 7.0, the "yield from" was tokenized as
                T_YIELD, T_WHITESPACE and T_STRING. So look for
                and change this token in earlier versions.
            */
            if (PHP_VERSION_ID < 70000 && PHP_VERSION_ID >= 50500 && $token_is_array === true && $token[0] === T_YIELD && isset($tokens[$stack_ptr + 1]) === true && isset($tokens[$stack_ptr + 2]) === true && $tokens[$stack_ptr + 1][0] === T_WHITESPACE && $tokens[$stack_ptr + 2][0] === T_STRING && strtolower($tokens[$stack_ptr + 2][1]) === 'from') {
                // Could be multi-line, so adjust the token stack.
                $token[0] = T_YIELD_FROM;
                $token[1] .= $tokens[$stack_ptr + 1][1] . $tokens[$stack_ptr + 2][1];
                if (PHP_CODESNIFFER_VERBOSITY > 1) {
                    for ($i = $stack_ptr + 1; $i <= $stack_ptr + 2; $i++) {
                        $type = Util\Tokens::token_name($tokens[$i][0]);
                        $content = Util\Common::prepare_for_output($tokens[$i][1]);
                        echo "\t\t* token {$i} merged into T_YIELD_FROM; was: {$type} => {$content}" . PHP_EOL;
                    }
                }
                $tokens[$stack_ptr + 1] = null;
                $tokens[$stack_ptr + 2] = null;
            }
            /*
                Before PHP 5.5, the yield keyword was tokenized as
                T_STRING. So look for and change this token in
                earlier versions.
                Checks also if it is just "yield" or "yield from".
            */
            if (PHP_VERSION_ID < 50500 && $token_is_array === true && $token[0] === T_STRING && strtolower($token[1]) === 'yield' && isset($this->tstring_contexts[$final_tokens[$last_not_empty_token]['code']]) === false) {
                if (isset($tokens[$stack_ptr + 1]) === true && isset($tokens[$stack_ptr + 2]) === true && $tokens[$stack_ptr + 1][0] === T_WHITESPACE && $tokens[$stack_ptr + 2][0] === T_STRING && strtolower($tokens[$stack_ptr + 2][1]) === 'from') {
                    // Could be multi-line, so just just the token stack.
                    $token[0] = T_YIELD_FROM;
                    $token[1] .= $tokens[$stack_ptr + 1][1] . $tokens[$stack_ptr + 2][1];
                    if (PHP_CODESNIFFER_VERBOSITY > 1) {
                        for ($i = $stack_ptr + 1; $i <= $stack_ptr + 2; $i++) {
                            $type = Util\Tokens::token_name($tokens[$i][0]);
                            $content = Util\Common::prepare_for_output($tokens[$i][1]);
                            echo "\t\t* token {$i} merged into T_YIELD_FROM; was: {$type} => {$content}" . PHP_EOL;
                        }
                    }
                    $tokens[$stack_ptr + 1] = null;
                    $tokens[$stack_ptr + 2] = null;
                } else {
                    $new_token = [];
                    $new_token['code'] = T_YIELD;
                    $new_token['type'] = 'T_YIELD';
                    $new_token['content'] = $token[1];
                    $final_tokens[$new_stack_ptr] = $new_token;
                    $new_stack_ptr++;
                    continue;
                }
                //end if
            }
            //end if
            /*
                Before PHP 5.6, the ... operator was tokenized as three
                T_STRING_CONCAT tokens in a row. So look for and combine
                these tokens in earlier versions.
            */
            if ($token_is_array === false && $token[0] === '.' && isset($tokens[$stack_ptr + 1]) === true && isset($tokens[$stack_ptr + 2]) === true && $tokens[$stack_ptr + 1] === '.' && $tokens[$stack_ptr + 2] === '.') {
                $new_token = [];
                $new_token['code'] = T_ELLIPSIS;
                $new_token['type'] = 'T_ELLIPSIS';
                $new_token['content'] = '...';
                $final_tokens[$new_stack_ptr] = $new_token;
                $new_stack_ptr++;
                $stack_ptr += 2;
                continue;
            }
            /*
                Before PHP 5.6, the ** operator was tokenized as two
                T_MULTIPLY tokens in a row. So look for and combine
                these tokens in earlier versions.
            */
            if ($token_is_array === false && $token[0] === '*' && isset($tokens[$stack_ptr + 1]) === true && $tokens[$stack_ptr + 1] === '*') {
                $new_token = [];
                $new_token['code'] = T_POW;
                $new_token['type'] = 'T_POW';
                $new_token['content'] = '**';
                $final_tokens[$new_stack_ptr] = $new_token;
                $new_stack_ptr++;
                $stack_ptr++;
                continue;
            }
            /*
                Before PHP 5.6, the **= operator was tokenized as
                T_MULTIPLY followed by T_MUL_EQUAL. So look for and combine
                these tokens in earlier versions.
            */
            if ($token_is_array === false && $token[0] === '*' && isset($tokens[$stack_ptr + 1]) === true && is_array($tokens[$stack_ptr + 1]) === true && $tokens[$stack_ptr + 1][1] === '*=') {
                $new_token = [];
                $new_token['code'] = T_POW_EQUAL;
                $new_token['type'] = 'T_POW_EQUAL';
                $new_token['content'] = '**=';
                $final_tokens[$new_stack_ptr] = $new_token;
                $new_stack_ptr++;
                $stack_ptr++;
                continue;
            }
            /*
                Before PHP 7, the ??= operator was tokenized as
                T_INLINE_THEN, T_INLINE_THEN, T_EQUAL.
                Between PHP 7.0 and 7.3, the ??= operator was tokenized as
                T_COALESCE, T_EQUAL.
                So look for and combine these tokens in earlier versions.
            */
            if ($token_is_array === false && $token[0] === '?' && isset($tokens[$stack_ptr + 1]) === true && $tokens[$stack_ptr + 1][0] === '?' && isset($tokens[$stack_ptr + 2]) === true && $tokens[$stack_ptr + 2][0] === '=' || $token_is_array === true && $token[0] === T_COALESCE && isset($tokens[$stack_ptr + 1]) === true && $tokens[$stack_ptr + 1][0] === '=') {
                $new_token = [];
                $new_token['code'] = T_COALESCE_EQUAL;
                $new_token['type'] = 'T_COALESCE_EQUAL';
                $new_token['content'] = '??=';
                $final_tokens[$new_stack_ptr] = $new_token;
                $new_stack_ptr++;
                $stack_ptr++;
                if ($token_is_array === false) {
                    // Pre PHP 7.
                    $stack_ptr++;
                }
                continue;
            }
            /*
                Before PHP 7, the ?? operator was tokenized as
                T_INLINE_THEN followed by T_INLINE_THEN.
                So look for and combine these tokens in earlier versions.
            */
            if ($token_is_array === false && $token[0] === '?' && isset($tokens[$stack_ptr + 1]) === true && $tokens[$stack_ptr + 1][0] === '?') {
                $new_token = [];
                $new_token['code'] = T_COALESCE;
                $new_token['type'] = 'T_COALESCE';
                $new_token['content'] = '??';
                $final_tokens[$new_stack_ptr] = $new_token;
                $new_stack_ptr++;
                $stack_ptr++;
                continue;
            }
            /*
                Before PHP 8, the ?-> operator was tokenized as
                T_INLINE_THEN followed by T_OBJECT_OPERATOR.
                So look for and combine these tokens in earlier versions.
            */
            if ($token_is_array === false && $token[0] === '?' && isset($tokens[$stack_ptr + 1]) === true && is_array($tokens[$stack_ptr + 1]) === true && $tokens[$stack_ptr + 1][0] === T_OBJECT_OPERATOR) {
                $new_token = [];
                $new_token['code'] = T_NULLSAFE_OBJECT_OPERATOR;
                $new_token['type'] = 'T_NULLSAFE_OBJECT_OPERATOR';
                $new_token['content'] = '?->';
                $final_tokens[$new_stack_ptr] = $new_token;
                $new_stack_ptr++;
                $stack_ptr++;
                continue;
            }
            /*
                Before PHP 7.4, underscores inside T_LNUMBER and T_DNUMBER
                tokens split the token with a T_STRING. So look for
                and change these tokens in earlier versions.
            */
            if (PHP_VERSION_ID < 70400 && ($token_is_array === true && ($token[0] === T_LNUMBER || $token[0] === T_DNUMBER) && isset($tokens[$stack_ptr + 1]) === true && is_array($tokens[$stack_ptr + 1]) === true && $tokens[$stack_ptr + 1][0] === T_STRING && $tokens[$stack_ptr + 1][1][0] === '_')) {
                $new_content = $token[1];
                $new_type = $token[0];
                for ($i = $stack_ptr + 1; $i < $num_tokens; $i++) {
                    if (is_array($tokens[$i]) === false) {
                        break;
                    }
                    if ($tokens[$i][0] === T_LNUMBER || $tokens[$i][0] === T_DNUMBER) {
                        $new_content .= $tokens[$i][1];
                        continue;
                    }
                    if ($tokens[$i][0] === T_STRING && $tokens[$i][1][0] === '_' && (strpos($new_content, '0x') === 0 && preg_match('`^((?<!\.)_[0-9A-F][0-9A-F\.]*)+$`iD', $tokens[$i][1]) === 1 || strpos($new_content, '0x') !== 0 && substr($new_content, -1) !== '.' && substr(strtolower($new_content), -1) !== 'e' && preg_match('`^(?:(?<![\.e])_[0-9][0-9e\.]*)+$`iD', $tokens[$i][1]) === 1)) {
                        $new_content .= $tokens[$i][1];
                        // Support floats.
                        if (substr(strtolower($tokens[$i][1]), -1) === 'e' && ($tokens[$i + 1] === '-' || $tokens[$i + 1] === '+')) {
                            $new_content .= $tokens[$i + 1];
                            $i++;
                        }
                        continue;
                    }
                    //end if
                    break;
                }
                //end for
                if ($new_type === T_LNUMBER && (stripos($new_content, '0x') === 0 && hexdec(str_replace('_', '', $new_content)) > PHP_INT_MAX || stripos($new_content, '0b') === 0 && bindec(str_replace('_', '', $new_content)) > PHP_INT_MAX || stripos($new_content, '0o') === 0 && octdec(str_replace('_', '', $new_content)) > PHP_INT_MAX || (stripos($new_content, '0x') !== 0 && stripos($new_content, 'e') !== false || strpos($new_content, '.') !== false) || strpos($new_content, '0') === 0 && stripos($new_content, '0x') !== 0 && stripos($new_content, '0b') !== 0 && octdec(str_replace('_', '', $new_content)) > PHP_INT_MAX || strpos($new_content, '0') !== 0 && str_replace('_', '', $new_content) > PHP_INT_MAX)) {
                    $new_type = T_DNUMBER;
                }
                $new_token = [];
                $new_token['code'] = $new_type;
                $new_token['type'] = Util\Tokens::token_name($new_type);
                $new_token['content'] = $new_content;
                $final_tokens[$new_stack_ptr] = $new_token;
                $new_stack_ptr++;
                $stack_ptr = $i - 1;
                continue;
            }
            //end if
            /*
                Backfill the T_MATCH token for PHP versions < 8.0 and
                do initial correction for non-match expression T_MATCH tokens
                to T_STRING for PHP >= 8.0.
                A final check for non-match expression T_MATCH tokens is done
                in PHP::processAdditional().
            */
            if ($token_is_array === true && ($token[0] === T_STRING && strtolower($token[1]) === 'match' || $token[0] === T_MATCH)) {
                $is_match = false;
                for ($x = $stack_ptr + 1; $x < $num_tokens; $x++) {
                    if (isset($tokens[$x][0], Util\Tokens::$empty_tokens[$tokens[$x][0]]) === true) {
                        continue;
                    }
                    if ($tokens[$x] !== '(') {
                        // This is not a match expression.
                        break;
                    }
                    if (isset($this->tstring_contexts[$final_tokens[$last_not_empty_token]['code']]) === true) {
                        // Also not a match expression.
                        break;
                    }
                    $is_match = true;
                    break;
                }
                //end for
                if ($is_match === true && $token[0] === T_STRING) {
                    $new_token = [];
                    $new_token['code'] = T_MATCH;
                    $new_token['type'] = 'T_MATCH';
                    $new_token['content'] = $token[1];
                    if (PHP_CODESNIFFER_VERBOSITY > 1) {
                        echo "\t\t* token {$stack_ptr} changed from T_STRING to T_MATCH" . PHP_EOL;
                    }
                    $final_tokens[$new_stack_ptr] = $new_token;
                    $new_stack_ptr++;
                    continue;
                }
                if ($is_match === false && $token[0] === T_MATCH) {
                    // PHP 8.0, match keyword, but not a match expression.
                    $new_token = [];
                    $new_token['code'] = T_STRING;
                    $new_token['type'] = 'T_STRING';
                    $new_token['content'] = $token[1];
                    if (PHP_CODESNIFFER_VERBOSITY > 1) {
                        echo "\t\t* token {$stack_ptr} changed from T_MATCH to T_STRING" . PHP_EOL;
                    }
                    $final_tokens[$new_stack_ptr] = $new_token;
                    $new_stack_ptr++;
                    continue;
                }
                //end if
            }
            //end if
            /*
                Retokenize the T_DEFAULT in match control structures as T_MATCH_DEFAULT
                to prevent scope being set and the scope for switch default statements
                breaking.
            */
            if ($token_is_array === true && $token[0] === T_DEFAULT && isset($this->tstring_contexts[$final_tokens[$last_not_empty_token]['code']]) === false) {
                for ($x = $stack_ptr + 1; $x < $num_tokens; $x++) {
                    if ($tokens[$x] === ',') {
                        // Skip over potential trailing comma (supported in PHP).
                        continue;
                    }
                    if (is_array($tokens[$x]) === false || isset(Util\Tokens::$empty_tokens[$tokens[$x][0]]) === false) {
                        // Non-empty, non-comma content.
                        break;
                    }
                }
                if (isset($tokens[$x]) === true && is_array($tokens[$x]) === true && $tokens[$x][0] === T_DOUBLE_ARROW) {
                    // Modify the original token stack for the double arrow so that
                    // future checks can disregard the double arrow token more easily.
                    // For match expression "case" statements, this is handled
                    // in PHP::processAdditional().
                    $tokens[$x][0] = T_MATCH_ARROW;
                    if (PHP_CODESNIFFER_VERBOSITY > 1) {
                        echo "\t\t* token {$x} changed from T_DOUBLE_ARROW to T_MATCH_ARROW" . PHP_EOL;
                    }
                    $new_token = [];
                    $new_token['code'] = T_MATCH_DEFAULT;
                    $new_token['type'] = 'T_MATCH_DEFAULT';
                    $new_token['content'] = $token[1];
                    if (PHP_CODESNIFFER_VERBOSITY > 1) {
                        echo "\t\t* token {$stack_ptr} changed from T_DEFAULT to T_MATCH_DEFAULT" . PHP_EOL;
                    }
                    $final_tokens[$new_stack_ptr] = $new_token;
                    $new_stack_ptr++;
                    continue;
                }
                //end if
            }
            //end if
            /*
                Convert ? to T_NULLABLE OR T_INLINE_THEN
            */
            if ($token_is_array === false && $token[0] === '?') {
                $new_token = [];
                $new_token['content'] = '?';
                /*
                 * Check if the next non-empty token is one of the tokens which can be used
                 * in type declarations. If not, it's definitely a ternary.
                 * At this point, the only token types which need to be taken into consideration
                 * as potential type declarations are identifier names, T_ARRAY, T_CALLABLE and T_NS_SEPARATOR.
                 */
                $last_relevant_non_empty = null;
                for ($i = $stack_ptr + 1; $i < $num_tokens; $i++) {
                    if (is_array($tokens[$i]) === true) {
                        $token_type = $tokens[$i][0];
                    } else {
                        $token_type = $tokens[$i];
                    }
                    if (isset(Util\Tokens::$empty_tokens[$token_type]) === true) {
                        continue;
                    }
                    if ($token_type === T_STRING || $token_type === T_NAME_FULLY_QUALIFIED || $token_type === T_NAME_RELATIVE || $token_type === T_NAME_QUALIFIED || $token_type === T_ARRAY || $token_type === T_NAMESPACE || $token_type === T_NS_SEPARATOR) {
                        $last_relevant_non_empty = $token_type;
                        continue;
                    }
                    if ($token_type !== T_CALLABLE && isset($last_relevant_non_empty) === false || $last_relevant_non_empty === T_ARRAY && $token_type === '(' || ($last_relevant_non_empty === T_STRING || $last_relevant_non_empty === T_NAME_FULLY_QUALIFIED || $last_relevant_non_empty === T_NAME_RELATIVE || $last_relevant_non_empty === T_NAME_QUALIFIED) && ($token_type === T_DOUBLE_COLON || $token_type === '(' || $token_type === ':')) {
                        if (PHP_CODESNIFFER_VERBOSITY > 1) {
                            echo "\t\t* token {$stack_ptr} changed from ? to T_INLINE_THEN" . PHP_EOL;
                        }
                        $new_token['code'] = T_INLINE_THEN;
                        $new_token['type'] = 'T_INLINE_THEN';
                        $inside_inline_if[] = $stack_ptr;
                        $final_tokens[$new_stack_ptr] = $new_token;
                        $new_stack_ptr++;
                        continue 2;
                    }
                    break;
                }
                //end for
                /*
                 * This can still be a nullable type or a ternary.
                 * Do additional checking.
                 */
                $prev_non_empty = null;
                $last_seen_non_empty = null;
                for ($i = $stack_ptr - 1; $i >= 0; $i--) {
                    if (is_array($tokens[$i]) === true) {
                        $token_type = $tokens[$i][0];
                    } else {
                        $token_type = $tokens[$i];
                    }
                    if ($token_type === T_STATIC && ($last_seen_non_empty === T_DOUBLE_COLON || $last_seen_non_empty === '(')) {
                        $last_seen_non_empty = $token_type;
                        continue;
                    }
                    if ($prev_non_empty === null && isset(Util\Tokens::$empty_tokens[$token_type]) === false) {
                        // Found the previous non-empty token.
                        if ($token_type === ':' || $token_type === ',' || $token_type === T_ATTRIBUTE_END) {
                            $new_token['code'] = T_NULLABLE;
                            $new_token['type'] = 'T_NULLABLE';
                            if (PHP_CODESNIFFER_VERBOSITY > 1) {
                                echo "\t\t* token {$stack_ptr} changed from ? to T_NULLABLE" . PHP_EOL;
                            }
                            break;
                        }
                        $prev_non_empty = $token_type;
                    }
                    if ($token_type === T_FUNCTION || $token_type === T_FN || isset(Util\Tokens::$method_prefixes[$token_type]) === true || $token_type === T_VAR) {
                        if (PHP_CODESNIFFER_VERBOSITY > 1) {
                            echo "\t\t* token {$stack_ptr} changed from ? to T_NULLABLE" . PHP_EOL;
                        }
                        $new_token['code'] = T_NULLABLE;
                        $new_token['type'] = 'T_NULLABLE';
                        break;
                    } elseif (in_array($token_type, [T_DOUBLE_ARROW, T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO, '=', '{', ';'], true) === true) {
                        if (PHP_CODESNIFFER_VERBOSITY > 1) {
                            echo "\t\t* token {$stack_ptr} changed from ? to T_INLINE_THEN" . PHP_EOL;
                        }
                        $new_token['code'] = T_INLINE_THEN;
                        $new_token['type'] = 'T_INLINE_THEN';
                        $inside_inline_if[] = $stack_ptr;
                        break;
                    }
                    if (isset(Util\Tokens::$empty_tokens[$token_type]) === false) {
                        $last_seen_non_empty = $token_type;
                    }
                }
                //end for
                $final_tokens[$new_stack_ptr] = $new_token;
                $new_stack_ptr++;
                continue;
            }
            //end if
            /*
                Tokens after a double colon may look like scope openers,
                such as when writing code like Foo::NAMESPACE, but they are
                only ever variables or strings.
            */
            if ($stack_ptr > 1 && (is_array($tokens[$stack_ptr - 1]) === true && $tokens[$stack_ptr - 1][0] === T_PAAMAYIM_NEKUDOTAYIM) && $token_is_array === true && $token[0] !== T_STRING && $token[0] !== T_VARIABLE && $token[0] !== T_DOLLAR && isset(Util\Tokens::$empty_tokens[$token[0]]) === false) {
                $new_token = [];
                $new_token['code'] = T_STRING;
                $new_token['type'] = 'T_STRING';
                $new_token['content'] = $token[1];
                $final_tokens[$new_stack_ptr] = $new_token;
                $new_stack_ptr++;
                continue;
            }
            /*
                Backfill the T_FN token for PHP versions < 7.4.
            */
            if ($token_is_array === true && $token[0] === T_STRING && strtolower($token[1]) === 'fn') {
                // Modify the original token stack so that
                // future checks (like looking for T_NULLABLE) can
                // detect the T_FN token more easily.
                $tokens[$stack_ptr][0] = T_FN;
                $token[0] = T_FN;
                if (PHP_CODESNIFFER_VERBOSITY > 1) {
                    echo "\t\t* token {$stack_ptr} changed from T_STRING to T_FN" . PHP_EOL;
                }
            }
            /*
                This is a special condition for T_ARRAY tokens used for
                function return types. We want to keep the parenthesis map clean,
                so let's tag these tokens as T_STRING.
            */
            if ($token_is_array === true && ($token[0] === T_FUNCTION || $token[0] === T_FN) && $final_tokens[$last_not_empty_token]['code'] !== T_USE) {
                // Go looking for the colon to start the return type hint.
                // Start by finding the closing parenthesis of the function.
                $parenthesis_stack = [];
                $parenthesis_closer = false;
                for ($x = $stack_ptr + 1; $x < $num_tokens; $x++) {
                    if (is_array($tokens[$x]) === false && $tokens[$x] === '(') {
                        $parenthesis_stack[] = $x;
                    } elseif (is_array($tokens[$x]) === false && $tokens[$x] === ')') {
                        array_pop($parenthesis_stack);
                        if (empty($parenthesis_stack) === true) {
                            $parenthesis_closer = $x;
                            break;
                        }
                    }
                }
                if ($parenthesis_closer !== false) {
                    for ($x = $parenthesis_closer + 1; $x < $num_tokens; $x++) {
                        if (is_array($tokens[$x]) === false || isset(Util\Tokens::$empty_tokens[$tokens[$x][0]]) === false) {
                            // Non-empty content.
                            if (is_array($tokens[$x]) === true && $tokens[$x][0] === T_USE) {
                                // Found a use statements, so search ahead for the closing parenthesis.
                                for ($x += 1; $x < $num_tokens; $x++) {
                                    if (is_array($tokens[$x]) === false && $tokens[$x] === ')') {
                                        continue 2;
                                    }
                                }
                            }
                            break;
                        }
                    }
                    if (isset($tokens[$x]) === true && is_array($tokens[$x]) === false && $tokens[$x] === ':') {
                        // Find the start of the return type.
                        for ($x += 1; $x < $num_tokens; $x++) {
                            if (is_array($tokens[$x]) === true && isset(Util\Tokens::$empty_tokens[$tokens[$x][0]]) === true) {
                                // Whitespace or comments before the return type.
                                continue;
                            }
                            if (is_array($tokens[$x]) === false && $tokens[$x] === '?') {
                                // Found a nullable operator, so skip it.
                                // But also convert the token to save the tokenizer
                                // a bit of time later on.
                                $tokens[$x] = [T_NULLABLE, '?'];
                                if (PHP_CODESNIFFER_VERBOSITY > 1) {
                                    echo "\t\t* token {$x} changed from ? to T_NULLABLE" . PHP_EOL;
                                }
                                continue;
                            }
                            break;
                        }
                        //end for
                    }
                    //end if
                }
                //end if
            }
            //end if
            /*
                Before PHP 7, the <=> operator was tokenized as
                T_IS_SMALLER_OR_EQUAL followed by T_GREATER_THAN.
                So look for and combine these tokens in earlier versions.
            */
            if ($token_is_array === true && $token[0] === T_IS_SMALLER_OR_EQUAL && isset($tokens[$stack_ptr + 1]) === true && $tokens[$stack_ptr + 1][0] === '>') {
                $new_token = [];
                $new_token['code'] = T_SPACESHIP;
                $new_token['type'] = 'T_SPACESHIP';
                $new_token['content'] = '<=>';
                $final_tokens[$new_stack_ptr] = $new_token;
                $new_stack_ptr++;
                $stack_ptr++;
                continue;
            }
            /*
                PHP doesn't assign a token to goto labels, so we have to.
                These are just string tokens with a single colon after them. Double
                colons are already tokenized and so don't interfere with this check.
                But we do have to account for CASE statements, that look just like
                goto labels.
            */
            if ($token_is_array === true && $token[0] === T_STRING && isset($tokens[$stack_ptr + 1]) === true && $tokens[$stack_ptr + 1] === ':' && (is_array($tokens[$stack_ptr - 1]) === false || $tokens[$stack_ptr - 1][0] !== T_PAAMAYIM_NEKUDOTAYIM)) {
                $stop_tokens = [T_CASE => true, T_SEMICOLON => true, T_OPEN_TAG => true, T_OPEN_CURLY_BRACKET => true, T_INLINE_THEN => true, T_ENUM => true];
                for ($x = $new_stack_ptr - 1; $x > 0; $x--) {
                    if (isset($stop_tokens[$final_tokens[$x]['code']]) === true) {
                        break;
                    }
                }
                if ($final_tokens[$x]['code'] !== T_CASE && $final_tokens[$x]['code'] !== T_INLINE_THEN && $final_tokens[$x]['code'] !== T_ENUM) {
                    $final_tokens[$new_stack_ptr] = ['content' => $token[1] . ':', 'code' => T_GOTO_LABEL, 'type' => 'T_GOTO_LABEL'];
                    if (PHP_CODESNIFFER_VERBOSITY > 1) {
                        echo "\t\t* token {$stack_ptr} changed from T_STRING to T_GOTO_LABEL" . PHP_EOL;
                        echo "\t\t* skipping T_COLON token " . ($stack_ptr + 1) . PHP_EOL;
                    }
                    $new_stack_ptr++;
                    $stack_ptr++;
                    continue;
                }
            }
            //end if
            /*
                If this token has newlines in its content, split each line up
                and create a new token for each line. We do this so it's easier
                to ascertain where errors occur on a line.
                Note that $token[1] is the token's content.
            */
            if ($token_is_array === true && strpos($token[1], $this->eol_char) !== false) {
                $token_lines = explode($this->eol_char, $token[1]);
                $num_lines = count($token_lines);
                $new_token = ['type' => Util\Tokens::token_name($token[0]), 'code' => $token[0], 'content' => ''];
                for ($i = 0; $i < $num_lines; $i++) {
                    $new_token['content'] = $token_lines[$i];
                    if ($i === $num_lines - 1) {
                        if ($token_lines[$i] === '') {
                            break;
                        }
                    } else {
                        $new_token['content'] .= $this->eol_char;
                    }
                    $final_tokens[$new_stack_ptr] = $new_token;
                    $new_stack_ptr++;
                }
            } else {
                // Some T_STRING tokens should remain that way due to their context.
                if ($token_is_array === true && $token[0] === T_STRING) {
                    $preserve_tstring = false;
                    if (isset($this->tstring_contexts[$final_tokens[$last_not_empty_token]['code']]) === true) {
                        $preserve_tstring = true;
                        // Special case for syntax like: return new self/new parent
                        // where self/parent should not be a string.
                        $token_content_lower = strtolower($token[1]);
                        if ($final_tokens[$last_not_empty_token]['code'] === T_NEW && ($token_content_lower === 'self' || $token_content_lower === 'parent')) {
                            $preserve_tstring = false;
                        }
                    } elseif ($final_tokens[$last_not_empty_token]['content'] === '&') {
                        // Function names for functions declared to return by reference.
                        for ($i = $last_not_empty_token - 1; $i >= 0; $i--) {
                            if (isset(Util\Tokens::$empty_tokens[$final_tokens[$i]['code']]) === true) {
                                continue;
                            }
                            if ($final_tokens[$i]['code'] === T_FUNCTION) {
                                $preserve_tstring = true;
                            }
                            break;
                        }
                    } else {
                        // Keywords with special PHPCS token when used as a function call.
                        for ($i = $stack_ptr + 1; $i < $num_tokens; $i++) {
                            if (is_array($tokens[$i]) === true && isset(Util\Tokens::$empty_tokens[$tokens[$i][0]]) === true) {
                                continue;
                            }
                            if ($tokens[$i][0] === '(') {
                                $preserve_tstring = true;
                            }
                            break;
                        }
                    }
                    //end if
                    if ($preserve_tstring === true) {
                        $final_tokens[$new_stack_ptr] = ['code' => T_STRING, 'type' => 'T_STRING', 'content' => $token[1]];
                        $new_stack_ptr++;
                        continue;
                    }
                }
                //end if
                $new_token = null;
                if ($token_is_array === false) {
                    if (isset(self::$resolve_token_cache[$token[0]]) === true) {
                        $new_token = self::$resolve_token_cache[$token[0]];
                    }
                } else {
                    $cache_key = null;
                    if ($token[0] === T_STRING) {
                        $cache_key = strtolower($token[1]);
                    } elseif ($token[0] !== T_CURLY_OPEN) {
                        $cache_key = $token[0];
                    }
                    if ($cache_key !== null && isset(self::$resolve_token_cache[$cache_key]) === true) {
                        $new_token = self::$resolve_token_cache[$cache_key];
                        $new_token['content'] = $token[1];
                    }
                }
                if ($new_token === null) {
                    $new_token = self::standardise_token($token);
                }
                // Convert colons that are actually the ELSE component of an
                // inline IF statement.
                if (empty($inside_inline_if) === false && $new_token['code'] === T_COLON) {
                    $is_inline_if = true;
                    // Make sure this isn't a named parameter label.
                    // Get the previous non-empty token.
                    for ($i = $stack_ptr - 1; $i > 0; $i--) {
                        if (is_array($tokens[$i]) === false || isset(Util\Tokens::$empty_tokens[$tokens[$i][0]]) === false) {
                            break;
                        }
                    }
                    if ($tokens[$i][0] === T_PARAM_NAME) {
                        $is_inline_if = false;
                        if (PHP_CODESNIFFER_VERBOSITY > 1) {
                            echo "\t\t* token is parameter label, not T_INLINE_ELSE" . PHP_EOL;
                        }
                    }
                    if ($is_inline_if === true) {
                        // Make sure this isn't a return type separator.
                        for ($i = $stack_ptr - 1; $i > 0; $i--) {
                            if (is_array($tokens[$i]) === false || $tokens[$i][0] !== T_DOC_COMMENT && $tokens[$i][0] !== T_COMMENT && $tokens[$i][0] !== T_WHITESPACE) {
                                break;
                            }
                        }
                        if ($tokens[$i] === ')') {
                            $paren_count = 1;
                            for ($i--; $i > 0; $i--) {
                                if ($tokens[$i] === '(') {
                                    $paren_count--;
                                    if ($paren_count === 0) {
                                        break;
                                    }
                                } elseif ($tokens[$i] === ')') {
                                    $paren_count++;
                                }
                            }
                            // We've found the open parenthesis, so if the previous
                            // non-empty token is FUNCTION or USE, this is a return type.
                            // Note that we need to skip T_STRING tokens here as these
                            // can be function names.
                            for ($i--; $i > 0; $i--) {
                                if (is_array($tokens[$i]) === false || $tokens[$i][0] !== T_DOC_COMMENT && $tokens[$i][0] !== T_COMMENT && $tokens[$i][0] !== T_WHITESPACE && $tokens[$i][0] !== T_STRING) {
                                    break;
                                }
                            }
                            if ($tokens[$i][0] === T_FUNCTION || $tokens[$i][0] === T_FN || $tokens[$i][0] === T_USE) {
                                $is_inline_if = false;
                                if (PHP_CODESNIFFER_VERBOSITY > 1) {
                                    echo "\t\t* token is return type, not T_INLINE_ELSE" . PHP_EOL;
                                }
                            }
                        }
                        //end if
                    }
                    //end if
                    // Check to see if this is a CASE or DEFAULT opener.
                    if ($is_inline_if === true) {
                        $inline_if_token = $inside_inline_if[count($inside_inline_if) - 1];
                        for ($i = $stack_ptr; $i > $inline_if_token; $i--) {
                            if (is_array($tokens[$i]) === true && ($tokens[$i][0] === T_CASE || $tokens[$i][0] === T_DEFAULT)) {
                                $is_inline_if = false;
                                if (PHP_CODESNIFFER_VERBOSITY > 1) {
                                    echo "\t\t* token is T_CASE or T_DEFAULT opener, not T_INLINE_ELSE" . PHP_EOL;
                                }
                                break;
                            }
                            if (is_array($tokens[$i]) === false && ($tokens[$i] === ';' || $tokens[$i] === '{' || $tokens[$i] === '}')) {
                                break;
                            }
                        }
                        //end for
                    }
                    //end if
                    if ($is_inline_if === true) {
                        array_pop($inside_inline_if);
                        $new_token['code'] = T_INLINE_ELSE;
                        $new_token['type'] = 'T_INLINE_ELSE';
                        if (PHP_CODESNIFFER_VERBOSITY > 1) {
                            echo "\t\t* token changed from T_COLON to T_INLINE_ELSE" . PHP_EOL;
                        }
                    }
                }
                //end if
                // This is a special condition for T_ARRAY tokens used for anything else
                // but array declarations, like type hinting function arguments as
                // being arrays.
                // We want to keep the parenthesis map clean, so let's tag these tokens as
                // T_STRING.
                if ($new_token['code'] === T_ARRAY) {
                    for ($i = $stack_ptr + 1; $i < $num_tokens; $i++) {
                        if (is_array($tokens[$i]) === false || isset(Util\Tokens::$empty_tokens[$tokens[$i][0]]) === false) {
                            // Non-empty content.
                            break;
                        }
                    }
                    if ($i !== $num_tokens && $tokens[$i] !== '(') {
                        $new_token['code'] = T_STRING;
                        $new_token['type'] = 'T_STRING';
                    }
                }
                // This is a special case when checking PHP 5.5+ code in PHP < 5.5
                // where "finally" should be T_FINALLY instead of T_STRING.
                if ($new_token['code'] === T_STRING && strtolower($new_token['content']) === 'finally' && $final_tokens[$last_not_empty_token]['code'] === T_CLOSE_CURLY_BRACKET) {
                    $new_token['code'] = T_FINALLY;
                    $new_token['type'] = 'T_FINALLY';
                }
                // This is a special case for PHP 5.6 use function and use const
                // where "function" and "const" should be T_STRING instead of T_FUNCTION
                // and T_CONST.
                if (($new_token['code'] === T_FUNCTION || $new_token['code'] === T_CONST) && ($final_tokens[$last_not_empty_token]['code'] === T_USE || $inside_use_group === true)) {
                    $new_token['code'] = T_STRING;
                    $new_token['type'] = 'T_STRING';
                }
                // This is a special case for use groups in PHP 7+ where leaving
                // the curly braces as their normal tokens would confuse
                // the scope map and sniffs.
                if ($new_token['code'] === T_OPEN_CURLY_BRACKET && $final_tokens[$last_not_empty_token]['code'] === T_NS_SEPARATOR) {
                    $new_token['code'] = T_OPEN_USE_GROUP;
                    $new_token['type'] = 'T_OPEN_USE_GROUP';
                    $inside_use_group = true;
                }
                if ($inside_use_group === true && $new_token['code'] === T_CLOSE_CURLY_BRACKET) {
                    $new_token['code'] = T_CLOSE_USE_GROUP;
                    $new_token['type'] = 'T_CLOSE_USE_GROUP';
                    $inside_use_group = false;
                }
                $final_tokens[$new_stack_ptr] = $new_token;
                $new_stack_ptr++;
            }
            //end if
        }
        //end for
        if (PHP_CODESNIFFER_VERBOSITY > 1) {
            echo "\t*** END PHP TOKENIZING ***" . PHP_EOL;
        }
        return $final_tokens;
    }
    //end tokenize()
    /**
     * Performs additional processing after main tokenizing.
     *
     * This additional processing checks for CASE statements that are using curly
     * braces for scope openers and closers. It also turns some T_FUNCTION tokens
     * into T_CLOSURE when they are not standard function definitions. It also
     * detects short array syntax and converts those square brackets into new tokens.
     * It also corrects some usage of the static and class keywords. It also
     * assigns tokens to function return types.
     *
     * @return void
     */
    protected function process_additional()
    {
        if (PHP_CODESNIFFER_VERBOSITY > 1) {
            echo "\t*** START ADDITIONAL PHP PROCESSING ***" . PHP_EOL;
        }
        $this->create_attributes_nesting_map();
        $num_tokens = count($this->tokens);
        for ($i = $num_tokens - 1; $i >= 0; $i--) {
            // Check for any unset scope conditions due to alternate IF/ENDIF syntax.
            if (isset($this->tokens[$i]['scope_opener']) === true && isset($this->tokens[$i]['scope_condition']) === false) {
                $this->tokens[$i]['scope_condition'] = $this->tokens[$this->tokens[$i]['scope_opener']]['scope_condition'];
            }
            if ($this->tokens[$i]['code'] === T_FUNCTION) {
                /*
                    Detect functions that are actually closures and
                    assign them a different token.
                */
                if (isset($this->tokens[$i]['scope_opener']) === true) {
                    for ($x = $i + 1; $x < $num_tokens; $x++) {
                        if (isset(Util\Tokens::$empty_tokens[$this->tokens[$x]['code']]) === false && $this->tokens[$x]['code'] !== T_BITWISE_AND) {
                            break;
                        }
                    }
                    if ($this->tokens[$x]['code'] === T_OPEN_PARENTHESIS) {
                        $this->tokens[$i]['code'] = T_CLOSURE;
                        $this->tokens[$i]['type'] = 'T_CLOSURE';
                        if (PHP_CODESNIFFER_VERBOSITY > 1) {
                            $line = $this->tokens[$i]['line'];
                            echo "\t* token {$i} on line {$line} changed from T_FUNCTION to T_CLOSURE" . PHP_EOL;
                        }
                        for ($x = $this->tokens[$i]['scope_opener'] + 1; $x < $this->tokens[$i]['scope_closer']; $x++) {
                            if (isset($this->tokens[$x]['conditions'][$i]) === false) {
                                continue;
                            }
                            $this->tokens[$x]['conditions'][$i] = T_CLOSURE;
                            if (PHP_CODESNIFFER_VERBOSITY > 1) {
                                $type = $this->tokens[$x]['type'];
                                echo "\t\t* cleaned {$x} ({$type}) *" . PHP_EOL;
                            }
                        }
                    }
                }
                //end if
                continue;
            }
            if ($this->tokens[$i]['code'] === T_CLASS && isset($this->tokens[$i]['scope_opener']) === true) {
                /*
                    Detect anonymous classes and assign them a different token.
                */
                for ($x = $i + 1; $x < $num_tokens; $x++) {
                    if (isset(Util\Tokens::$empty_tokens[$this->tokens[$x]['code']]) === false) {
                        break;
                    }
                }
                if ($this->tokens[$x]['code'] === T_OPEN_PARENTHESIS || $this->tokens[$x]['code'] === T_OPEN_CURLY_BRACKET || $this->tokens[$x]['code'] === T_EXTENDS || $this->tokens[$x]['code'] === T_IMPLEMENTS) {
                    $this->tokens[$i]['code'] = T_ANON_CLASS;
                    $this->tokens[$i]['type'] = 'T_ANON_CLASS';
                    if (PHP_CODESNIFFER_VERBOSITY > 1) {
                        $line = $this->tokens[$i]['line'];
                        echo "\t* token {$i} on line {$line} changed from T_CLASS to T_ANON_CLASS" . PHP_EOL;
                    }
                    if ($this->tokens[$x]['code'] === T_OPEN_PARENTHESIS && isset($this->tokens[$x]['parenthesis_closer']) === true) {
                        $closer = $this->tokens[$x]['parenthesis_closer'];
                        $this->tokens[$i]['parenthesis_opener'] = $x;
                        $this->tokens[$i]['parenthesis_closer'] = $closer;
                        $this->tokens[$i]['parenthesis_owner'] = $i;
                        $this->tokens[$x]['parenthesis_owner'] = $i;
                        $this->tokens[$closer]['parenthesis_owner'] = $i;
                        if (PHP_CODESNIFFER_VERBOSITY > 1) {
                            $line = $this->tokens[$i]['line'];
                            echo "\t\t* added parenthesis keys to T_ANON_CLASS token {$i} on line {$line}" . PHP_EOL;
                        }
                    }
                    for ($x = $this->tokens[$i]['scope_opener'] + 1; $x < $this->tokens[$i]['scope_closer']; $x++) {
                        if (isset($this->tokens[$x]['conditions'][$i]) === false) {
                            continue;
                        }
                        $this->tokens[$x]['conditions'][$i] = T_ANON_CLASS;
                        if (PHP_CODESNIFFER_VERBOSITY > 1) {
                            $type = $this->tokens[$x]['type'];
                            echo "\t\t* cleaned {$x} ({$type}) *" . PHP_EOL;
                        }
                    }
                }
                //end if
                continue;
            }
            if ($this->tokens[$i]['code'] === T_FN && isset($this->tokens[$i + 1]) === true) {
                // Possible arrow function.
                for ($x = $i + 1; $x < $num_tokens; $x++) {
                    if (isset(Util\Tokens::$empty_tokens[$this->tokens[$x]['code']]) === false && $this->tokens[$x]['code'] !== T_BITWISE_AND) {
                        // Non-whitespace content.
                        break;
                    }
                }
                if (isset($this->tokens[$x]) === true && $this->tokens[$x]['code'] === T_OPEN_PARENTHESIS) {
                    $ignore = Util\Tokens::$empty_tokens;
                    $ignore += [T_ARRAY => T_ARRAY, T_CALLABLE => T_CALLABLE, T_COLON => T_COLON, T_NAMESPACE => T_NAMESPACE, T_NS_SEPARATOR => T_NS_SEPARATOR, T_NULL => T_NULL, T_NULLABLE => T_NULLABLE, T_PARENT => T_PARENT, T_SELF => T_SELF, T_STATIC => T_STATIC, T_STRING => T_STRING, T_TYPE_UNION => T_TYPE_UNION, T_TYPE_INTERSECTION => T_TYPE_INTERSECTION];
                    $closer = $this->tokens[$x]['parenthesis_closer'];
                    for ($arrow = $closer + 1; $arrow < $num_tokens; $arrow++) {
                        if (isset($ignore[$this->tokens[$arrow]['code']]) === false) {
                            break;
                        }
                    }
                    if ($this->tokens[$arrow]['code'] === T_DOUBLE_ARROW) {
                        $end_tokens = [T_COLON => true, T_COMMA => true, T_SEMICOLON => true, T_CLOSE_PARENTHESIS => true, T_CLOSE_SQUARE_BRACKET => true, T_CLOSE_CURLY_BRACKET => true, T_CLOSE_SHORT_ARRAY => true, T_OPEN_TAG => true, T_CLOSE_TAG => true];
                        $in_ternary = false;
                        $last_end_token = null;
                        for ($scope_closer = $arrow + 1; $scope_closer < $num_tokens; $scope_closer++) {
                            // Arrow function closer should never be shared with the closer of a match
                            // control structure.
                            if (isset($this->tokens[$scope_closer]['scope_closer'], $this->tokens[$scope_closer]['scope_condition']) === true && $scope_closer === $this->tokens[$scope_closer]['scope_closer'] && $this->tokens[$this->tokens[$scope_closer]['scope_condition']]['code'] === T_MATCH) {
                                if ($arrow < $this->tokens[$scope_closer]['scope_condition']) {
                                    // Match in return value of arrow function. Move on to the next token.
                                    continue;
                                }
                                // Arrow function as return value for the last match case without trailing comma.
                                if ($last_end_token !== null) {
                                    $scope_closer = $last_end_token;
                                    break;
                                }
                                for ($last_non_empty = $scope_closer - 1; $last_non_empty > $arrow; $last_non_empty--) {
                                    if (isset(Util\Tokens::$empty_tokens[$this->tokens[$last_non_empty]['code']]) === false) {
                                        $scope_closer = $last_non_empty;
                                        break 2;
                                    }
                                }
                            }
                            if (isset($end_tokens[$this->tokens[$scope_closer]['code']]) === true) {
                                if ($last_end_token !== null && (isset($this->tokens[$scope_closer]['parenthesis_opener']) === true && $this->tokens[$scope_closer]['parenthesis_opener'] < $arrow || isset($this->tokens[$scope_closer]['bracket_opener']) === true && $this->tokens[$scope_closer]['bracket_opener'] < $arrow)) {
                                    for ($last_non_empty = $scope_closer - 1; $last_non_empty > $arrow; $last_non_empty--) {
                                        if (isset(Util\Tokens::$empty_tokens[$this->tokens[$last_non_empty]['code']]) === false) {
                                            $scope_closer = $last_non_empty;
                                            break;
                                        }
                                    }
                                }
                                break;
                            }
                            if ($in_ternary === false && isset($this->tokens[$scope_closer]['scope_closer'], $this->tokens[$scope_closer]['scope_condition']) === true && $scope_closer === $this->tokens[$scope_closer]['scope_closer'] && $this->tokens[$this->tokens[$scope_closer]['scope_condition']]['code'] === T_FN) {
                                // Found a nested arrow function that already has the closer set and is in
                                // the same scope as us, so we can use its closer.
                                break;
                            }
                            if (isset($this->tokens[$scope_closer]['scope_closer']) === true && $this->tokens[$scope_closer]['code'] !== T_INLINE_ELSE && $this->tokens[$scope_closer]['code'] !== T_END_HEREDOC && $this->tokens[$scope_closer]['code'] !== T_END_NOWDOC) {
                                // We minus 1 here in case the closer can be shared with us.
                                $scope_closer = $this->tokens[$scope_closer]['scope_closer'] - 1;
                                continue;
                            }
                            if (isset($this->tokens[$scope_closer]['parenthesis_closer']) === true) {
                                $scope_closer = $this->tokens[$scope_closer]['parenthesis_closer'];
                                $last_end_token = $scope_closer;
                                continue;
                            }
                            if (isset($this->tokens[$scope_closer]['bracket_closer']) === true) {
                                $scope_closer = $this->tokens[$scope_closer]['bracket_closer'];
                                $last_end_token = $scope_closer;
                                continue;
                            }
                            if ($this->tokens[$scope_closer]['code'] === T_INLINE_THEN) {
                                $in_ternary = true;
                                continue;
                            }
                            if ($this->tokens[$scope_closer]['code'] === T_INLINE_ELSE) {
                                if ($in_ternary === false) {
                                    break;
                                }
                                $in_ternary = false;
                                continue;
                            }
                        }
                        //end for
                        if ($scope_closer !== $num_tokens) {
                            if (PHP_CODESNIFFER_VERBOSITY > 1) {
                                $line = $this->tokens[$i]['line'];
                                echo "\t=> token {$i} on line {$line} processed as arrow function" . PHP_EOL;
                                echo "\t\t* scope opener set to {$arrow} *" . PHP_EOL;
                                echo "\t\t* scope closer set to {$scope_closer} *" . PHP_EOL;
                                echo "\t\t* parenthesis opener set to {$x} *" . PHP_EOL;
                                echo "\t\t* parenthesis closer set to {$closer} *" . PHP_EOL;
                            }
                            $this->tokens[$i]['code'] = T_FN;
                            $this->tokens[$i]['type'] = 'T_FN';
                            $this->tokens[$i]['scope_condition'] = $i;
                            $this->tokens[$i]['scope_opener'] = $arrow;
                            $this->tokens[$i]['scope_closer'] = $scope_closer;
                            $this->tokens[$i]['parenthesis_owner'] = $i;
                            $this->tokens[$i]['parenthesis_opener'] = $x;
                            $this->tokens[$i]['parenthesis_closer'] = $closer;
                            $this->tokens[$arrow]['code'] = T_FN_ARROW;
                            $this->tokens[$arrow]['type'] = 'T_FN_ARROW';
                            $this->tokens[$arrow]['scope_condition'] = $i;
                            $this->tokens[$arrow]['scope_opener'] = $arrow;
                            $this->tokens[$arrow]['scope_closer'] = $scope_closer;
                            $this->tokens[$scope_closer]['scope_condition'] = $i;
                            $this->tokens[$scope_closer]['scope_opener'] = $arrow;
                            $this->tokens[$scope_closer]['scope_closer'] = $scope_closer;
                            $opener = $this->tokens[$i]['parenthesis_opener'];
                            $closer = $this->tokens[$i]['parenthesis_closer'];
                            $this->tokens[$opener]['parenthesis_owner'] = $i;
                            $this->tokens[$closer]['parenthesis_owner'] = $i;
                            if (PHP_CODESNIFFER_VERBOSITY > 1) {
                                $line = $this->tokens[$arrow]['line'];
                                echo "\t\t* token {$arrow} on line {$line} changed from T_DOUBLE_ARROW to T_FN_ARROW" . PHP_EOL;
                            }
                        }
                        //end if
                    }
                    //end if
                }
                //end if
                // If after all that, the extra tokens are not set, this is not an arrow function.
                if (isset($this->tokens[$i]['scope_closer']) === false) {
                    if (PHP_CODESNIFFER_VERBOSITY > 1) {
                        $line = $this->tokens[$i]['line'];
                        echo "\t=> token {$i} on line {$line} is not an arrow function" . PHP_EOL;
                        echo "\t\t* token changed from T_FN to T_STRING" . PHP_EOL;
                    }
                    $this->tokens[$i]['code'] = T_STRING;
                    $this->tokens[$i]['type'] = 'T_STRING';
                }
            } else {
                if ($this->tokens[$i]['code'] === T_OPEN_SQUARE_BRACKET) {
                    if (isset($this->tokens[$i]['bracket_closer']) === false) {
                        continue;
                    }
                    // Unless there is a variable or a bracket before this token,
                    // it is the start of an array being defined using the short syntax.
                    $is_short_array = false;
                    $allowed = [T_CLOSE_SQUARE_BRACKET => T_CLOSE_SQUARE_BRACKET, T_CLOSE_CURLY_BRACKET => T_CLOSE_CURLY_BRACKET, T_CLOSE_PARENTHESIS => T_CLOSE_PARENTHESIS, T_VARIABLE => T_VARIABLE, T_OBJECT_OPERATOR => T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR => T_NULLSAFE_OBJECT_OPERATOR, T_STRING => T_STRING, T_CONSTANT_ENCAPSED_STRING => T_CONSTANT_ENCAPSED_STRING, T_DOUBLE_QUOTED_STRING => T_DOUBLE_QUOTED_STRING];
                    $allowed += Util\Tokens::$magic_constants;
                    for ($x = $i - 1; $x >= 0; $x--) {
                        // If we hit a scope opener, the statement has ended
                        // without finding anything, so it's probably an array
                        // using PHP 7.1 short list syntax.
                        if (isset($this->tokens[$x]['scope_opener']) === true) {
                            $is_short_array = true;
                            break;
                        }
                        if (isset(Util\Tokens::$empty_tokens[$this->tokens[$x]['code']]) === false) {
                            // Allow for control structures without braces.
                            if ($this->tokens[$x]['code'] === T_CLOSE_PARENTHESIS && isset($this->tokens[$x]['parenthesis_owner']) === true && isset(Util\Tokens::$scope_openers[$this->tokens[$this->tokens[$x]['parenthesis_owner']]['code']]) === true || isset($allowed[$this->tokens[$x]['code']]) === false) {
                                $is_short_array = true;
                            }
                            break;
                        }
                    }
                    //end for
                    if ($is_short_array === true) {
                        $this->tokens[$i]['code'] = T_OPEN_SHORT_ARRAY;
                        $this->tokens[$i]['type'] = 'T_OPEN_SHORT_ARRAY';
                        $closer = $this->tokens[$i]['bracket_closer'];
                        $this->tokens[$closer]['code'] = T_CLOSE_SHORT_ARRAY;
                        $this->tokens[$closer]['type'] = 'T_CLOSE_SHORT_ARRAY';
                        if (PHP_CODESNIFFER_VERBOSITY > 1) {
                            $line = $this->tokens[$i]['line'];
                            echo "\t* token {$i} on line {$line} changed from T_OPEN_SQUARE_BRACKET to T_OPEN_SHORT_ARRAY" . PHP_EOL;
                            $line = $this->tokens[$closer]['line'];
                            echo "\t* token {$closer} on line {$line} changed from T_CLOSE_SQUARE_BRACKET to T_CLOSE_SHORT_ARRAY" . PHP_EOL;
                        }
                    }
                    continue;
                }
                if ($this->tokens[$i]['code'] === T_MATCH) {
                    if (isset($this->tokens[$i]['scope_opener'], $this->tokens[$i]['scope_closer']) === false) {
                        // Not a match expression after all.
                        $this->tokens[$i]['code'] = T_STRING;
                        $this->tokens[$i]['type'] = 'T_STRING';
                        if (PHP_CODESNIFFER_VERBOSITY > 1) {
                            echo "\t\t* token {$i} changed from T_MATCH to T_STRING" . PHP_EOL;
                        }
                        if (isset($this->tokens[$i]['parenthesis_opener'], $this->tokens[$i]['parenthesis_closer']) === true) {
                            $opener = $this->tokens[$i]['parenthesis_opener'];
                            $closer = $this->tokens[$i]['parenthesis_closer'];
                            unset($this->tokens[$opener]['parenthesis_owner'], $this->tokens[$closer]['parenthesis_owner']);
                            unset($this->tokens[$i]['parenthesis_opener'], $this->tokens[$i]['parenthesis_closer'], $this->tokens[$i]['parenthesis_owner']);
                            if (PHP_CODESNIFFER_VERBOSITY > 1) {
                                echo "\t\t* cleaned parenthesis of token {$i} *" . PHP_EOL;
                            }
                        }
                    } else {
                        // Retokenize the double arrows for match expression cases to `T_MATCH_ARROW`.
                        $search_for = [T_OPEN_CURLY_BRACKET => T_OPEN_CURLY_BRACKET, T_OPEN_SQUARE_BRACKET => T_OPEN_SQUARE_BRACKET, T_OPEN_PARENTHESIS => T_OPEN_PARENTHESIS, T_OPEN_SHORT_ARRAY => T_OPEN_SHORT_ARRAY, T_DOUBLE_ARROW => T_DOUBLE_ARROW];
                        $search_for += Util\Tokens::$scope_openers;
                        for ($x = $this->tokens[$i]['scope_opener'] + 1; $x < $this->tokens[$i]['scope_closer']; $x++) {
                            if (isset($search_for[$this->tokens[$x]['code']]) === false) {
                                continue;
                            }
                            if (isset($this->tokens[$x]['scope_closer']) === true) {
                                $x = $this->tokens[$x]['scope_closer'];
                                continue;
                            }
                            if (isset($this->tokens[$x]['parenthesis_closer']) === true) {
                                $x = $this->tokens[$x]['parenthesis_closer'];
                                continue;
                            }
                            if (isset($this->tokens[$x]['bracket_closer']) === true) {
                                $x = $this->tokens[$x]['bracket_closer'];
                                continue;
                            }
                            // This must be a double arrow, but make sure anyhow.
                            if ($this->tokens[$x]['code'] === T_DOUBLE_ARROW) {
                                $this->tokens[$x]['code'] = T_MATCH_ARROW;
                                $this->tokens[$x]['type'] = 'T_MATCH_ARROW';
                                if (PHP_CODESNIFFER_VERBOSITY > 1) {
                                    echo "\t\t* token {$x} changed from T_DOUBLE_ARROW to T_MATCH_ARROW" . PHP_EOL;
                                }
                            }
                        }
                        //end for
                    }
                    //end if
                    continue;
                }
                if ($this->tokens[$i]['code'] === T_BITWISE_OR || $this->tokens[$i]['code'] === T_BITWISE_AND) {
                    /*
                        Convert "|" to T_TYPE_UNION or leave as T_BITWISE_OR.
                        Convert "&" to T_TYPE_INTERSECTION or leave as T_BITWISE_AND.
                    */
                    $allowed = [T_STRING => T_STRING, T_CALLABLE => T_CALLABLE, T_SELF => T_SELF, T_PARENT => T_PARENT, T_STATIC => T_STATIC, T_FALSE => T_FALSE, T_NULL => T_NULL, T_NAMESPACE => T_NAMESPACE, T_NS_SEPARATOR => T_NS_SEPARATOR];
                    $suspected_type = null;
                    $type_token_count = 0;
                    for ($x = $i + 1; $x < $num_tokens; $x++) {
                        if (isset(Util\Tokens::$empty_tokens[$this->tokens[$x]['code']]) === true) {
                            continue;
                        }
                        if (isset($allowed[$this->tokens[$x]['code']]) === true) {
                            ++$type_token_count;
                            continue;
                        }
                        if ($type_token_count > 0 && ($this->tokens[$x]['code'] === T_BITWISE_AND || $this->tokens[$x]['code'] === T_ELLIPSIS)) {
                            // Skip past reference and variadic indicators for parameter types.
                            continue;
                        }
                        if ($this->tokens[$x]['code'] === T_VARIABLE) {
                            // Parameter/Property defaults can not contain variables, so this could be a type.
                            $suspected_type = 'property or parameter';
                            break;
                        }
                        if ($this->tokens[$x]['code'] === T_DOUBLE_ARROW) {
                            // Possible arrow function.
                            $suspected_type = 'return';
                            break;
                        }
                        if ($this->tokens[$x]['code'] === T_SEMICOLON) {
                            // Possible abstract method or interface method.
                            $suspected_type = 'return';
                            break;
                        }
                        if ($this->tokens[$x]['code'] === T_OPEN_CURLY_BRACKET && isset($this->tokens[$x]['scope_condition']) === true && $this->tokens[$this->tokens[$x]['scope_condition']]['code'] === T_FUNCTION) {
                            $suspected_type = 'return';
                        }
                        break;
                    }
                    //end for
                    if ($type_token_count === 0) {
                        // Definitely not a union or intersection type, move on.
                        continue;
                    }
                    if (isset($suspected_type) === false) {
                        // Definitely not a union or intersection type, move on.
                        continue;
                    }
                    $type_token_count = 0;
                    $type_operators = [$i];
                    $confirmed = false;
                    for ($x = $i - 1; $x >= 0; $x--) {
                        if (isset(Util\Tokens::$empty_tokens[$this->tokens[$x]['code']]) === true) {
                            continue;
                        }
                        if (isset($allowed[$this->tokens[$x]['code']]) === true) {
                            ++$type_token_count;
                            continue;
                        }
                        // Union and intersection types can't use the nullable operator, but be tolerant to parse errors.
                        if ($type_token_count > 0 && $this->tokens[$x]['code'] === T_NULLABLE) {
                            continue;
                        }
                        if ($this->tokens[$x]['code'] === T_BITWISE_OR || $this->tokens[$x]['code'] === T_BITWISE_AND) {
                            $type_operators[] = $x;
                            continue;
                        }
                        if ($suspected_type === 'return' && $this->tokens[$x]['code'] === T_COLON) {
                            $confirmed = true;
                            break;
                        }
                        if ($suspected_type === 'property or parameter' && (isset(Util\Tokens::$scope_modifiers[$this->tokens[$x]['code']]) === true || $this->tokens[$x]['code'] === T_VAR || $this->tokens[$x]['code'] === T_READONLY)) {
                            // This will also confirm constructor property promotion parameters, but that's fine.
                            $confirmed = true;
                        }
                        break;
                    }
                    //end for
                    if ($confirmed === false && $suspected_type === 'property or parameter' && isset($this->tokens[$i]['nested_parenthesis']) === true) {
                        $parens = $this->tokens[$i]['nested_parenthesis'];
                        $last = end($parens);
                        if (isset($this->tokens[$last]['parenthesis_owner']) === true && $this->tokens[$this->tokens[$last]['parenthesis_owner']]['code'] === T_FUNCTION) {
                            $confirmed = true;
                        } else {
                            // No parenthesis owner set, this may be an arrow function which has not yet
                            // had additional processing done.
                            if (isset($this->tokens[$last]['parenthesis_opener']) === true) {
                                for ($x = $this->tokens[$last]['parenthesis_opener'] - 1; $x >= 0; $x--) {
                                    if (isset(Util\Tokens::$empty_tokens[$this->tokens[$x]['code']]) === true) {
                                        continue;
                                    }
                                    break;
                                }
                                if ($this->tokens[$x]['code'] === T_FN) {
                                    for (--$x; $x >= 0; $x--) {
                                        if (isset(Util\Tokens::$empty_tokens[$this->tokens[$x]['code']]) === true) {
                                            continue;
                                        }
                                        if ($this->tokens[$x]['code'] === T_BITWISE_AND) {
                                            continue;
                                        }
                                        break;
                                    }
                                    if ($this->tokens[$x]['code'] !== T_FUNCTION) {
                                        $confirmed = true;
                                    }
                                }
                            }
                            //end if
                        }
                        //end if
                        unset($parens, $last);
                    }
                    //end if
                    if ($confirmed === false) {
                        // Not a union or intersection type after all, move on.
                        continue;
                    }
                    foreach ($type_operators as $x) {
                        if ($this->tokens[$x]['code'] === T_BITWISE_OR) {
                            $this->tokens[$x]['code'] = T_TYPE_UNION;
                            $this->tokens[$x]['type'] = 'T_TYPE_UNION';
                            if (PHP_CODESNIFFER_VERBOSITY > 1) {
                                $line = $this->tokens[$x]['line'];
                                echo "\t* token {$x} on line {$line} changed from T_BITWISE_OR to T_TYPE_UNION" . PHP_EOL;
                            }
                        } else {
                            $this->tokens[$x]['code'] = T_TYPE_INTERSECTION;
                            $this->tokens[$x]['type'] = 'T_TYPE_INTERSECTION';
                            if (PHP_CODESNIFFER_VERBOSITY > 1) {
                                $line = $this->tokens[$x]['line'];
                                echo "\t* token {$x} on line {$line} changed from T_BITWISE_AND to T_TYPE_INTERSECTION" . PHP_EOL;
                            }
                        }
                    }
                    continue;
                }
                if ($this->tokens[$i]['code'] === T_STATIC) {
                    for ($x = $i - 1; $x > 0; $x--) {
                        if (isset(Util\Tokens::$empty_tokens[$this->tokens[$x]['code']]) === false) {
                            break;
                        }
                    }
                    if ($this->tokens[$x]['code'] === T_INSTANCEOF) {
                        $this->tokens[$i]['code'] = T_STRING;
                        $this->tokens[$i]['type'] = 'T_STRING';
                        if (PHP_CODESNIFFER_VERBOSITY > 1) {
                            $line = $this->tokens[$i]['line'];
                            echo "\t* token {$i} on line {$line} changed from T_STATIC to T_STRING" . PHP_EOL;
                        }
                    }
                    continue;
                }
                if ($this->tokens[$i]['code'] === T_TRUE || $this->tokens[$i]['code'] === T_FALSE || $this->tokens[$i]['code'] === T_NULL) {
                    for ($x = $i + 1; $i < $num_tokens; $x++) {
                        if (isset(Util\Tokens::$empty_tokens[$this->tokens[$x]['code']]) === false) {
                            // Non-whitespace content.
                            break;
                        }
                    }
                    if (isset($this->tstring_contexts[$this->tokens[$x]['code']]) === true) {
                        if (PHP_CODESNIFFER_VERBOSITY > 1) {
                            $line = $this->tokens[$i]['line'];
                            $type = $this->tokens[$i]['type'];
                            echo "\t* token {$i} on line {$line} changed from {$type} to T_STRING" . PHP_EOL;
                        }
                        $this->tokens[$i]['code'] = T_STRING;
                        $this->tokens[$i]['type'] = 'T_STRING';
                    }
                }
            }
            //end if
            if ($this->tokens[$i]['code'] !== T_CASE && $this->tokens[$i]['code'] !== T_DEFAULT) {
                // Only interested in CASE and DEFAULT statements from here on in.
                continue;
            }
            if (isset($this->tokens[$i]['scope_opener']) === false) {
                // Only interested in CASE and DEFAULT statements from here on in.
                continue;
            }
            $scope_opener = $this->tokens[$i]['scope_opener'];
            $scope_closer = $this->tokens[$i]['scope_closer'];
            // If the first char after the opener is a curly brace
            // and that brace has been ignored, it is actually
            // opening this case statement and the opener and closer are
            // probably set incorrectly.
            for ($x = $scope_opener + 1; $x < $num_tokens; $x++) {
                if (isset(Util\Tokens::$empty_tokens[$this->tokens[$x]['code']]) === false) {
                    // Non-whitespace content.
                    break;
                }
            }
            if ($this->tokens[$x]['code'] === T_CASE || $this->tokens[$x]['code'] === T_DEFAULT) {
                // Special case for multiple CASE statements that share the same
                // closer. Because we are going backwards through the file, this next
                // CASE statement is already fixed, so just use its closer and don't
                // worry about fixing anything.
                $new_closer = $this->tokens[$x]['scope_closer'];
                $this->tokens[$i]['scope_closer'] = $new_closer;
                if (PHP_CODESNIFFER_VERBOSITY > 1) {
                    $old_type = $this->tokens[$scope_closer]['type'];
                    $new_type = $this->tokens[$new_closer]['type'];
                    $line = $this->tokens[$i]['line'];
                    echo "\t* token {$i} (T_CASE) on line {$line} closer changed from {$scope_closer} ({$old_type}) to {$new_closer} ({$new_type})" . PHP_EOL;
                }
                continue;
            }
            if ($this->tokens[$x]['code'] !== T_OPEN_CURLY_BRACKET) {
                // Not a CASE/DEFAULT with a curly brace opener.
                continue;
            }
            if (isset($this->tokens[$x]['scope_condition']) === true) {
                // Not a CASE/DEFAULT with a curly brace opener.
                continue;
            }
            // The closer for this CASE/DEFAULT should be the closing curly brace and
            // not whatever it already is. The opener needs to be the opening curly
            // brace so everything matches up.
            $new_closer = $this->tokens[$x]['bracket_closer'];
            foreach ([$i, $x, $new_closer] as $index) {
                $this->tokens[$index]['scope_condition'] = $i;
                $this->tokens[$index]['scope_opener'] = $x;
                $this->tokens[$index]['scope_closer'] = $new_closer;
            }
            if (PHP_CODESNIFFER_VERBOSITY > 1) {
                $line = $this->tokens[$i]['line'];
                $token_type = $this->tokens[$i]['type'];
                $old_type = $this->tokens[$scope_opener]['type'];
                $new_type = $this->tokens[$x]['type'];
                echo "\t* token {$i} ({$token_type}) on line {$line} opener changed from {$scope_opener} ({$old_type}) to {$x} ({$new_type})" . PHP_EOL;
                $old_type = $this->tokens[$scope_closer]['type'];
                $new_type = $this->tokens[$new_closer]['type'];
                echo "\t* token {$i} ({$token_type}) on line {$line} closer changed from {$scope_closer} ({$old_type}) to {$new_closer} ({$new_type})" . PHP_EOL;
            }
            if ($this->tokens[$scope_opener]['scope_condition'] === $i) {
                unset($this->tokens[$scope_opener]['scope_condition']);
                unset($this->tokens[$scope_opener]['scope_opener']);
                unset($this->tokens[$scope_opener]['scope_closer']);
            }
            if ($this->tokens[$scope_closer]['scope_condition'] === $i) {
                unset($this->tokens[$scope_closer]['scope_condition']);
                unset($this->tokens[$scope_closer]['scope_opener']);
                unset($this->tokens[$scope_closer]['scope_closer']);
            } else {
                // We were using a shared closer. All tokens that were
                // sharing this closer with us, except for the scope condition
                // and it's opener, need to now point to the new closer.
                $condition = $this->tokens[$scope_closer]['scope_condition'];
                $start = $this->tokens[$condition]['scope_opener'] + 1;
                for ($y = $start; $y < $scope_closer; $y++) {
                    if (isset($this->tokens[$y]['scope_closer']) === true && $this->tokens[$y]['scope_closer'] === $scope_closer) {
                        $this->tokens[$y]['scope_closer'] = $new_closer;
                        if (PHP_CODESNIFFER_VERBOSITY > 1) {
                            $line = $this->tokens[$y]['line'];
                            $token_type = $this->tokens[$y]['type'];
                            $old_type = $this->tokens[$scope_closer]['type'];
                            $new_type = $this->tokens[$new_closer]['type'];
                            echo "\t\t* token {$y} ({$token_type}) on line {$line} closer changed from {$scope_closer} ({$old_type}) to {$new_closer} ({$new_type})" . PHP_EOL;
                        }
                    }
                }
            }
            //end if
            unset($this->tokens[$x]['bracket_opener']);
            unset($this->tokens[$x]['bracket_closer']);
            unset($this->tokens[$new_closer]['bracket_opener']);
            unset($this->tokens[$new_closer]['bracket_closer']);
            $this->tokens[$scope_closer]['conditions'][] = $i;
            // Now fix up all the tokens that think they are
            // inside the CASE/DEFAULT statement when they are really outside.
            for ($x = $new_closer; $x < $scope_closer; $x++) {
                foreach ($this->tokens[$x]['conditions'] as $num => $old_cond) {
                    if ($old_cond === $this->tokens[$i]['code']) {
                        $old_conditions = $this->tokens[$x]['conditions'];
                        unset($this->tokens[$x]['conditions'][$num]);
                        if (PHP_CODESNIFFER_VERBOSITY > 1) {
                            $type = $this->tokens[$x]['type'];
                            $old_conds = '';
                            foreach ($old_conditions as $condition) {
                                $old_conds .= Util\Tokens::token_name($condition) . ',';
                            }
                            $old_conds = rtrim($old_conds, ',');
                            $new_conds = '';
                            foreach ($this->tokens[$x]['conditions'] as $condition) {
                                $new_conds .= Util\Tokens::token_name($condition) . ',';
                            }
                            $new_conds = rtrim($new_conds, ',');
                            echo "\t\t* cleaned {$x} ({$type}) *" . PHP_EOL;
                            echo "\t\t\t=> conditions changed from {$old_conds} to {$new_conds}" . PHP_EOL;
                        }
                        break;
                    }
                    //end if
                }
                //end foreach
            }
            //end for
        }
        //end for
        if (PHP_CODESNIFFER_VERBOSITY > 1) {
            echo "\t*** END ADDITIONAL PHP PROCESSING ***" . PHP_EOL;
        }
    }
    //end processAdditional()
    /**
     * Takes a token produced from <code>token_get_all()</code> and produces a
     * more uniform token.
     *
     * @param string|array $token The token to convert.
     *
     * @return array The new token.
     */
    public static function standardise_token(array $token)
    {
        if (isset($token[1]) === false) {
            if (isset(self::$resolve_token_cache[$token[0]]) === true) {
                return self::$resolve_token_cache[$token[0]];
            }
        } else {
            $cache_key = null;
            if ($token[0] === T_STRING) {
                $cache_key = strtolower($token[1]);
            } elseif ($token[0] !== T_CURLY_OPEN) {
                $cache_key = $token[0];
            }
            if ($cache_key !== null && isset(self::$resolve_token_cache[$cache_key]) === true) {
                $new_token = self::$resolve_token_cache[$cache_key];
                $new_token['content'] = $token[1];
                return $new_token;
            }
        }
        if (isset($token[1]) === false) {
            return self::resolve_simple_token($token[0]);
        }
        if ($token[0] === T_STRING) {
            switch ($cache_key) {
                case 'false':
                    $new_token['type'] = 'T_FALSE';
                    break;
                case 'true':
                    $new_token['type'] = 'T_TRUE';
                    break;
                case 'null':
                    $new_token['type'] = 'T_NULL';
                    break;
                case 'self':
                    $new_token['type'] = 'T_SELF';
                    break;
                case 'parent':
                    $new_token['type'] = 'T_PARENT';
                    break;
                default:
                    $new_token['type'] = 'T_STRING';
                    break;
            }
            $new_token['code'] = constant($new_token['type']);
            self::$resolve_token_cache[$cache_key] = $new_token;
        } elseif ($token[0] === T_CURLY_OPEN) {
            $new_token = ['code' => T_OPEN_CURLY_BRACKET, 'type' => 'T_OPEN_CURLY_BRACKET'];
        } else {
            $new_token = ['code' => $token[0], 'type' => Util\Tokens::token_name($token[0])];
            self::$resolve_token_cache[$token[0]] = $new_token;
        }
        //end if
        $new_token['content'] = $token[1];
        return $new_token;
    }
    //end standardiseToken()
    /**
     * Converts simple tokens into a format that conforms to complex tokens
     * produced by token_get_all().
     *
     * Simple tokens are tokens that are not in array form when produced from
     * token_get_all().
     *
     * @param string $token The simple token to convert.
     *
     * @return array The new token in array format.
     */
    public static function resolve_simple_token($token)
    {
        $new_token = [];
        switch ($token) {
            case '{':
                $new_token['type'] = 'T_OPEN_CURLY_BRACKET';
                break;
            case '}':
                $new_token['type'] = 'T_CLOSE_CURLY_BRACKET';
                break;
            case '[':
                $new_token['type'] = 'T_OPEN_SQUARE_BRACKET';
                break;
            case ']':
                $new_token['type'] = 'T_CLOSE_SQUARE_BRACKET';
                break;
            case '(':
                $new_token['type'] = 'T_OPEN_PARENTHESIS';
                break;
            case ')':
                $new_token['type'] = 'T_CLOSE_PARENTHESIS';
                break;
            case ':':
                $new_token['type'] = 'T_COLON';
                break;
            case '.':
                $new_token['type'] = 'T_STRING_CONCAT';
                break;
            case ';':
                $new_token['type'] = 'T_SEMICOLON';
                break;
            case '=':
                $new_token['type'] = 'T_EQUAL';
                break;
            case '*':
                $new_token['type'] = 'T_MULTIPLY';
                break;
            case '/':
                $new_token['type'] = 'T_DIVIDE';
                break;
            case '+':
                $new_token['type'] = 'T_PLUS';
                break;
            case '-':
                $new_token['type'] = 'T_MINUS';
                break;
            case '%':
                $new_token['type'] = 'T_MODULUS';
                break;
            case '^':
                $new_token['type'] = 'T_BITWISE_XOR';
                break;
            case '&':
                $new_token['type'] = 'T_BITWISE_AND';
                break;
            case '|':
                $new_token['type'] = 'T_BITWISE_OR';
                break;
            case '~':
                $new_token['type'] = 'T_BITWISE_NOT';
                break;
            case '<':
                $new_token['type'] = 'T_LESS_THAN';
                break;
            case '>':
                $new_token['type'] = 'T_GREATER_THAN';
                break;
            case '!':
                $new_token['type'] = 'T_BOOLEAN_NOT';
                break;
            case ',':
                $new_token['type'] = 'T_COMMA';
                break;
            case '@':
                $new_token['type'] = 'T_ASPERAND';
                break;
            case '$':
                $new_token['type'] = 'T_DOLLAR';
                break;
            case '`':
                $new_token['type'] = 'T_BACKTICK';
                break;
            default:
                $new_token['type'] = 'T_NONE';
                break;
        }
        //end switch
        $new_token['code'] = constant($new_token['type']);
        $new_token['content'] = $token;
        self::$resolve_token_cache[$token] = $new_token;
        return $new_token;
    }
    //end resolveSimpleToken()
    /**
     * Finds a "closer" token (closing parenthesis or square bracket for example)
     * Handle parenthesis balancing while searching for closing token
     *
     * @param array           $tokens       The list of tokens to iterate searching the closing token (as returned by token_get_all)
     * @param int             $start        The starting position
     * @param string|string[] $openerTokens The opening character
     * @param string          $closerChar   The closing character
     *
     * @return int|null The position of the closing token, if found. NULL otherwise.
     */
    private function find_closer(array &$tokens, $start, $opener_tokens, $closer_char)
    {
        $num_tokens = count($tokens);
        $stack = [0];
        $closer = null;
        $opener_tokens = (array) $opener_tokens;
        for ($x = $start; $x < $num_tokens; $x++) {
            if (in_array($tokens[$x], $opener_tokens, true) === true || is_array($tokens[$x]) === true && in_array($tokens[$x][1], $opener_tokens, true) === true) {
                $stack[] = $x;
            } elseif ($tokens[$x] === $closer_char) {
                array_pop($stack);
                if (empty($stack) === true) {
                    $closer = $x;
                    break;
                }
            }
        }
        return $closer;
    }
    //end findCloser()
    /**
     * PHP 8 attributes parser for PHP < 8
     * Handles single-line and multiline attributes.
     *
     * @param array $tokens   The original array of tokens (as returned by token_get_all)
     * @param int   $stackPtr The current position in token array
     *
     * @return array|null The array of parsed attribute tokens
     */
    private function parse_php_attribute(array &$tokens, $stack_ptr)
    {
        $token = $tokens[$stack_ptr];
        $comment_body = substr($token[1], 2);
        $sub_tokens = @token_get_all('<?php ' . $comment_body);
        foreach ($sub_tokens as $i => $sub_token) {
            if (is_array($sub_token) === true && $sub_token[0] === T_COMMENT && strpos($sub_token[1], '#[') === 0) {
                $reparsed = $this->parse_php_attribute($sub_tokens, $i);
                if ($reparsed !== null) {
                    array_splice($sub_tokens, $i, 1, $reparsed);
                } else {
                    $sub_token[0] = T_ATTRIBUTE;
                }
            }
        }
        array_splice($sub_tokens, 0, 1, [[T_ATTRIBUTE, '#[']]);
        // Go looking for the close bracket.
        $bracket_closer = $this->find_closer($sub_tokens, 1, '[', ']');
        if (PHP_VERSION_ID < 80000 && $bracket_closer === null) {
            foreach (array_slice($tokens, $stack_ptr + 1) as $token) {
                if (is_array($token) === true) {
                    $comment_body .= $token[1];
                } else {
                    $comment_body .= $token;
                }
            }
            $sub_tokens = @token_get_all('<?php ' . $comment_body);
            array_splice($sub_tokens, 0, 1, [[T_ATTRIBUTE, '#[']]);
            $bracket_closer = $this->find_closer($sub_tokens, 1, '[', ']');
            if ($bracket_closer !== null) {
                array_splice($tokens, $stack_ptr + 1, count($tokens), array_slice($sub_tokens, $bracket_closer + 1));
                $sub_tokens = array_slice($sub_tokens, 0, $bracket_closer + 1);
            }
        }
        if ($bracket_closer === null) {
            return null;
        }
        return $sub_tokens;
    }
    //end parsePhpAttribute()
    /**
     * Creates a map for the attributes tokens that surround other tokens.
     *
     * @return void
     */
    private function create_attributes_nesting_map()
    {
        $map = [];
        for ($i = 0; $i < $this->num_tokens; $i++) {
            if (isset($this->tokens[$i]['attribute_opener']) === true && $i === $this->tokens[$i]['attribute_opener']) {
                if (empty($map) === false) {
                    $this->tokens[$i]['nested_attributes'] = $map;
                }
                if (isset($this->tokens[$i]['attribute_closer']) === true) {
                    $map[$this->tokens[$i]['attribute_opener']] = $this->tokens[$i]['attribute_closer'];
                }
            } elseif (isset($this->tokens[$i]['attribute_closer']) === true && $i === $this->tokens[$i]['attribute_closer']) {
                array_pop($map);
                if (empty($map) === false) {
                    $this->tokens[$i]['nested_attributes'] = $map;
                }
            } else if (empty($map) === false) {
                $this->tokens[$i]['nested_attributes'] = $map;
            }
            //end if
        }
        //end for
    }
    //end createAttributesNestingMap()
}
//end class