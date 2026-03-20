<?php

declare (strict_types=1);
/**
 * Tokenizes CSS code.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Tokenizers;

use Php_code_Sniffer\Config;
use Php_code_Sniffer\Exceptions\Tokenizer_Exception;
use Php_code_Sniffer\Util;
class CSS extends PHP
{
    /**
     * Initialise the tokenizer.
     *
     * Pre-checks the content to see if it looks minified.
     *
     * @param string                  $content The content to tokenize,
     * @param \PHP_CodeSniffer\Config $config  The config data for the run.
     * @param string                  $eolChar The EOL char used in the content.
     *
     * @throws \PHP_CodeSniffer\Exceptions\TokenizerException If the file appears to be minified.
     */
    public function __construct($content, Config $config, $eol_char = '\n')
    {
        if ($this->is_minified_content($content, $eol_char) === true) {
            throw new Tokenizer_Exception('File appears to be minified and cannot be processed');
        }
        parent::__construct($content, $config, $eol_char);
    }
    //end __construct()
    /**
     * Creates an array of tokens when given some CSS code.
     *
     * Uses the PHP tokenizer to do all the tricky work
     *
     * @param string $string The string to tokenize.
     *
     * @return array
     */
    public function tokenize($string)
    {
        if (PHP_CODESNIFFER_VERBOSITY > 1) {
            echo "\t*** START CSS TOKENIZING 1ST PASS ***" . PHP_EOL;
        }
        // If the content doesn't have an EOL char on the end, add one so
        // the open and close tags we add are parsed correctly.
        $eol_added = false;
        if (substr($string, strlen($this->eol_char) * -1) !== $this->eol_char) {
            $string .= $this->eol_char;
            $eol_added = true;
        }
        $string = str_replace('<?php', '^PHPCS_CSS_T_OPEN_TAG^', $string);
        $string = str_replace('?>', '^PHPCS_CSS_T_CLOSE_TAG^', $string);
        $tokens = parent::tokenize('<?php ' . $string . '?>');
        $final_tokens = [];
        $final_tokens[0] = ['code' => T_OPEN_TAG, 'type' => 'T_OPEN_TAG', 'content' => ''];
        $new_stack_ptr = 1;
        $num_tokens = count($tokens);
        $multi_line_comment = false;
        for ($stack_ptr = 1; $stack_ptr < $num_tokens; $stack_ptr++) {
            $token = $tokens[$stack_ptr];
            // CSS files don't have lists, breaks etc, so convert these to
            // standard strings early so they can be converted into T_STYLE
            // tokens and joined with other strings if needed.
            if ($token['code'] === T_BREAK || $token['code'] === T_LIST || $token['code'] === T_DEFAULT || $token['code'] === T_SWITCH || $token['code'] === T_FOR || $token['code'] === T_FOREACH || $token['code'] === T_WHILE || $token['code'] === T_DEC || $token['code'] === T_NEW) {
                $token['type'] = 'T_STRING';
                $token['code'] = T_STRING;
            }
            $token['content'] = str_replace('^PHPCS_CSS_T_OPEN_TAG^', '<?php', $token['content']);
            $token['content'] = str_replace('^PHPCS_CSS_T_CLOSE_TAG^', '?>', $token['content']);
            if (PHP_CODESNIFFER_VERBOSITY > 1) {
                $type = $token['type'];
                $content = Util\Common::prepare_for_output($token['content']);
                echo "\tProcess token {$stack_ptr}: {$type} => {$content}" . PHP_EOL;
            }
            if ($token['code'] === T_BITWISE_XOR && $tokens[$stack_ptr + 1]['content'] === 'PHPCS_CSS_T_OPEN_TAG') {
                $content = '<?php';
                for ($stack_ptr += 3; $stack_ptr < $num_tokens; $stack_ptr++) {
                    if ($tokens[$stack_ptr]['code'] === T_BITWISE_XOR && $tokens[$stack_ptr + 1]['content'] === 'PHPCS_CSS_T_CLOSE_TAG') {
                        // Add the end tag and ignore the * we put at the end.
                        $content .= '?>';
                        $stack_ptr += 2;
                        break;
                    } else {
                        $content .= $tokens[$stack_ptr]['content'];
                    }
                }
                if (PHP_CODESNIFFER_VERBOSITY > 1) {
                    echo "\t\t=> Found embedded PHP code: ";
                    $clean_content = Util\Common::prepare_for_output($content);
                    echo $clean_content . PHP_EOL;
                }
                $final_tokens[$new_stack_ptr] = ['type' => 'T_EMBEDDED_PHP', 'code' => T_EMBEDDED_PHP, 'content' => $content];
                $new_stack_ptr++;
                continue;
            }
            //end if
            if ($token['code'] === T_GOTO_LABEL) {
                // Convert these back to T_STRING followed by T_COLON so we can
                // more easily process style definitions.
                $final_tokens[$new_stack_ptr] = ['type' => 'T_STRING', 'code' => T_STRING, 'content' => substr($token['content'], 0, -1)];
                $new_stack_ptr++;
                $final_tokens[$new_stack_ptr] = ['type' => 'T_COLON', 'code' => T_COLON, 'content' => ':'];
                $new_stack_ptr++;
                continue;
            }
            if ($token['code'] === T_FUNCTION) {
                // There are no functions in CSS, so convert this to a string.
                $final_tokens[$new_stack_ptr] = ['type' => 'T_STRING', 'code' => T_STRING, 'content' => $token['content']];
                $new_stack_ptr++;
                continue;
            }
            if ($token['code'] === T_COMMENT && substr($token['content'], 0, 2) === '/*') {
                // Multi-line comment. Record it so we can ignore other
                // comment tags until we get out of this one.
                $multi_line_comment = true;
            }
            if ($token['code'] === T_COMMENT && $multi_line_comment === false && (substr($token['content'], 0, 2) === '//' || $token['content'][0] === '#')) {
                $content = ltrim($token['content'], '#/');
                // Guard against PHP7+ syntax errors by stripping
                // leading zeros so the content doesn't look like an invalid int.
                $leading_zero = false;
                if ($content[0] === '0') {
                    $content = '1' . $content;
                    $leading_zero = true;
                }
                $comment_tokens = parent::tokenize('<?php ' . $content . '?>');
                // The first and last tokens are the open/close tags.
                array_shift($comment_tokens);
                $close_tag = array_pop($comment_tokens);
                while ($close_tag['content'] !== '?' . '>') {
                    $close_tag = array_pop($comment_tokens);
                }
                if ($leading_zero === true) {
                    $comment_tokens[0]['content'] = substr($comment_tokens[0]['content'], 1);
                    $content = substr($content, 1);
                }
                if ($token['content'][0] === '#') {
                    // The # character is not a comment in CSS files, so
                    // determine what it means in this context.
                    $first_content = $comment_tokens[0]['content'];
                    // If the first content is just a number, it is probably a
                    // colour like 8FB7DB, which PHP splits into 8 and FB7DB.
                    if (($comment_tokens[0]['code'] === T_LNUMBER || $comment_tokens[0]['code'] === T_DNUMBER) && $comment_tokens[1]['code'] === T_STRING) {
                        $first_content .= $comment_tokens[1]['content'];
                        array_shift($comment_tokens);
                    }
                    // If the first content looks like a colour and not a class
                    // definition, join the tokens together.
                    if (preg_match('/^[ABCDEF0-9]+$/i', $first_content) === 1 && $comment_tokens[1]['content'] !== '-') {
                        array_shift($comment_tokens);
                        // Work out what we trimmed off above and remember to re-add it.
                        $trimmed = substr($token['content'], 0, strlen($token['content']) - strlen($content));
                        $final_tokens[$new_stack_ptr] = ['type' => 'T_COLOUR', 'code' => T_COLOUR, 'content' => $trimmed . $first_content];
                    } else {
                        $final_tokens[$new_stack_ptr] = ['type' => 'T_HASH', 'code' => T_HASH, 'content' => '#'];
                    }
                } else {
                    $final_tokens[$new_stack_ptr] = ['type' => 'T_STRING', 'code' => T_STRING, 'content' => '//'];
                }
                //end if
                $new_stack_ptr++;
                array_splice($tokens, $stack_ptr, 1, $comment_tokens);
                $num_tokens = count($tokens);
                $stack_ptr--;
                continue;
            }
            //end if
            if ($token['code'] === T_COMMENT && substr($token['content'], -2) === '*/') {
                // Multi-line comment is done.
                $multi_line_comment = false;
            }
            $final_tokens[$new_stack_ptr] = $token;
            $new_stack_ptr++;
        }
        //end for
        if (PHP_CODESNIFFER_VERBOSITY > 1) {
            echo "\t*** END CSS TOKENIZING 1ST PASS ***" . PHP_EOL;
            echo "\t*** START CSS TOKENIZING 2ND PASS ***" . PHP_EOL;
        }
        // A flag to indicate if we are inside a style definition,
        // which is defined using curly braces.
        $in_style_def = false;
        // A flag to indicate if an At-rule like "@media" is used, which will result
        // in nested curly brackets.
        $asperand_start = false;
        $num_tokens = count($final_tokens);
        for ($stack_ptr = 0; $stack_ptr < $num_tokens; $stack_ptr++) {
            $token = $final_tokens[$stack_ptr];
            if (PHP_CODESNIFFER_VERBOSITY > 1) {
                $type = $token['type'];
                $content = Util\Common::prepare_for_output($token['content']);
                echo "\tProcess token {$stack_ptr}: {$type} => {$content}" . PHP_EOL;
            }
            switch ($token['code']) {
                case T_OPEN_CURLY_BRACKET:
                    // Opening curly brackets for an At-rule do not start a style
                    // definition. We also reset the asperand flag here because the next
                    // opening curly bracket could be indeed the start of a style
                    // definition.
                    if ($asperand_start === true) {
                        if (PHP_CODESNIFFER_VERBOSITY > 1) {
                            if ($in_style_def === true) {
                                echo "\t\t* style definition closed *" . PHP_EOL;
                            }
                            echo "\t\t* at-rule definition closed *" . PHP_EOL;
                        }
                        $in_style_def = false;
                        $asperand_start = false;
                    } else {
                        $in_style_def = true;
                        if (PHP_CODESNIFFER_VERBOSITY > 1) {
                            echo "\t\t* style definition opened *" . PHP_EOL;
                        }
                    }
                    break;
                case T_CLOSE_CURLY_BRACKET:
                    if (PHP_CODESNIFFER_VERBOSITY > 1) {
                        if ($in_style_def === true) {
                            echo "\t\t* style definition closed *" . PHP_EOL;
                        }
                        if ($asperand_start === true) {
                            echo "\t\t* at-rule definition closed *" . PHP_EOL;
                        }
                    }
                    $in_style_def = false;
                    $asperand_start = false;
                    break;
                case T_MINUS:
                    // Minus signs are often used instead of spaces inside
                    // class names, IDs and styles.
                    if ($final_tokens[$stack_ptr + 1]['code'] === T_STRING) {
                        if ($final_tokens[$stack_ptr - 1]['code'] === T_STRING) {
                            $new_content = $final_tokens[$stack_ptr - 1]['content'] . '-' . $final_tokens[$stack_ptr + 1]['content'];
                            if (PHP_CODESNIFFER_VERBOSITY > 1) {
                                echo "\t\t* token is a string joiner; ignoring this and previous token" . PHP_EOL;
                                $old = Util\Common::prepare_for_output($final_tokens[$stack_ptr + 1]['content']);
                                $new = Util\Common::prepare_for_output($new_content);
                                echo "\t\t=> token " . ($stack_ptr + 1) . " content changed from \"{$old}\" to \"{$new}\"" . PHP_EOL;
                            }
                            $final_tokens[$stack_ptr + 1]['content'] = $new_content;
                            unset($final_tokens[$stack_ptr]);
                            unset($final_tokens[$stack_ptr - 1]);
                        } else {
                            $new_content = '-' . $final_tokens[$stack_ptr + 1]['content'];
                            $final_tokens[$stack_ptr + 1]['content'] = $new_content;
                            unset($final_tokens[$stack_ptr]);
                        }
                    } elseif ($final_tokens[$stack_ptr + 1]['code'] === T_LNUMBER) {
                        // They can also be used to provide negative numbers.
                        if (PHP_CODESNIFFER_VERBOSITY > 1) {
                            echo "\t\t* token is part of a negative number; adding content to next token and ignoring *" . PHP_EOL;
                            $content = Util\Common::prepare_for_output($final_tokens[$stack_ptr + 1]['content']);
                            echo "\t\t=> token " . ($stack_ptr + 1) . " content changed from \"{$content}\" to \"-{$content}\"" . PHP_EOL;
                        }
                        $final_tokens[$stack_ptr + 1]['content'] = '-' . $final_tokens[$stack_ptr + 1]['content'];
                        unset($final_tokens[$stack_ptr]);
                    }
                    //end if
                    break;
                case T_COLON:
                    // Only interested in colons that are defining styles.
                    if ($in_style_def === false) {
                        break;
                    }
                    for ($x = $stack_ptr - 1; $x >= 0; $x--) {
                        if (isset(Util\Tokens::$empty_tokens[$final_tokens[$x]['code']]) === false) {
                            break;
                        }
                    }
                    if (PHP_CODESNIFFER_VERBOSITY > 1) {
                        $type = $final_tokens[$x]['type'];
                        echo "\t\t=> token {$x} changed from {$type} to T_STYLE" . PHP_EOL;
                    }
                    $final_tokens[$x]['type'] = 'T_STYLE';
                    $final_tokens[$x]['code'] = T_STYLE;
                    break;
                case T_STRING:
                    if (strtolower($token['content']) === 'url') {
                        // Find the next content.
                        for ($x = $stack_ptr + 1; $x < $num_tokens; $x++) {
                            if (isset(Util\Tokens::$empty_tokens[$final_tokens[$x]['code']]) === false) {
                                break;
                            }
                        }
                        // Needs to be in the format "url(" for it to be a URL.
                        if ($final_tokens[$x]['code'] !== T_OPEN_PARENTHESIS) {
                            continue 2;
                        }
                        // Make sure the content isn't empty.
                        for ($y = $x + 1; $y < $num_tokens; $y++) {
                            if (isset(Util\Tokens::$empty_tokens[$final_tokens[$y]['code']]) === false) {
                                break;
                            }
                        }
                        if ($final_tokens[$y]['code'] === T_CLOSE_PARENTHESIS) {
                            continue 2;
                        }
                        if (PHP_CODESNIFFER_VERBOSITY > 1) {
                            for ($i = $stack_ptr + 1; $i <= $y; $i++) {
                                $type = $final_tokens[$i]['type'];
                                $content = Util\Common::prepare_for_output($final_tokens[$i]['content']);
                                echo "\tProcess token {$i}: {$type} => {$content}" . PHP_EOL;
                            }
                            echo "\t\t* token starts a URL *" . PHP_EOL;
                        }
                        // Join all the content together inside the url() statement.
                        $new_content = '';
                        for ($i = $x + 2; $i < $num_tokens; $i++) {
                            if ($final_tokens[$i]['code'] === T_CLOSE_PARENTHESIS) {
                                break;
                            }
                            $new_content .= $final_tokens[$i]['content'];
                            if (PHP_CODESNIFFER_VERBOSITY > 1) {
                                $content = Util\Common::prepare_for_output($final_tokens[$i]['content']);
                                echo "\t\t=> token {$i} added to URL string and ignored: {$content}" . PHP_EOL;
                            }
                            unset($final_tokens[$i]);
                        }
                        $stack_ptr = $i;
                        // If the content inside the "url()" is in double quotes
                        // there will only be one token and so we don't have to do
                        // anything except change its type. If it is not empty,
                        // we need to do some token merging.
                        $final_tokens[$x + 1]['type'] = 'T_URL';
                        $final_tokens[$x + 1]['code'] = T_URL;
                        if ($new_content !== '') {
                            $final_tokens[$x + 1]['content'] .= $new_content;
                            if (PHP_CODESNIFFER_VERBOSITY > 1) {
                                $content = Util\Common::prepare_for_output($final_tokens[$x + 1]['content']);
                                echo "\t\t=> token content changed to: {$content}" . PHP_EOL;
                            }
                        }
                    } elseif ($final_tokens[$stack_ptr]['content'][0] === '-' && $final_tokens[$stack_ptr + 1]['code'] === T_STRING) {
                        if (isset($final_tokens[$stack_ptr - 1]) === true && $final_tokens[$stack_ptr - 1]['code'] === T_STRING) {
                            $new_content = $final_tokens[$stack_ptr - 1]['content'] . $final_tokens[$stack_ptr]['content'] . $final_tokens[$stack_ptr + 1]['content'];
                            if (PHP_CODESNIFFER_VERBOSITY > 1) {
                                echo "\t\t* token is a string joiner; ignoring this and previous token" . PHP_EOL;
                                $old = Util\Common::prepare_for_output($final_tokens[$stack_ptr + 1]['content']);
                                $new = Util\Common::prepare_for_output($new_content);
                                echo "\t\t=> token " . ($stack_ptr + 1) . " content changed from \"{$old}\" to \"{$new}\"" . PHP_EOL;
                            }
                            $final_tokens[$stack_ptr + 1]['content'] = $new_content;
                            unset($final_tokens[$stack_ptr]);
                            unset($final_tokens[$stack_ptr - 1]);
                        } else {
                            $new_content = $final_tokens[$stack_ptr]['content'] . $final_tokens[$stack_ptr + 1]['content'];
                            $final_tokens[$stack_ptr + 1]['content'] = $new_content;
                            unset($final_tokens[$stack_ptr]);
                        }
                    }
                    //end if
                    break;
                case T_ASPERAND:
                    $asperand_start = true;
                    if (PHP_CODESNIFFER_VERBOSITY > 1) {
                        echo "\t\t* at-rule definition opened *" . PHP_EOL;
                    }
                    break;
                default:
                    // Nothing special to be done with this token.
                    break;
            }
            //end switch
        }
        //end for
        // Reset the array keys to avoid gaps.
        $final_tokens = array_values($final_tokens);
        $num_tokens = count($final_tokens);
        // Blank out the content of the end tag.
        $final_tokens[$num_tokens - 1]['content'] = '';
        if ($eol_added === true) {
            // Strip off the extra EOL char we added for tokenizing.
            $final_tokens[$num_tokens - 2]['content'] = substr($final_tokens[$num_tokens - 2]['content'], 0, strlen($this->eol_char) * -1);
            if ($final_tokens[$num_tokens - 2]['content'] === '') {
                unset($final_tokens[$num_tokens - 2]);
                $final_tokens = array_values($final_tokens);
                $num_tokens = count($final_tokens);
            }
        }
        if (PHP_CODESNIFFER_VERBOSITY > 1) {
            echo "\t*** END CSS TOKENIZING 2ND PASS ***" . PHP_EOL;
        }
        return $final_tokens;
    }
    //end tokenize()
    /**
     * Performs additional processing after main tokenizing.
     *
     * @return void
     */
    public function process_additional()
    {
        /*
            We override this method because we don't want the PHP version to
            run during CSS processing because it is wasted processing time.
        */
    }
    //end processAdditional()
}
//end class