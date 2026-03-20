<?php

declare (strict_types=1);
/**
 * Processes pattern strings and checks that the code conforms to the pattern.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Sniffs;

use Php_code_Sniffer\Exceptions\RuntimeException;
use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Tokenizers\PHP;
use Php_code_Sniffer\Util\Tokens;
abstract class Abstract_Pattern_Sniff implements Sniff
{
    /**
     * If true, comments will be ignored if they are found in the code.
     *
     * @var boolean
     */
    public $ignore_comments = false;
    /**
     * The current file being checked.
     *
     * @var string
     */
    protected $curr_file = '';
    /**
     * The parsed patterns array.
     *
     * @var array
     */
    private $parsed_patterns = [];
    /**
     * Tokens that this sniff wishes to process outside of the patterns.
     *
     * @var int[]
     * @see registerSupplementary()
     * @see processSupplementary()
     */
    private $supplementary_tokens = [];
    /**
     * Positions in the stack where errors have occurred.
     *
     * @var array<int, bool>
     */
    private $error_pos = [];
    /**
     * Constructs a AbstractPatternSniff.
     *
     * @param boolean $ignoreComments If true, comments will be ignored.
     */
    public function __construct($ignore_comments = null)
    {
        // This is here for backwards compatibility.
        if ($ignore_comments !== null) {
            $this->ignore_comments = $ignore_comments;
        }
        $this->supplementary_tokens = $this->register_supplementary();
    }
    //end __construct()
    /**
     * Registers the tokens to listen to.
     *
     * Classes extending <i>AbstractPatternTest</i> should implement the
     * <i>getPatterns()</i> method to register the patterns they wish to test.
     *
     * @return int[]
     * @see    process()
     */
    final public function register()
    {
        $listen_types = [];
        $patterns = $this->get_patterns();
        foreach ($patterns as $pattern) {
            $parsed_pattern = $this->parse($pattern);
            // Find a token position in the pattern that we can use
            // for a listener token.
            $pos = $this->get_listener_token_pos($parsed_pattern);
            $token_type = $parsed_pattern[$pos]['token'];
            $listen_types[] = $token_type;
            $pattern_array = ['listen_pos' => $pos, 'pattern' => $parsed_pattern, 'pattern_code' => $pattern];
            if (isset($this->parsed_patterns[$token_type]) === false) {
                $this->parsed_patterns[$token_type] = [];
            }
            $this->parsed_patterns[$token_type][] = $pattern_array;
        }
        //end foreach
        return array_unique(array_merge($listen_types, $this->supplementary_tokens));
    }
    //end register()
    /**
     * Returns the token types that the specified pattern is checking for.
     *
     * Returned array is in the format:
     * <code>
     *   array(
     *      T_WHITESPACE => 0, // 0 is the position where the T_WHITESPACE token
     *                         // should occur in the pattern.
     *   );
     * </code>
     *
     * @param array $pattern The parsed pattern to find the acquire the token
     *                       types from.
     *
     * @return array<int, int>
     */
    private function get_pattern_token_types($pattern)
    {
        $token_types = [];
        foreach ($pattern as $pos => $pattern_info) {
            if ($pattern_info['type'] !== 'token') {
                continue;
            }
            if (isset($token_types[$pattern_info['token']]) !== false) {
                continue;
            }
            $token_types[$pattern_info['token']] = $pos;
        }
        return $token_types;
    }
    //end getPatternTokenTypes()
    /**
     * Returns the position in the pattern that this test should register as
     * a listener for the pattern.
     *
     * @param array $pattern The pattern to acquire the listener for.
     *
     * @return int The position in the pattern that this test should register
     *             as the listener.
     * @throws \PHP_CodeSniffer\Exceptions\RuntimeException If we could not determine a token to listen for.
     */
    private function get_listener_token_pos($pattern)
    {
        $token_types = $this->get_pattern_token_types($pattern);
        $token_codes = array_keys($token_types);
        $token = Tokens::get_highest_weighted_token($token_codes);
        // If we could not get a token.
        if ($token === false) {
            $error = 'Could not determine a token to listen for';
            throw new RuntimeException($error);
        }
        return $token_types[$token];
    }
    //end getListenerTokenPos()
    /**
     * Processes the test.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The PHP_CodeSniffer file where the
     *                                               token occurred.
     * @param int                         $stackPtr  The position in the tokens stack
     *                                               where the listening token type
     *                                               was found.
     *
     * @return void
     * @see    register()
     */
    final public function process(File $phpcs_file, $stack_ptr)
    {
        $file = $phpcs_file->get_filename();
        if ($this->curr_file !== $file) {
            // We have changed files, so clean up.
            $this->error_pos = [];
            $this->curr_file = $file;
        }
        $tokens = $phpcs_file->get_tokens();
        if (in_array($tokens[$stack_ptr]['code'], $this->supplementary_tokens, true) === true) {
            $this->process_supplementary($phpcs_file, $stack_ptr);
        }
        $type = $tokens[$stack_ptr]['code'];
        // If the type is not set, then it must have been a token registered
        // with registerSupplementary().
        if (isset($this->parsed_patterns[$type]) === false) {
            return;
        }
        $all_errors = [];
        // Loop over each pattern that is listening to the current token type
        // that we are processing.
        foreach ($this->parsed_patterns[$type] as $pattern_info) {
            // If processPattern returns false, then the pattern that we are
            // checking the code with must not be designed to check that code.
            $errors = $this->process_pattern($pattern_info, $phpcs_file, $stack_ptr);
            if ($errors === false) {
                // The pattern didn't match.
                continue;
            }
            if (empty($errors) === true) {
                // The pattern matched, but there were no errors.
                break;
            }
            foreach ($errors as $stack_ptr => $error) {
                if (isset($this->error_pos[$stack_ptr]) === false) {
                    $this->error_pos[$stack_ptr] = true;
                    $all_errors[$stack_ptr] = $error;
                }
            }
        }
        foreach ($all_errors as $stack_ptr => $error) {
            $phpcs_file->add_error($error, $stack_ptr, 'Found');
        }
    }
    //end process()
    /**
     * Processes the pattern and verifies the code at $stackPtr.
     *
     * @param array                       $patternInfo Information about the pattern used
     *                                                 for checking, which includes are
     *                                                 parsed token representation of the
     *                                                 pattern.
     * @param \PHP_CodeSniffer\Files\File $phpcsFile   The PHP_CodeSniffer file where the
     *                                                 token occurred.
     * @param int                         $stackPtr    The position in the tokens stack where
     *                                                 the listening token type was found.
     *
     * @return array
     */
    protected function process_pattern(array $pattern_info, File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        $pattern = $pattern_info['pattern'];
        $pattern_code = $pattern_info['pattern_code'];
        $errors = [];
        $found = '';
        $ignore_tokens = [T_WHITESPACE => T_WHITESPACE];
        if ($this->ignore_comments === true) {
            $ignore_tokens += Tokens::$comment_tokens;
        }
        $orig_stack_ptr = $stack_ptr;
        $has_error = false;
        if ($pattern_info['listen_pos'] > 0) {
            $stack_ptr--;
            for ($i = $pattern_info['listen_pos'] - 1; $i >= 0; $i--) {
                if ($pattern[$i]['type'] === 'token') {
                    if ($pattern[$i]['token'] === T_WHITESPACE) {
                        if ($tokens[$stack_ptr]['code'] === T_WHITESPACE) {
                            $found = $tokens[$stack_ptr]['content'] . $found;
                        }
                        // Only check the size of the whitespace if this is not
                        // the first token. We don't care about the size of
                        // leading whitespace, just that there is some.
                        if ($i !== 0) {
                            if ($tokens[$stack_ptr]['content'] !== $pattern[$i]['value']) {
                                $has_error = true;
                            }
                        }
                    } else {
                        // Check to see if this important token is the same as the
                        // previous important token in the pattern. If it is not,
                        // then the pattern cannot be for this piece of code.
                        $prev = $phpcs_file->find_previous($ignore_tokens, $stack_ptr, null, true);
                        if ($prev === false || $tokens[$prev]['code'] !== $pattern[$i]['token']) {
                            return false;
                        }
                        // If we skipped past some whitespace tokens, then add them
                        // to the found string.
                        $token_content = $phpcs_file->get_tokens_as_string($prev + 1, $stack_ptr - $prev - 1);
                        $found = $tokens[$prev]['content'] . $token_content . $found;
                        if (isset($pattern[$i - 1]) === true && $pattern[$i - 1]['type'] === 'skip') {
                            $stack_ptr = $prev;
                        } else {
                            $stack_ptr = $prev - 1;
                        }
                    }
                    //end if
                } elseif ($pattern[$i]['type'] === 'skip') {
                    // Skip to next piece of relevant code.
                    if ($pattern[$i]['to'] === 'parenthesis_closer') {
                        $to = 'parenthesis_opener';
                    } else {
                        $to = 'scope_opener';
                    }
                    // Find the previous opener.
                    $next = $phpcs_file->find_previous($ignore_tokens, $stack_ptr, null, true);
                    if ($next === false || isset($tokens[$next][$to]) === false) {
                        // If there was not opener, then we must be
                        // using the wrong pattern.
                        return false;
                    }
                    if ($to === 'parenthesis_opener') {
                        $found = '{' . $found;
                    } else {
                        $found = '(' . $found;
                    }
                    $found = '...' . $found;
                    // Skip to the opening token.
                    $stack_ptr = $tokens[$next][$to] - 1;
                } elseif ($pattern[$i]['type'] === 'string') {
                    $found = 'abc';
                } elseif ($pattern[$i]['type'] === 'newline') {
                    if ($this->ignore_comments === true && isset(Tokens::$comment_tokens[$tokens[$stack_ptr]['code']]) === true) {
                        $start_comment = $phpcs_file->find_previous(Tokens::$comment_tokens, $stack_ptr - 1, null, true);
                        if ($tokens[$start_comment]['line'] !== $tokens[$start_comment + 1]['line']) {
                            $start_comment++;
                        }
                        $token_content = $phpcs_file->get_tokens_as_string($start_comment, $stack_ptr - $start_comment + 1);
                        $found = $token_content . $found;
                        $stack_ptr = $start_comment - 1;
                    }
                    if ($tokens[$stack_ptr]['code'] === T_WHITESPACE) {
                        if ($tokens[$stack_ptr]['content'] !== $phpcs_file->eol_char) {
                            $found = $tokens[$stack_ptr]['content'] . $found;
                            // This may just be an indent that comes after a newline
                            // so check the token before to make sure. If it is a newline, we
                            // can ignore the error here.
                            if ($tokens[$stack_ptr - 1]['content'] !== $phpcs_file->eol_char && ($this->ignore_comments === true && isset(Tokens::$comment_tokens[$tokens[$stack_ptr - 1]['code']]) === false)) {
                                $has_error = true;
                            } else {
                                $stack_ptr--;
                            }
                        } else {
                            $found = 'EOL' . $found;
                        }
                    } else {
                        $found = $tokens[$stack_ptr]['content'] . $found;
                        $has_error = true;
                    }
                    //end if
                    if ($has_error === false && $pattern[$i - 1]['type'] !== 'newline') {
                        // Make sure they only have 1 newline.
                        $prev = $phpcs_file->find_previous($ignore_tokens, $stack_ptr - 1, null, true);
                        if ($prev !== false && $tokens[$prev]['line'] !== $tokens[$stack_ptr]['line']) {
                            $has_error = true;
                        }
                    }
                }
                //end if
            }
            //end for
        }
        //end if
        $stack_ptr = $orig_stack_ptr;
        $last_added_stack_ptr = null;
        $pattern_len = count($pattern);
        for ($i = $pattern_info['listen_pos']; $i < $pattern_len; $i++) {
            if (isset($tokens[$stack_ptr]) === false) {
                break;
            }
            if ($pattern[$i]['type'] === 'token') {
                if ($pattern[$i]['token'] === T_WHITESPACE) {
                    if ($this->ignore_comments === true) {
                        // If we are ignoring comments, check to see if this current
                        // token is a comment. If so skip it.
                        if (isset(Tokens::$comment_tokens[$tokens[$stack_ptr]['code']]) === true) {
                            continue;
                        }
                        // If the next token is a comment, the we need to skip the
                        // current token as we should allow a space before a
                        // comment for readability.
                        if (isset($tokens[$stack_ptr + 1]) === true && isset(Tokens::$comment_tokens[$tokens[$stack_ptr + 1]['code']]) === true) {
                            continue;
                        }
                    }
                    $token_content = '';
                    if ($tokens[$stack_ptr]['code'] === T_WHITESPACE) {
                        if (isset($pattern[$i + 1]) === false) {
                            // This is the last token in the pattern, so just compare
                            // the next token of content.
                            $token_content = $tokens[$stack_ptr]['content'];
                        } else {
                            // Get all the whitespace to the next token.
                            $next = $phpcs_file->find_next(Tokens::$empty_tokens, $stack_ptr, null, true);
                            $token_content = $phpcs_file->get_tokens_as_string($stack_ptr, $next - $stack_ptr);
                            $last_added_stack_ptr = $stack_ptr;
                            $stack_ptr = $next;
                        }
                        //end if
                        if ($stack_ptr !== $last_added_stack_ptr) {
                            $found .= $token_content;
                        }
                    } else if ($stack_ptr !== $last_added_stack_ptr) {
                        $found .= $tokens[$stack_ptr]['content'];
                        $last_added_stack_ptr = $stack_ptr;
                    }
                    //end if
                    if (isset($pattern[$i + 1]) === true && $pattern[$i + 1]['type'] === 'skip') {
                        // The next token is a skip token, so we just need to make
                        // sure the whitespace we found has *at least* the
                        // whitespace required.
                        if (strpos($token_content, $pattern[$i]['value']) !== 0) {
                            $has_error = true;
                        }
                    } else if ($token_content !== $pattern[$i]['value']) {
                        $has_error = true;
                    }
                } else {
                    // Check to see if this important token is the same as the
                    // next important token in the pattern. If it is not, then
                    // the pattern cannot be for this piece of code.
                    $next = $phpcs_file->find_next($ignore_tokens, $stack_ptr, null, true);
                    if ($next === false || $tokens[$next]['code'] !== $pattern[$i]['token']) {
                        // The next important token did not match the pattern.
                        return false;
                    }
                    if ($last_added_stack_ptr !== null) {
                        if (($tokens[$next]['code'] === T_OPEN_CURLY_BRACKET || $tokens[$next]['code'] === T_CLOSE_CURLY_BRACKET) && isset($tokens[$next]['scope_condition']) === true && $tokens[$next]['scope_condition'] > $last_added_stack_ptr) {
                            // This is a brace, but the owner of it is after the current
                            // token, which means it does not belong to any token in
                            // our pattern. This means the pattern is not for us.
                            return false;
                        }
                        if (($tokens[$next]['code'] === T_OPEN_PARENTHESIS || $tokens[$next]['code'] === T_CLOSE_PARENTHESIS) && isset($tokens[$next]['parenthesis_owner']) === true && $tokens[$next]['parenthesis_owner'] > $last_added_stack_ptr) {
                            // This is a bracket, but the owner of it is after the current
                            // token, which means it does not belong to any token in
                            // our pattern. This means the pattern is not for us.
                            return false;
                        }
                    }
                    //end if
                    // If we skipped past some whitespace tokens, then add them
                    // to the found string.
                    if ($next - $stack_ptr > 0) {
                        $has_comment = false;
                        for ($j = $stack_ptr; $j < $next; $j++) {
                            $found .= $tokens[$j]['content'];
                            if (isset(Tokens::$comment_tokens[$tokens[$j]['code']]) === true) {
                                $has_comment = true;
                            }
                        }
                        // If we are not ignoring comments, this additional
                        // whitespace or comment is not allowed. If we are
                        // ignoring comments, there needs to be at least one
                        // comment for this to be allowed.
                        if ($this->ignore_comments === false || $this->ignore_comments === true && $has_comment === false) {
                            $has_error = true;
                        }
                        // Even when ignoring comments, we are not allowed to include
                        // newlines without the pattern specifying them, so
                        // everything should be on the same line.
                        if ($tokens[$next]['line'] !== $tokens[$stack_ptr]['line']) {
                            $has_error = true;
                        }
                    }
                    //end if
                    if ($next !== $last_added_stack_ptr) {
                        $found .= $tokens[$next]['content'];
                        $last_added_stack_ptr = $next;
                    }
                    if (isset($pattern[$i + 1]) === true && $pattern[$i + 1]['type'] === 'skip') {
                        $stack_ptr = $next;
                    } else {
                        $stack_ptr = $next + 1;
                    }
                }
                //end if
            } elseif ($pattern[$i]['type'] === 'skip') {
                if ($pattern[$i]['to'] === 'unknown') {
                    $next = $phpcs_file->find_next($pattern[$i + 1]['token'], $stack_ptr);
                    if ($next === false) {
                        // Couldn't find the next token, so we must
                        // be using the wrong pattern.
                        return false;
                    }
                    $found .= '...';
                    $stack_ptr = $next;
                } else {
                    // Find the previous opener.
                    $next = $phpcs_file->find_previous(Tokens::$block_openers, $stack_ptr);
                    if ($next === false || isset($tokens[$next][$pattern[$i]['to']]) === false) {
                        // If there was not opener, then we must
                        // be using the wrong pattern.
                        return false;
                    }
                    $found .= '...';
                    if ($pattern[$i]['to'] === 'parenthesis_closer') {
                        $found .= ')';
                    } else {
                        $found .= '}';
                    }
                    // Skip to the closing token.
                    $stack_ptr = $tokens[$next][$pattern[$i]['to']] + 1;
                }
                //end if
            } elseif ($pattern[$i]['type'] === 'string') {
                if ($tokens[$stack_ptr]['code'] !== T_STRING) {
                    $has_error = true;
                }
                if ($stack_ptr !== $last_added_stack_ptr) {
                    $found .= 'abc';
                    $last_added_stack_ptr = $stack_ptr;
                }
                $stack_ptr++;
            } elseif ($pattern[$i]['type'] === 'newline') {
                // Find the next token that contains a newline character.
                $newline = 0;
                for ($j = $stack_ptr; $j < $phpcs_file->num_tokens; $j++) {
                    if (strpos($tokens[$j]['content'], $phpcs_file->eol_char) !== false) {
                        $newline = $j;
                        break;
                    }
                }
                if ($newline === 0) {
                    // We didn't find a newline character in the rest of the file.
                    $next = $phpcs_file->num_tokens - 1;
                    $has_error = true;
                } else {
                    if ($this->ignore_comments === false) {
                        // The newline character cannot be part of a comment.
                        if (isset(Tokens::$comment_tokens[$tokens[$newline]['code']]) === true) {
                            $has_error = true;
                        }
                    }
                    if ($newline === $stack_ptr) {
                        $next = $stack_ptr + 1;
                    } else {
                        // Check that there were no significant tokens that we
                        // skipped over to find our newline character.
                        $next = $phpcs_file->find_next($ignore_tokens, $stack_ptr, null, true);
                        if ($next < $newline) {
                            // We skipped a non-ignored token.
                            $has_error = true;
                        } else {
                            $next = $newline + 1;
                        }
                    }
                }
                //end if
                if ($stack_ptr !== $last_added_stack_ptr) {
                    $found .= $phpcs_file->get_tokens_as_string($stack_ptr, $next - $stack_ptr);
                    $last_added_stack_ptr = $next - 1;
                }
                $stack_ptr = $next;
            }
            //end if
        }
        //end for
        if ($has_error === true) {
            $error = $this->prepare_error($found, $pattern_code);
            $errors[$orig_stack_ptr] = $error;
        }
        return $errors;
    }
    //end processPattern()
    /**
     * Prepares an error for the specified patternCode.
     *
     * @param string $found       The actual found string in the code.
     * @param string $patternCode The expected pattern code.
     *
     * @return string The error message.
     */
    protected function prepare_error($found, $pattern_code)
    {
        $found = str_replace("\r\n", '\n', $found);
        $found = str_replace("\n", '\n', $found);
        $found = str_replace("\r", '\n', $found);
        $found = str_replace("\t", '\t', $found);
        $found = str_replace('EOL', '\n', $found);
        $expected = str_replace('EOL', '\n', $pattern_code);
        return "Expected \"{$expected}\"; found \"{$found}\"";
    }
    //end prepareError()
    /**
     * Returns the patterns that should be checked.
     *
     * @return string[]
     */
    abstract protected function get_patterns();
    /**
     * Registers any supplementary tokens that this test might wish to process.
     *
     * A sniff may wish to register supplementary tests when it wishes to group
     * an arbitrary validation that cannot be performed using a pattern, with
     * other pattern tests.
     *
     * @return int[]
     * @see    processSupplementary()
     */
    protected function register_supplementary()
    {
        return [];
    }
    //end registerSupplementary()
    /**
     * Processes any tokens registered with registerSupplementary().
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The PHP_CodeSniffer file where to
     *                                               process the skip.
     * @param int                         $stackPtr  The position in the tokens stack to
     *                                               process.
     *
     * @return void
     * @see    registerSupplementary()
     */
    protected function process_supplementary(File $phpcs_file, $stack_ptr)
    {
    }
    //end processSupplementary()
    /**
     * Parses a pattern string into an array of pattern steps.
     *
     * @param string $pattern The pattern to parse.
     *
     * @return array The parsed pattern array.
     * @see    createSkipPattern()
     * @see    createTokenPattern()
     */
    private function parse($pattern)
    {
        $patterns = [];
        $length = strlen($pattern);
        $last_token = 0;
        $first_token = 0;
        for ($i = 0; $i < $length; $i++) {
            $special_pattern = false;
            $is_last_char = $i === $length - 1;
            $old_first_token = $first_token;
            if (substr($pattern, $i, 3) === '...') {
                // It's a skip pattern. The skip pattern requires the
                // content of the token in the "from" position and the token
                // to skip to.
                $special_pattern = $this->create_skip_pattern($pattern, $i - 1);
                $last_token = $i - $first_token;
                $first_token = $i + 3;
                $i += 2;
                if ($special_pattern['to'] !== 'unknown') {
                    $first_token++;
                }
            } elseif (substr($pattern, $i, 3) === 'abc') {
                $special_pattern = ['type' => 'string'];
                $last_token = $i - $first_token;
                $first_token = $i + 3;
                $i += 2;
            } elseif (substr($pattern, $i, 3) === 'EOL') {
                $special_pattern = ['type' => 'newline'];
                $last_token = $i - $first_token;
                $first_token = $i + 3;
                $i += 2;
            }
            //end if
            if ($special_pattern !== false || $is_last_char === true) {
                // If we are at the end of the string, don't worry about a limit.
                if ($is_last_char === true) {
                    // Get the string from the end of the last skip pattern, if any,
                    // to the end of the pattern string.
                    $str = substr($pattern, $old_first_token);
                } else if ($last_token === 0) {
                    // Note that if the last special token was zero characters ago,
                    // there will be nothing to process so we can skip this bit.
                    // This happens if you have something like: EOL... in your pattern.
                    $str = '';
                } else {
                    $str = substr($pattern, $old_first_token, $last_token);
                }
                if ($str !== '') {
                    $token_patterns = $this->create_token_pattern($str);
                    foreach ($token_patterns as $token_pattern) {
                        $patterns[] = $token_pattern;
                    }
                }
                // Make sure we don't skip the last token.
                if ($is_last_char === false && $i === $length - 1) {
                    $i--;
                }
            }
            //end if
            // Add the skip pattern *after* we have processed
            // all the tokens from the end of the last skip pattern
            // to the start of this skip pattern.
            if ($special_pattern !== false) {
                $patterns[] = $special_pattern;
            }
        }
        //end for
        return $patterns;
    }
    //end parse()
    /**
     * Creates a skip pattern.
     *
     * @param string $pattern The pattern being parsed.
     * @param string $from    The token content that the skip pattern starts from.
     *
     * @return array The pattern step.
     * @see    createTokenPattern()
     * @see    parse()
     */
    private function create_skip_pattern($pattern, $from)
    {
        $skip = ['type' => 'skip'];
        $nested_parenthesis = 0;
        $nested_braces = 0;
        for ($start = $from; $start >= 0; $start--) {
            switch ($pattern[$start]) {
                case '(':
                    if ($nested_parenthesis === 0) {
                        $skip['to'] = 'parenthesis_closer';
                    }
                    $nested_parenthesis--;
                    break;
                case '{':
                    if ($nested_braces === 0) {
                        $skip['to'] = 'scope_closer';
                    }
                    $nested_braces--;
                    break;
                case '}':
                    $nested_braces++;
                    break;
                case ')':
                    $nested_parenthesis++;
                    break;
            }
            //end switch
            if (isset($skip['to']) === true) {
                break;
            }
        }
        //end for
        if (isset($skip['to']) === false) {
            $skip['to'] = 'unknown';
        }
        return $skip;
    }
    //end createSkipPattern()
    /**
     * Creates a token pattern.
     *
     * @param string $str The tokens string that the pattern should match.
     *
     * @return array The pattern step.
     * @see    createSkipPattern()
     * @see    parse()
     */
    private function create_token_pattern($str)
    {
        // Don't add a space after the closing php tag as it will add a new
        // whitespace token.
        $tokenizer = new PHP('<?php ' . $str . '?>', null);
        // Remove the <?php tag from the front and the end php tag from the back.
        $tokens = $tokenizer->get_tokens();
        $tokens = array_slice($tokens, 1, count($tokens) - 2);
        $patterns = [];
        foreach ($tokens as $pattern_info) {
            $patterns[] = ['type' => 'token', 'token' => $pattern_info['code'], 'value' => $pattern_info['content']];
        }
        return $patterns;
    }
    //end createTokenPattern()
}
//end class