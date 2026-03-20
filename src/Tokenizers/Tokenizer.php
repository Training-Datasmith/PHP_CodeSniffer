<?php

declare (strict_types=1);
/**
 * The base tokenizer class.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Tokenizers;

use Php_code_Sniffer\Exceptions\Tokenizer_Exception;
use Php_code_Sniffer\Util;
abstract class Tokenizer
{
    /**
     * The config data for the run.
     *
     * @var \PHP_CodeSniffer\Config
     */
    protected $config;
    /**
     * The EOL char used in the content.
     *
     * @var string
     */
    protected $eol_char = [];
    /**
     * A token-based representation of the content.
     *
     * @var array
     */
    protected $tokens = [];
    /**
     * The number of tokens in the tokens array.
     *
     * @var integer
     */
    protected $num_tokens = 0;
    /**
     * A list of tokens that are allowed to open a scope.
     *
     * @var array
     */
    public $scope_openers = [];
    /**
     * A list of tokens that end the scope.
     *
     * @var array
     */
    public $end_scope_tokens = [];
    /**
     * Known lengths of tokens.
     *
     * @var array<int, int>
     */
    public $known_lengths = [];
    /**
     * A list of lines being ignored due to error suppression comments.
     *
     * @var array
     */
    public $ignored_lines = [];
    /**
     * Initialise and run the tokenizer.
     *
     * @param string                         $content The content to tokenize,
     * @param \PHP_CodeSniffer\Config | null $config  The config data for the run.
     * @param string                         $eolChar The EOL char used in the content.
     *
     * @throws \PHP_CodeSniffer\Exceptions\TokenizerException If the file appears to be minified.
     */
    public function __construct($content, $config, $eol_char = '\n')
    {
        $this->eol_char = $eol_char;
        $this->config = $config;
        $this->tokens = $this->tokenize($content);
        if ($config === null) {
            return;
        }
        $this->create_position_map();
        $this->create_token_map();
        $this->create_parenthesis_nesting_map();
        $this->create_scope_map();
        $this->create_level_map();
        // Allow the tokenizer to do additional processing if required.
        $this->process_additional();
    }
    //end __construct()
    /**
     * Checks the content to see if it looks minified.
     *
     * @param string $content The content to tokenize.
     * @param string $eolChar The EOL char used in the content.
     *
     * @return boolean
     */
    protected function is_minified_content($content, $eol_char = '\n')
    {
        // Minified files often have a very large number of characters per line
        // and cause issues when tokenizing.
        $num_chars = strlen($content);
        $num_lines = substr_count($content, $eol_char) + 1;
        $average = $num_chars / $num_lines;
        if ($average > 100) {
            return true;
        }
        return false;
    }
    //end isMinifiedContent()
    /**
     * Gets the array of tokens.
     *
     * @return array
     */
    public function get_tokens()
    {
        return $this->tokens;
    }
    //end getTokens()
    /**
     * Creates an array of tokens when given some content.
     *
     * @param string $string The string to tokenize.
     *
     * @return array
     */
    abstract protected function tokenize($string);
    /**
     * Performs additional processing after main tokenizing.
     *
     * @return void
     */
    abstract protected function process_additional();
    /**
     * Sets token position information.
     *
     * Can also convert tabs into spaces. Each tab can represent between
     * 1 and $width spaces, so this cannot be a straight string replace.
     *
     * @return void
     */
    private function create_position_map()
    {
        $curr_column = 1;
        $line_number = 1;
        $eol_len = strlen($this->eol_char);
        $ignoring = null;
        $in_tests = defined('PHP_CODESNIFFER_IN_TESTS');
        $check_encoding = false;
        if (function_exists('iconv_strlen') === true) {
            $check_encoding = true;
        }
        $check_annotations = $this->config->annotations;
        $encoding = $this->config->encoding;
        $tab_width = $this->config->tab_width;
        $tokens_with_tabs = [T_WHITESPACE => true, T_COMMENT => true, T_DOC_COMMENT => true, T_DOC_COMMENT_WHITESPACE => true, T_DOC_COMMENT_STRING => true, T_CONSTANT_ENCAPSED_STRING => true, T_DOUBLE_QUOTED_STRING => true, T_HEREDOC => true, T_NOWDOC => true, T_END_HEREDOC => true, T_END_NOWDOC => true, T_INLINE_HTML => true];
        $this->num_tokens = count($this->tokens);
        for ($i = 0; $i < $this->num_tokens; $i++) {
            $this->tokens[$i]['line'] = $line_number;
            $this->tokens[$i]['column'] = $curr_column;
            if (isset($this->known_lengths[$this->tokens[$i]['code']]) === true) {
                // There are no tabs in the tokens we know the length of.
                $length = $this->known_lengths[$this->tokens[$i]['code']];
                $curr_column += $length;
            } elseif ($tab_width === 0 || isset($tokens_with_tabs[$this->tokens[$i]['code']]) === false || strpos($this->tokens[$i]['content'], "\t") === false) {
                // There are no tabs in this content, or we aren't replacing them.
                if ($check_encoding === true) {
                    // Not using the default encoding, so take a bit more care.
                    $old_level = error_reporting();
                    error_reporting(0);
                    $length = iconv_strlen($this->tokens[$i]['content'], $encoding);
                    error_reporting($old_level);
                    if ($length === false) {
                        // String contained invalid characters, so revert to default.
                        $length = strlen($this->tokens[$i]['content']);
                    }
                } else {
                    $length = strlen($this->tokens[$i]['content']);
                }
                $curr_column += $length;
            } else {
                $this->replace_tabs_in_token($this->tokens[$i]);
                $length = $this->tokens[$i]['length'];
                $curr_column += $length;
            }
            //end if
            $this->tokens[$i]['length'] = $length;
            if (isset($this->known_lengths[$this->tokens[$i]['code']]) === false && strpos($this->tokens[$i]['content'], $this->eol_char) !== false) {
                $line_number++;
                $curr_column = 1;
                // Newline chars are not counted in the token length.
                $this->tokens[$i]['length'] -= $eol_len;
            }
            if ($this->tokens[$i]['code'] === T_COMMENT || $this->tokens[$i]['code'] === T_DOC_COMMENT_STRING || $this->tokens[$i]['code'] === T_DOC_COMMENT_TAG || $in_tests === true && $this->tokens[$i]['code'] === T_INLINE_HTML) {
                $comment_text = ltrim($this->tokens[$i]['content'], " \t/*#");
                $comment_text = rtrim($comment_text, " */\t\r\n");
                $comment_text_lower = strtolower($comment_text);
                if (strpos($comment_text, '@codingStandards') !== false) {
                    // If this comment is the only thing on the line, it tells us
                    // to ignore the following line. If the line contains other content
                    // then we are just ignoring this one single line.
                    $own_line = false;
                    if ($i > 0) {
                        for ($prev = $i - 1; $prev >= 0; $prev--) {
                            if ($this->tokens[$prev]['code'] === T_WHITESPACE) {
                                continue;
                            }
                            break;
                        }
                        if ($this->tokens[$prev]['line'] !== $this->tokens[$i]['line']) {
                            $own_line = true;
                        }
                    }
                    if ($ignoring === null && strpos($comment_text, '@codingStandardsIgnoreStart') !== false) {
                        $ignoring = ['.all' => true];
                        if ($own_line === true) {
                            $this->ignored_lines[$this->tokens[$i]['line']] = $ignoring;
                        }
                    } elseif ($ignoring !== null && strpos($comment_text, '@codingStandardsIgnoreEnd') !== false) {
                        if ($own_line === true) {
                            $this->ignored_lines[$this->tokens[$i]['line']] = ['.all' => true];
                        } else {
                            $this->ignored_lines[$this->tokens[$i]['line']] = $ignoring;
                        }
                        $ignoring = null;
                    } elseif ($ignoring === null && strpos($comment_text, '@codingStandardsIgnoreLine') !== false) {
                        $ignoring = ['.all' => true];
                        if ($own_line === true) {
                            $this->ignored_lines[$this->tokens[$i]['line']] = $ignoring;
                            $this->ignored_lines[$this->tokens[$i]['line'] + 1] = $ignoring;
                        } else {
                            $this->ignored_lines[$this->tokens[$i]['line']] = $ignoring;
                        }
                        $ignoring = null;
                    }
                    //end if
                } elseif (substr($comment_text_lower, 0, 6) === 'phpcs:' || substr($comment_text_lower, 0, 7) === '@phpcs:') {
                    // If the @phpcs: syntax is being used, strip the @ to make
                    // comparisons easier.
                    if ($comment_text[0] === '@') {
                        $comment_text = substr($comment_text, 1);
                        $comment_text_lower = strtolower($comment_text);
                    }
                    // If there is a comment on the end, strip it off.
                    $comment_start = strpos($comment_text_lower, ' --');
                    if ($comment_start !== false) {
                        $comment_text = substr($comment_text, 0, $comment_start);
                        $comment_text_lower = strtolower($comment_text);
                    }
                    // If this comment is the only thing on the line, it tells us
                    // to ignore the following line. If the line contains other content
                    // then we are just ignoring this one single line.
                    $line_has_other_content = false;
                    $line_has_other_tokens = false;
                    if ($i > 0) {
                        for ($prev = $i - 1; $prev > 0; $prev--) {
                            if ($this->tokens[$prev]['line'] !== $this->tokens[$i]['line']) {
                                // Changed lines.
                                break;
                            }
                            if ($this->tokens[$prev]['code'] === T_WHITESPACE) {
                                continue;
                            }
                            if ($this->tokens[$prev]['code'] === T_DOC_COMMENT_WHITESPACE) {
                                continue;
                            }
                            if ($this->tokens[$prev]['code'] === T_INLINE_HTML && trim($this->tokens[$prev]['content']) === '') {
                                continue;
                            }
                            $line_has_other_tokens = true;
                            if ($this->tokens[$prev]['code'] === T_OPEN_TAG) {
                                continue;
                            }
                            if ($this->tokens[$prev]['code'] === T_DOC_COMMENT_STAR) {
                                continue;
                            }
                            $line_has_other_content = true;
                            break;
                        }
                        //end for
                        $changed_lines = false;
                        for ($next = $i; $next < $this->num_tokens; $next++) {
                            if ($changed_lines === true) {
                                // Changed lines.
                                break;
                            }
                            if (isset($this->known_lengths[$this->tokens[$next]['code']]) === false && strpos($this->tokens[$next]['content'], $this->eol_char) !== false) {
                                // Last token on the current line.
                                $changed_lines = true;
                            }
                            if ($next === $i) {
                                continue;
                            }
                            if ($this->tokens[$next]['code'] === T_WHITESPACE) {
                                continue;
                            }
                            if ($this->tokens[$next]['code'] === T_DOC_COMMENT_WHITESPACE) {
                                continue;
                            }
                            if ($this->tokens[$next]['code'] === T_INLINE_HTML && trim($this->tokens[$next]['content']) === '') {
                                continue;
                            }
                            $line_has_other_tokens = true;
                            if ($this->tokens[$next]['code'] === T_CLOSE_TAG) {
                                continue;
                            }
                            $line_has_other_content = true;
                            break;
                        }
                        //end for
                    }
                    //end if
                    if (substr($comment_text_lower, 0, 9) === 'phpcs:set') {
                        // Ignore standards for complete lines that change sniff settings.
                        if ($line_has_other_tokens === false) {
                            $this->ignored_lines[$this->tokens[$i]['line']] = ['.all' => true];
                        }
                        // Need to maintain case here, to get the correct sniff code.
                        $parts = explode(' ', substr($comment_text, 10));
                        if (count($parts) >= 2) {
                            $sniff_parts = explode('.', $parts[0]);
                            if (count($sniff_parts) >= 3) {
                                $this->tokens[$i]['sniffCode'] = array_shift($parts);
                                $this->tokens[$i]['sniffProperty'] = array_shift($parts);
                                $this->tokens[$i]['sniffPropertyValue'] = rtrim(implode(' ', $parts), " */\r\n");
                            }
                        }
                        $this->tokens[$i]['code'] = T_PHPCS_SET;
                        $this->tokens[$i]['type'] = 'T_PHPCS_SET';
                    } elseif (substr($comment_text_lower, 0, 16) === 'phpcs:ignorefile') {
                        // The whole file will be ignored, but at least set the correct token.
                        $this->tokens[$i]['code'] = T_PHPCS_IGNORE_FILE;
                        $this->tokens[$i]['type'] = 'T_PHPCS_IGNORE_FILE';
                    } elseif (substr($comment_text_lower, 0, 13) === 'phpcs:disable') {
                        if ($line_has_other_content === false) {
                            // Completely ignore the comment line.
                            $this->ignored_lines[$this->tokens[$i]['line']] = ['.all' => true];
                        }
                        if ($ignoring === null) {
                            $ignoring = [];
                        }
                        $disabled_sniffs = [];
                        $additional_text = substr($comment_text, 14);
                        if (empty($additional_text) === true) {
                            $ignoring = ['.all' => true];
                        } else {
                            $parts = explode(',', $additional_text);
                            foreach ($parts as $sniff_code) {
                                $sniff_code = trim($sniff_code);
                                $disabled_sniffs[$sniff_code] = true;
                                $ignoring[$sniff_code] = true;
                                // This newly disabled sniff might be disabling an existing
                                // enabled exception that we are tracking.
                                if (isset($ignoring['.except']) === true) {
                                    foreach (array_keys($ignoring['.except']) as $ignored_sniff_code) {
                                        if ($ignored_sniff_code === $sniff_code || strpos($ignored_sniff_code, $sniff_code . '.') === 0) {
                                            unset($ignoring['.except'][$ignored_sniff_code]);
                                        }
                                    }
                                    if (empty($ignoring['.except']) === true) {
                                        unset($ignoring['.except']);
                                    }
                                }
                            }
                            //end foreach
                        }
                        //end if
                        $this->tokens[$i]['code'] = T_PHPCS_DISABLE;
                        $this->tokens[$i]['type'] = 'T_PHPCS_DISABLE';
                        $this->tokens[$i]['sniffCodes'] = $disabled_sniffs;
                    } elseif (substr($comment_text_lower, 0, 12) === 'phpcs:enable') {
                        if ($ignoring !== null) {
                            $enabled_sniffs = [];
                            $additional_text = substr($comment_text, 13);
                            if (empty($additional_text) === true) {
                                $ignoring = null;
                            } else {
                                $parts = explode(',', $additional_text);
                                foreach ($parts as $sniff_code) {
                                    $sniff_code = trim($sniff_code);
                                    $enabled_sniffs[$sniff_code] = true;
                                    // This new enabled sniff might remove previously disabled
                                    // sniffs if it is actually a standard or category of sniffs.
                                    foreach (array_keys($ignoring) as $ignored_sniff_code) {
                                        if ($ignored_sniff_code === $sniff_code || strpos($ignored_sniff_code, $sniff_code . '.') === 0) {
                                            unset($ignoring[$ignored_sniff_code]);
                                        }
                                    }
                                    // This new enabled sniff might be able to clear up
                                    // previously enabled sniffs if it is actually a standard or
                                    // category of sniffs.
                                    if (isset($ignoring['.except']) === true) {
                                        foreach (array_keys($ignoring['.except']) as $ignored_sniff_code) {
                                            if ($ignored_sniff_code === $sniff_code || strpos($ignored_sniff_code, $sniff_code . '.') === 0) {
                                                unset($ignoring['.except'][$ignored_sniff_code]);
                                            }
                                        }
                                    }
                                }
                                //end foreach
                                if (empty($ignoring) === true) {
                                    $ignoring = null;
                                } else if (isset($ignoring['.except']) === true) {
                                    $ignoring['.except'] += $enabled_sniffs;
                                } else {
                                    $ignoring['.except'] = $enabled_sniffs;
                                }
                            }
                            //end if
                            if ($line_has_other_content === false) {
                                // Completely ignore the comment line.
                                $this->ignored_lines[$this->tokens[$i]['line']] = ['.all' => true];
                            } else {
                                // The comment is on the same line as the code it is ignoring,
                                // so respect the new ignore rules.
                                $this->ignored_lines[$this->tokens[$i]['line']] = $ignoring;
                            }
                            $this->tokens[$i]['sniffCodes'] = $enabled_sniffs;
                        }
                        //end if
                        $this->tokens[$i]['code'] = T_PHPCS_ENABLE;
                        $this->tokens[$i]['type'] = 'T_PHPCS_ENABLE';
                    } elseif (substr($comment_text_lower, 0, 12) === 'phpcs:ignore') {
                        $ignore_rules = [];
                        $additional_text = substr($comment_text, 13);
                        if (empty($additional_text) === true) {
                            $ignore_rules = ['.all' => true];
                        } else {
                            $parts = explode(',', $additional_text);
                            foreach ($parts as $sniff_code) {
                                $ignore_rules[trim($sniff_code)] = true;
                            }
                        }
                        $this->tokens[$i]['code'] = T_PHPCS_IGNORE;
                        $this->tokens[$i]['type'] = 'T_PHPCS_IGNORE';
                        $this->tokens[$i]['sniffCodes'] = $ignore_rules;
                        if ($ignoring !== null) {
                            $ignore_rules += $ignoring;
                        }
                        if ($line_has_other_content === false) {
                            // Completely ignore the comment line, and set the following
                            // line to include the ignore rules we've set.
                            $this->ignored_lines[$this->tokens[$i]['line']] = ['.all' => true];
                            $this->ignored_lines[$this->tokens[$i]['line'] + 1] = $ignore_rules;
                        } else {
                            // The comment is on the same line as the code it is ignoring,
                            // so respect the ignore rules it set.
                            $this->ignored_lines[$this->tokens[$i]['line']] = $ignore_rules;
                        }
                    }
                    //end if
                }
                //end if
            }
            //end if
            if ($ignoring !== null && isset($this->ignored_lines[$this->tokens[$i]['line']]) === false) {
                $this->ignored_lines[$this->tokens[$i]['line']] = $ignoring;
            }
        }
        //end for
        // If annotations are being ignored, we clear out all the ignore rules
        // but leave the annotations tokenized as normal.
        if ($check_annotations === false) {
            $this->ignored_lines = [];
        }
    }
    //end createPositionMap()
    /**
     * Replaces tabs in original token content with spaces.
     *
     * Each tab can represent between 1 and $config->tabWidth spaces,
     * so this cannot be a straight string replace. The original content
     * is placed into an orig_content index and the new token length is also
     * set in the length index.
     *
     * @param array  $token    The token to replace tabs inside.
     * @param string $prefix   The character to use to represent the start of a tab.
     * @param string $padding  The character to use to represent the end of a tab.
     * @param int    $tabWidth The number of spaces each tab represents.
     *
     * @return void
     */
    public function replace_tabs_in_token(array &$token, $prefix = ' ', $padding = ' ', $tab_width = null)
    {
        $check_encoding = false;
        if (function_exists('iconv_strlen') === true) {
            $check_encoding = true;
        }
        $curr_column = $token['column'];
        if ($tab_width === null) {
            $tab_width = $this->config->tab_width;
            if ($tab_width === 0) {
                $tab_width = 1;
            }
        }
        if (rtrim($token['content'], "\t") === '') {
            // String only contains tabs, so we can shortcut the process.
            $num_tabs = strlen($token['content']);
            $first_tab_size = $tab_width - ($curr_column - 1) % $tab_width;
            $length = $first_tab_size + $tab_width * ($num_tabs - 1);
            $new_content = $prefix . str_repeat($padding, $length - 1);
        } else {
            // We need to determine the length of each tab.
            $tabs = explode("\t", $token['content']);
            $num_tabs = count($tabs) - 1;
            $tab_num = 0;
            $new_content = '';
            $length = 0;
            foreach ($tabs as $content) {
                if ($content !== '') {
                    $new_content .= $content;
                    if ($check_encoding === true) {
                        // Not using the default encoding, so take a bit more care.
                        $old_level = error_reporting();
                        error_reporting(0);
                        $content_length = iconv_strlen($content, $this->config->encoding);
                        error_reporting($old_level);
                        if ($content_length === false) {
                            // String contained invalid characters, so revert to default.
                            $content_length = strlen($content);
                        }
                    } else {
                        $content_length = strlen($content);
                    }
                    $curr_column += $content_length;
                    $length += $content_length;
                }
                // The last piece of content does not have a tab after it.
                if ($tab_num === $num_tabs) {
                    break;
                }
                // Process the tab that comes after the content.
                $tab_num++;
                // Move the pointer to the next tab stop.
                $pad = $tab_width - ($curr_column + $tab_width - 1) % $tab_width;
                $curr_column += $pad;
                $length += $pad;
                $new_content .= $prefix . str_repeat($padding, $pad - 1);
            }
            //end foreach
        }
        //end if
        $token['orig_content'] = $token['content'];
        $token['content'] = $new_content;
        $token['length'] = $length;
    }
    //end replaceTabsInToken()
    /**
     * Creates a map of brackets positions.
     *
     * @return void
     */
    private function create_token_map()
    {
        if (PHP_CODESNIFFER_VERBOSITY > 1) {
            echo "\t*** START TOKEN MAP ***" . PHP_EOL;
        }
        $square_openers = [];
        $curly_openers = [];
        $this->num_tokens = count($this->tokens);
        $openers = [];
        $open_owner = null;
        for ($i = 0; $i < $this->num_tokens; $i++) {
            /*
                Parenthesis mapping.
            */
            if (isset(Util\Tokens::$parenthesis_openers[$this->tokens[$i]['code']]) === true) {
                $this->tokens[$i]['parenthesis_opener'] = null;
                $this->tokens[$i]['parenthesis_closer'] = null;
                $this->tokens[$i]['parenthesis_owner'] = $i;
                $open_owner = $i;
                if (PHP_CODESNIFFER_VERBOSITY > 1) {
                    echo str_repeat("\t", count($openers) + 1);
                    echo "=> Found parenthesis owner at {$i}" . PHP_EOL;
                }
            } elseif ($this->tokens[$i]['code'] === T_OPEN_PARENTHESIS) {
                $openers[] = $i;
                $this->tokens[$i]['parenthesis_opener'] = $i;
                if ($open_owner !== null) {
                    if (PHP_CODESNIFFER_VERBOSITY > 1) {
                        echo str_repeat("\t", count($openers));
                        echo "=> Found parenthesis opener at {$i} for {$open_owner}" . PHP_EOL;
                    }
                    $this->tokens[$open_owner]['parenthesis_opener'] = $i;
                    $this->tokens[$i]['parenthesis_owner'] = $open_owner;
                    $open_owner = null;
                } elseif (PHP_CODESNIFFER_VERBOSITY > 1) {
                    echo str_repeat("\t", count($openers));
                    echo "=> Found unowned parenthesis opener at {$i}" . PHP_EOL;
                }
            } elseif ($this->tokens[$i]['code'] === T_CLOSE_PARENTHESIS) {
                // Did we set an owner for this set of parenthesis?
                $num_openers = count($openers);
                if ($num_openers !== 0) {
                    $opener = array_pop($openers);
                    if (isset($this->tokens[$opener]['parenthesis_owner']) === true) {
                        $owner = $this->tokens[$opener]['parenthesis_owner'];
                        $this->tokens[$owner]['parenthesis_closer'] = $i;
                        $this->tokens[$i]['parenthesis_owner'] = $owner;
                        if (PHP_CODESNIFFER_VERBOSITY > 1) {
                            echo str_repeat("\t", count($openers) + 1);
                            echo "=> Found parenthesis closer at {$i} for {$owner}" . PHP_EOL;
                        }
                    } elseif (PHP_CODESNIFFER_VERBOSITY > 1) {
                        echo str_repeat("\t", count($openers) + 1);
                        echo "=> Found unowned parenthesis closer at {$i} for {$opener}" . PHP_EOL;
                    }
                    $this->tokens[$i]['parenthesis_opener'] = $opener;
                    $this->tokens[$i]['parenthesis_closer'] = $i;
                    $this->tokens[$opener]['parenthesis_closer'] = $i;
                }
                //end if
            } elseif ($this->tokens[$i]['code'] === T_ATTRIBUTE) {
                $openers[] = $i;
                if (PHP_CODESNIFFER_VERBOSITY > 1) {
                    echo str_repeat("\t", count($openers));
                    echo "=> Found attribute opener at {$i}" . PHP_EOL;
                }
                $this->tokens[$i]['attribute_opener'] = $i;
                $this->tokens[$i]['attribute_closer'] = null;
            } elseif ($this->tokens[$i]['code'] === T_ATTRIBUTE_END) {
                $num_openers = count($openers);
                if ($num_openers !== 0) {
                    $opener = array_pop($openers);
                    if (isset($this->tokens[$opener]['attribute_opener']) === true) {
                        $this->tokens[$opener]['attribute_closer'] = $i;
                        if (PHP_CODESNIFFER_VERBOSITY > 1) {
                            echo str_repeat("\t", count($openers) + 1);
                            echo "=> Found attribute closer at {$i} for {$opener}" . PHP_EOL;
                        }
                        for ($x = $opener + 1; $x <= $i; ++$x) {
                            if (isset($this->tokens[$x]['attribute_closer']) === true) {
                                continue;
                            }
                            $this->tokens[$x]['attribute_opener'] = $opener;
                            $this->tokens[$x]['attribute_closer'] = $i;
                        }
                    } elseif (PHP_CODESNIFFER_VERBOSITY > 1) {
                        echo str_repeat("\t", count($openers) + 1);
                        echo "=> Found unowned attribute closer at {$i} for {$opener}" . PHP_EOL;
                    }
                }
                //end if
            }
            //end if
            /*
                Bracket mapping.
            */
            switch ($this->tokens[$i]['code']) {
                case T_OPEN_SQUARE_BRACKET:
                    $square_openers[] = $i;
                    if (PHP_CODESNIFFER_VERBOSITY > 1) {
                        echo str_repeat("\t", count($square_openers));
                        echo str_repeat("\t", count($curly_openers));
                        echo "=> Found square bracket opener at {$i}" . PHP_EOL;
                    }
                    break;
                case T_OPEN_CURLY_BRACKET:
                    if (isset($this->tokens[$i]['scope_closer']) === false) {
                        $curly_openers[] = $i;
                        if (PHP_CODESNIFFER_VERBOSITY > 1) {
                            echo str_repeat("\t", count($square_openers));
                            echo str_repeat("\t", count($curly_openers));
                            echo "=> Found curly bracket opener at {$i}" . PHP_EOL;
                        }
                    }
                    break;
                case T_CLOSE_SQUARE_BRACKET:
                    if (empty($square_openers) === false) {
                        $opener = array_pop($square_openers);
                        $this->tokens[$i]['bracket_opener'] = $opener;
                        $this->tokens[$i]['bracket_closer'] = $i;
                        $this->tokens[$opener]['bracket_opener'] = $opener;
                        $this->tokens[$opener]['bracket_closer'] = $i;
                        if (PHP_CODESNIFFER_VERBOSITY > 1) {
                            echo str_repeat("\t", count($square_openers));
                            echo str_repeat("\t", count($curly_openers));
                            echo "\t=> Found square bracket closer at {$i} for {$opener}" . PHP_EOL;
                        }
                    }
                    break;
                case T_CLOSE_CURLY_BRACKET:
                    if (empty($curly_openers) === false && isset($this->tokens[$i]['scope_opener']) === false) {
                        $opener = array_pop($curly_openers);
                        $this->tokens[$i]['bracket_opener'] = $opener;
                        $this->tokens[$i]['bracket_closer'] = $i;
                        $this->tokens[$opener]['bracket_opener'] = $opener;
                        $this->tokens[$opener]['bracket_closer'] = $i;
                        if (PHP_CODESNIFFER_VERBOSITY > 1) {
                            echo str_repeat("\t", count($square_openers));
                            echo str_repeat("\t", count($curly_openers));
                            echo "\t=> Found curly bracket closer at {$i} for {$opener}" . PHP_EOL;
                        }
                    }
                    break;
                default:
                    continue 2;
            }
            //end switch
        }
        //end for
        // Cleanup for any openers that we didn't find closers for.
        // This typically means there was a syntax error breaking things.
        foreach ($openers as $opener) {
            unset($this->tokens[$opener]['parenthesis_opener']);
            unset($this->tokens[$opener]['parenthesis_owner']);
        }
        if (PHP_CODESNIFFER_VERBOSITY > 1) {
            echo "\t*** END TOKEN MAP ***" . PHP_EOL;
        }
    }
    //end createTokenMap()
    /**
     * Creates a map for the parenthesis tokens that surround other tokens.
     *
     * @return void
     */
    private function create_parenthesis_nesting_map()
    {
        $map = [];
        for ($i = 0; $i < $this->num_tokens; $i++) {
            if (isset($this->tokens[$i]['parenthesis_opener']) === true && $i === $this->tokens[$i]['parenthesis_opener']) {
                if (empty($map) === false) {
                    $this->tokens[$i]['nested_parenthesis'] = $map;
                }
                if (isset($this->tokens[$i]['parenthesis_closer']) === true) {
                    $map[$this->tokens[$i]['parenthesis_opener']] = $this->tokens[$i]['parenthesis_closer'];
                }
            } elseif (isset($this->tokens[$i]['parenthesis_closer']) === true && $i === $this->tokens[$i]['parenthesis_closer']) {
                array_pop($map);
                if (empty($map) === false) {
                    $this->tokens[$i]['nested_parenthesis'] = $map;
                }
            } else if (empty($map) === false) {
                $this->tokens[$i]['nested_parenthesis'] = $map;
            }
            //end if
        }
        //end for
    }
    //end createParenthesisNestingMap()
    /**
     * Creates a scope map of tokens that open scopes.
     *
     * @return void
     * @see    recurseScopeMap()
     */
    private function create_scope_map()
    {
        if (PHP_CODESNIFFER_VERBOSITY > 1) {
            echo "\t*** START SCOPE MAP ***" . PHP_EOL;
        }
        for ($i = 0; $i < $this->num_tokens; $i++) {
            // Check to see if the current token starts a new scope.
            if (isset($this->scope_openers[$this->tokens[$i]['code']]) === true) {
                if (PHP_CODESNIFFER_VERBOSITY > 1) {
                    $type = $this->tokens[$i]['type'];
                    $content = Util\Common::prepare_for_output($this->tokens[$i]['content']);
                    echo "\tStart scope map at {$i}:{$type} => {$content}" . PHP_EOL;
                }
                if (isset($this->tokens[$i]['scope_condition']) === true) {
                    if (PHP_CODESNIFFER_VERBOSITY > 1) {
                        echo "\t* already processed, skipping *" . PHP_EOL;
                    }
                    continue;
                }
                $i = $this->recurse_scope_map($i);
            }
            //end if
        }
        //end for
        if (PHP_CODESNIFFER_VERBOSITY > 1) {
            echo "\t*** END SCOPE MAP ***" . PHP_EOL;
        }
    }
    //end createScopeMap()
    /**
     * Recurses though the scope openers to build a scope map.
     *
     * @param int $stackPtr The position in the stack of the token that
     *                      opened the scope (eg. an IF token or FOR token).
     * @param int $depth    How many scope levels down we are.
     * @param int $ignore   How many curly braces we are ignoring.
     *
     * @return int The position in the stack that closed the scope.
     * @throws \PHP_CodeSniffer\Exceptions\TokenizerException If the nesting level gets too deep.
     */
    private function recurse_scope_map($stack_ptr, $depth = 1, &$ignore = 0)
    {
        if (PHP_CODESNIFFER_VERBOSITY > 1) {
            echo str_repeat("\t", $depth);
            echo "=> Begin scope map recursion at token {$stack_ptr} with depth {$depth}" . PHP_EOL;
        }
        $opener = null;
        $curr_type = $this->tokens[$stack_ptr]['code'];
        $start_line = $this->tokens[$stack_ptr]['line'];
        // We will need this to restore the value if we end up
        // returning a token ID that causes our calling function to go back
        // over already ignored braces.
        $original_ignore = $ignore;
        // If the start token for this scope opener is the same as
        // the scope token, we have already found our opener.
        if (isset($this->scope_openers[$curr_type]['start'][$curr_type]) === true) {
            $opener = $stack_ptr;
        }
        for ($i = $stack_ptr + 1; $i < $this->num_tokens; $i++) {
            $token_type = $this->tokens[$i]['code'];
            if (PHP_CODESNIFFER_VERBOSITY > 1) {
                $type = $this->tokens[$i]['type'];
                $line = $this->tokens[$i]['line'];
                $content = Util\Common::prepare_for_output($this->tokens[$i]['content']);
                echo str_repeat("\t", $depth);
                echo "Process token {$i} on line {$line} [";
                if ($opener !== null) {
                    echo "opener:{$opener};";
                }
                if ($ignore > 0) {
                    echo "ignore={$ignore};";
                }
                echo "]: {$type} => {$content}" . PHP_EOL;
            }
            //end if
            // Very special case for IF statements in PHP that can be defined without
            // scope tokens. E.g., if (1) 1; 1 ? (1 ? 1 : 1) : 1;
            // If an IF statement below this one has an opener but no
            // keyword, the opener will be incorrectly assigned to this IF statement.
            // The same case also applies to USE statements, which don't have to have
            // openers, so a following USE statement can cause an incorrect brace match.
            if (($curr_type === T_IF || $curr_type === T_ELSE || $curr_type === T_USE) && $opener === null && ($this->tokens[$i]['code'] === T_SEMICOLON || $this->tokens[$i]['code'] === T_CLOSE_TAG)) {
                if (PHP_CODESNIFFER_VERBOSITY > 1) {
                    $type = $this->tokens[$stack_ptr]['type'];
                    echo str_repeat("\t", $depth);
                    if ($this->tokens[$i]['code'] === T_SEMICOLON) {
                        $closer_type = 'semicolon';
                    } else {
                        $closer_type = 'close tag';
                    }
                    echo "=> Found {$closer_type} before scope opener for {$stack_ptr}:{$type}, bailing" . PHP_EOL;
                }
                return $i;
            }
            // Special case for PHP control structures that have no braces.
            // If we find a curly brace closer before we find the opener,
            // we're not going to find an opener. That closer probably belongs to
            // a control structure higher up.
            if ($opener === null && $ignore === 0 && $token_type === T_CLOSE_CURLY_BRACKET && isset($this->scope_openers[$curr_type]['end'][$token_type]) === true) {
                if (PHP_CODESNIFFER_VERBOSITY > 1) {
                    $type = $this->tokens[$stack_ptr]['type'];
                    echo str_repeat("\t", $depth);
                    echo "=> Found curly brace closer before scope opener for {$stack_ptr}:{$type}, bailing" . PHP_EOL;
                }
                return $i - 1;
            }
            if ($opener !== null && (isset($this->tokens[$i]['scope_opener']) === false || $this->scope_openers[$this->tokens[$stack_ptr]['code']]['shared'] === true) && isset($this->scope_openers[$curr_type]['end'][$token_type]) === true) {
                if ($ignore > 0 && $token_type === T_CLOSE_CURLY_BRACKET) {
                    // The last opening bracket must have been for a string
                    // offset or alike, so let's ignore it.
                    if (PHP_CODESNIFFER_VERBOSITY > 1) {
                        echo str_repeat("\t", $depth);
                        echo '* finished ignoring curly brace *' . PHP_EOL;
                    }
                    $ignore--;
                    continue;
                }
                if ($this->tokens[$opener]['code'] === T_OPEN_CURLY_BRACKET && $token_type !== T_CLOSE_CURLY_BRACKET) {
                    // The opener is a curly bracket so the closer must be a curly bracket as well.
                    // We ignore this closer to handle cases such as T_ELSE or T_ELSEIF being considered
                    // a closer of T_IF when it should not.
                    if (PHP_CODESNIFFER_VERBOSITY > 1) {
                        $type = $this->tokens[$stack_ptr]['type'];
                        echo str_repeat("\t", $depth);
                        echo "=> Ignoring non-curly scope closer for {$stack_ptr}:{$type}" . PHP_EOL;
                    }
                } else {
                    $scope_closer = $i;
                    $todo = [$stack_ptr, $opener];
                    if (PHP_CODESNIFFER_VERBOSITY > 1) {
                        $type = $this->tokens[$stack_ptr]['type'];
                        $closer_type = $this->tokens[$scope_closer]['type'];
                        echo str_repeat("\t", $depth);
                        echo "=> Found scope closer ({$scope_closer}:{$closer_type}) for {$stack_ptr}:{$type}" . PHP_EOL;
                    }
                    $valid_closer = true;
                    if (($this->tokens[$stack_ptr]['code'] === T_IF || $this->tokens[$stack_ptr]['code'] === T_ELSEIF) && ($token_type === T_ELSE || $token_type === T_ELSEIF)) {
                        // To be a closer, this token must have an opener.
                        if (PHP_CODESNIFFER_VERBOSITY > 1) {
                            echo str_repeat("\t", $depth);
                            echo '* closer needs to be tested *' . PHP_EOL;
                        }
                        $i = self::recurse_scope_map($i, $depth + 1, $ignore);
                        if (isset($this->tokens[$scope_closer]['scope_opener']) === false) {
                            $valid_closer = false;
                            if (PHP_CODESNIFFER_VERBOSITY > 1) {
                                echo str_repeat("\t", $depth);
                                echo '* closer is not valid (no opener found) *' . PHP_EOL;
                            }
                        } elseif ($this->tokens[$this->tokens[$scope_closer]['scope_opener']]['code'] !== $this->tokens[$opener]['code']) {
                            $valid_closer = false;
                            if (PHP_CODESNIFFER_VERBOSITY > 1) {
                                echo str_repeat("\t", $depth);
                                $type = $this->tokens[$this->tokens[$scope_closer]['scope_opener']]['type'];
                                $opener_type = $this->tokens[$opener]['type'];
                                echo "* closer is not valid (mismatched opener type; {$type} != {$opener_type}) *" . PHP_EOL;
                            }
                        } elseif (PHP_CODESNIFFER_VERBOSITY > 1) {
                            echo str_repeat("\t", $depth);
                            echo '* closer was valid *' . PHP_EOL;
                        }
                    } else {
                        // The closer was not processed, so we need to
                        // complete that token as well.
                        $todo[] = $scope_closer;
                    }
                    //end if
                    if ($valid_closer === true) {
                        foreach ($todo as $token) {
                            $this->tokens[$token]['scope_condition'] = $stack_ptr;
                            $this->tokens[$token]['scope_opener'] = $opener;
                            $this->tokens[$token]['scope_closer'] = $scope_closer;
                        }
                        if ($this->scope_openers[$this->tokens[$stack_ptr]['code']]['shared'] === true) {
                            // As we are going back to where we started originally, restore
                            // the ignore value back to its original value.
                            $ignore = $original_ignore;
                            return $opener;
                        }
                        if ($scope_closer === $i && isset($this->scope_openers[$token_type]) === true) {
                            // Unset scope_condition here or else the token will appear to have
                            // already been processed, and it will be skipped. Normally we want that,
                            // but in this case, the token is both a closer and an opener, so
                            // it needs to act like an opener. This is also why we return the
                            // token before this one; so the closer has a chance to be processed
                            // a second time, but as an opener.
                            unset($this->tokens[$scope_closer]['scope_condition']);
                            return $i - 1;
                        }
                        return $i;
                    }
                    continue;
                    //end if
                }
                //end if
            }
            //end if
            // Is this an opening condition ?
            if (isset($this->scope_openers[$token_type]) === true) {
                if ($opener === null) {
                    if ($token_type === T_USE) {
                        // PHP use keywords are special because they can be
                        // used as blocks but also inline in function definitions.
                        // So if we find them nested inside another opener, just skip them.
                        continue;
                    }
                    if ($token_type === T_NAMESPACE) {
                        // PHP namespace keywords are special because they can be
                        // used as blocks but also inline as operators.
                        // So if we find them nested inside another opener, just skip them.
                        continue;
                    }
                    if ($token_type === T_FUNCTION && $this->tokens[$stack_ptr]['code'] !== T_FUNCTION) {
                        // Probably a closure, so process it manually.
                        if (PHP_CODESNIFFER_VERBOSITY > 1) {
                            $type = $this->tokens[$stack_ptr]['type'];
                            echo str_repeat("\t", $depth);
                            echo "=> Found function before scope opener for {$stack_ptr}:{$type}, processing manually" . PHP_EOL;
                        }
                        if (isset($this->tokens[$i]['scope_closer']) === true) {
                            // We've already processed this closure.
                            if (PHP_CODESNIFFER_VERBOSITY > 1) {
                                echo str_repeat("\t", $depth);
                                echo '* already processed, skipping *' . PHP_EOL;
                            }
                            $i = $this->tokens[$i]['scope_closer'];
                            continue;
                        }
                        $i = self::recurse_scope_map($i, $depth + 1, $ignore);
                        continue;
                    }
                    //end if
                    if ($token_type === T_CLASS) {
                        // Probably an anonymous class inside another anonymous class,
                        // so process it manually.
                        if (PHP_CODESNIFFER_VERBOSITY > 1) {
                            $type = $this->tokens[$stack_ptr]['type'];
                            echo str_repeat("\t", $depth);
                            echo "=> Found class before scope opener for {$stack_ptr}:{$type}, processing manually" . PHP_EOL;
                        }
                        if (isset($this->tokens[$i]['scope_closer']) === true) {
                            // We've already processed this anon class.
                            if (PHP_CODESNIFFER_VERBOSITY > 1) {
                                echo str_repeat("\t", $depth);
                                echo '* already processed, skipping *' . PHP_EOL;
                            }
                            $i = $this->tokens[$i]['scope_closer'];
                            continue;
                        }
                        $i = self::recurse_scope_map($i, $depth + 1, $ignore);
                        continue;
                    }
                    //end if
                    // Found another opening condition but still haven't
                    // found our opener, so we are never going to find one.
                    if (PHP_CODESNIFFER_VERBOSITY > 1) {
                        $type = $this->tokens[$stack_ptr]['type'];
                        echo str_repeat("\t", $depth);
                        echo "=> Found new opening condition before scope opener for {$stack_ptr}:{$type}, ";
                    }
                    if (($this->tokens[$stack_ptr]['code'] === T_IF || $this->tokens[$stack_ptr]['code'] === T_ELSEIF || $this->tokens[$stack_ptr]['code'] === T_ELSE) && ($this->tokens[$i]['code'] === T_ELSE || $this->tokens[$i]['code'] === T_ELSEIF)) {
                        if (PHP_CODESNIFFER_VERBOSITY > 1) {
                            echo 'continuing' . PHP_EOL;
                        }
                        return $i - 1;
                    }
                    if (PHP_CODESNIFFER_VERBOSITY > 1) {
                        echo 'backtracking' . PHP_EOL;
                    }
                    return $stack_ptr;
                }
                //end if
                if (PHP_CODESNIFFER_VERBOSITY > 1) {
                    echo str_repeat("\t", $depth);
                    echo '* token is an opening condition *' . PHP_EOL;
                }
                $is_shared = $this->scope_openers[$token_type]['shared'] === true;
                if (isset($this->tokens[$i]['scope_condition']) === true) {
                    // We've been here before.
                    if (PHP_CODESNIFFER_VERBOSITY > 1) {
                        echo str_repeat("\t", $depth);
                        echo '* already processed, skipping *' . PHP_EOL;
                    }
                    if ($is_shared === false && isset($this->tokens[$i]['scope_closer']) === true) {
                        $i = $this->tokens[$i]['scope_closer'];
                    }
                    continue;
                }
                if ($curr_type === $token_type && $is_shared === false && $opener === null) {
                    // We haven't yet found our opener, but we have found another
                    // scope opener which is the same type as us, and we don't
                    // share openers, so we will never find one.
                    if (PHP_CODESNIFFER_VERBOSITY > 1) {
                        echo str_repeat("\t", $depth);
                        echo '* it was another token\'s opener, bailing *' . PHP_EOL;
                    }
                    return $stack_ptr;
                }
                if (PHP_CODESNIFFER_VERBOSITY > 1) {
                    echo str_repeat("\t", $depth);
                    echo '* searching for opener *' . PHP_EOL;
                }
                if (isset($this->scope_openers[$token_type]['end'][T_CLOSE_CURLY_BRACKET]) === true) {
                    $old_ignore = $ignore;
                    $ignore = 0;
                }
                // PHP has a max nesting level for functions. Stop before we hit that limit
                // because too many loops means we've run into trouble anyway.
                if ($depth > 50) {
                    if (PHP_CODESNIFFER_VERBOSITY > 1) {
                        echo str_repeat("\t", $depth);
                        echo '* reached maximum nesting level; aborting *' . PHP_EOL;
                    }
                    throw new Tokenizer_Exception('Maximum nesting level reached; file could not be processed');
                }
                $old_depth = $depth;
                if ($is_shared === true && isset($this->scope_openers[$token_type]['with'][$curr_type]) === true) {
                    // Don't allow the depth to increment because this is
                    // possibly not a true nesting if we are sharing our closer.
                    // This can happen, for example, when a SWITCH has a large
                    // number of CASE statements with the same shared BREAK.
                    $depth--;
                }
                $i = self::recurse_scope_map($i, $depth + 1, $ignore);
                $depth = $old_depth;
                if (isset($this->scope_openers[$token_type]['end'][T_CLOSE_CURLY_BRACKET]) === true) {
                    $ignore = $old_ignore;
                }
                //end if
            }
            //end if
            if (isset($this->scope_openers[$curr_type]['start'][$token_type]) === true && $opener === null) {
                if ($token_type === T_OPEN_CURLY_BRACKET) {
                    if (isset($this->tokens[$stack_ptr]['parenthesis_closer']) === true && $i < $this->tokens[$stack_ptr]['parenthesis_closer']) {
                        // We found a curly brace inside the condition of the
                        // current scope opener, so it must be a string offset.
                        if (PHP_CODESNIFFER_VERBOSITY > 1) {
                            echo str_repeat("\t", $depth);
                            echo '* ignoring curly brace inside condition *' . PHP_EOL;
                        }
                        $ignore++;
                    } else {
                        // Make sure this is actually an opener and not a
                        // string offset (e.g., $var{0}).
                        for ($x = $i - 1; $x > 0; $x--) {
                            if (isset(Util\Tokens::$empty_tokens[$this->tokens[$x]['code']]) === true) {
                                continue;
                            }
                            // If the first non-whitespace/comment token looks like this
                            // brace is a string offset, or this brace is mid-way through
                            // a new statement, it isn't a scope opener.
                            $disallowed = Util\Tokens::$assignment_tokens;
                            $disallowed += [T_DOLLAR => true, T_VARIABLE => true, T_OBJECT_OPERATOR => true, T_NULLSAFE_OBJECT_OPERATOR => true, T_COMMA => true, T_OPEN_PARENTHESIS => true];
                            if (isset($disallowed[$this->tokens[$x]['code']]) === true) {
                                if (PHP_CODESNIFFER_VERBOSITY > 1) {
                                    echo str_repeat("\t", $depth);
                                    echo '* ignoring curly brace *' . PHP_EOL;
                                }
                                $ignore++;
                            }
                            break;
                            //end if
                        }
                        //end for
                    }
                    //end if
                }
                //end if
                if ($ignore === 0 || $token_type !== T_OPEN_CURLY_BRACKET) {
                    $opener_nested = isset($this->tokens[$i]['nested_parenthesis']);
                    $owner_nested = isset($this->tokens[$stack_ptr]['nested_parenthesis']);
                    if ($opener_nested === true && $owner_nested === false || $opener_nested === false && $owner_nested === true || $opener_nested === true && $this->tokens[$i]['nested_parenthesis'] !== $this->tokens[$stack_ptr]['nested_parenthesis']) {
                        // We found the a token that looks like the opener, but it's nested differently.
                        if (PHP_CODESNIFFER_VERBOSITY > 1) {
                            $type = $this->tokens[$i]['type'];
                            echo str_repeat("\t", $depth);
                            echo "* ignoring possible opener {$i}:{$type} as nested parenthesis don't match *" . PHP_EOL;
                        }
                    } else {
                        // We found the opening scope token for $currType.
                        if (PHP_CODESNIFFER_VERBOSITY > 1) {
                            $type = $this->tokens[$stack_ptr]['type'];
                            echo str_repeat("\t", $depth);
                            echo "=> Found scope opener for {$stack_ptr}:{$type}" . PHP_EOL;
                        }
                        $opener = $i;
                    }
                }
                //end if
            } else {
                if ($token_type === T_SEMICOLON && $opener === null && (isset($this->tokens[$stack_ptr]['parenthesis_closer']) === false || $i > $this->tokens[$stack_ptr]['parenthesis_closer'])) {
                    // Found the end of a statement but still haven't
                    // found our opener, so we are never going to find one.
                    if (PHP_CODESNIFFER_VERBOSITY > 1) {
                        $type = $this->tokens[$stack_ptr]['type'];
                        echo str_repeat("\t", $depth);
                        echo "=> Found end of statement before scope opener for {$stack_ptr}:{$type}, continuing" . PHP_EOL;
                    }
                    return $i - 1;
                }
                if ($token_type === T_OPEN_PARENTHESIS) {
                    if (isset($this->tokens[$i]['parenthesis_owner']) === true) {
                        $owner = $this->tokens[$i]['parenthesis_owner'];
                        if (isset(Util\Tokens::$scope_openers[$this->tokens[$owner]['code']]) === true && isset($this->tokens[$i]['parenthesis_closer']) === true) {
                            // If we get into here, then we opened a parenthesis for
                            // a scope (eg. an if or else if) so we need to update the
                            // start of the line so that when we check to see
                            // if the closing parenthesis is more than n lines away from
                            // the statement, we check from the closing parenthesis.
                            $start_line = $this->tokens[$this->tokens[$i]['parenthesis_closer']]['line'];
                        }
                    }
                } elseif ($token_type === T_OPEN_CURLY_BRACKET && $opener !== null) {
                    // We opened something that we don't have a scope opener for.
                    // Examples of this are curly brackets for string offsets etc.
                    // We want to ignore this so that we don't have an invalid scope
                    // map.
                    if (PHP_CODESNIFFER_VERBOSITY > 1) {
                        echo str_repeat("\t", $depth);
                        echo '* ignoring curly brace *' . PHP_EOL;
                    }
                    $ignore++;
                } elseif ($token_type === T_CLOSE_CURLY_BRACKET && $ignore > 0) {
                    // We found the end token for the opener we were ignoring.
                    if (PHP_CODESNIFFER_VERBOSITY > 1) {
                        echo str_repeat("\t", $depth);
                        echo '* finished ignoring curly brace *' . PHP_EOL;
                    }
                    $ignore--;
                } elseif ($opener === null && isset($this->scope_openers[$curr_type]) === true) {
                    // If we still haven't found the opener after 30 lines,
                    // we're not going to find it, unless we know it requires
                    // an opener (in which case we better keep looking) or the last
                    // token was empty (in which case we'll just confirm there is
                    // more code in this file and not just a big comment).
                    if ($this->tokens[$i]['line'] >= $start_line + 30 && isset(Util\Tokens::$empty_tokens[$this->tokens[$i - 1]['code']]) === false) {
                        if ($this->scope_openers[$curr_type]['strict'] === true) {
                            if (PHP_CODESNIFFER_VERBOSITY > 1) {
                                $type = $this->tokens[$stack_ptr]['type'];
                                $lines = $this->tokens[$i]['line'] - $start_line;
                                echo str_repeat("\t", $depth);
                                echo "=> Still looking for {$stack_ptr}:{$type} scope opener after {$lines} lines" . PHP_EOL;
                            }
                        } else {
                            if (PHP_CODESNIFFER_VERBOSITY > 1) {
                                $type = $this->tokens[$stack_ptr]['type'];
                                echo str_repeat("\t", $depth);
                                echo "=> Couldn't find scope opener for {$stack_ptr}:{$type}, bailing" . PHP_EOL;
                            }
                            return $stack_ptr;
                        }
                    }
                } elseif ($opener !== null && $token_type !== T_BREAK && isset($this->end_scope_tokens[$token_type]) === true) {
                    if (isset($this->tokens[$i]['scope_condition']) === false) {
                        if ($ignore > 0) {
                            // We found the end token for the opener we were ignoring.
                            if (PHP_CODESNIFFER_VERBOSITY > 1) {
                                echo str_repeat("\t", $depth);
                                echo '* finished ignoring curly brace *' . PHP_EOL;
                            }
                            $ignore--;
                        } else {
                            // We found a token that closes the scope but it doesn't
                            // have a condition, so it belongs to another token and
                            // our token doesn't have a closer, so pretend this is
                            // the closer.
                            if (PHP_CODESNIFFER_VERBOSITY > 1) {
                                $type = $this->tokens[$stack_ptr]['type'];
                                echo str_repeat("\t", $depth);
                                echo "=> Found (unexpected) scope closer for {$stack_ptr}:{$type}" . PHP_EOL;
                            }
                            foreach ([$stack_ptr, $opener] as $token) {
                                $this->tokens[$token]['scope_condition'] = $stack_ptr;
                                $this->tokens[$token]['scope_opener'] = $opener;
                                $this->tokens[$token]['scope_closer'] = $i;
                            }
                            return $i - 1;
                        }
                        //end if
                    }
                    //end if
                }
            }
            //end if
        }
        //end for
        return $stack_ptr;
    }
    //end recurseScopeMap()
    /**
     * Constructs the level map.
     *
     * The level map adds a 'level' index to each token which indicates the
     * depth that a token within a set of scope blocks. It also adds a
     * 'conditions' index which is an array of the scope conditions that opened
     * each of the scopes - position 0 being the first scope opener.
     *
     * @return void
     */
    private function create_level_map()
    {
        if (PHP_CODESNIFFER_VERBOSITY > 1) {
            echo "\t*** START LEVEL MAP ***" . PHP_EOL;
        }
        $this->num_tokens = count($this->tokens);
        $level = 0;
        $conditions = [];
        $last_opener = null;
        $openers = [];
        for ($i = 0; $i < $this->num_tokens; $i++) {
            if (PHP_CODESNIFFER_VERBOSITY > 1) {
                $type = $this->tokens[$i]['type'];
                $line = $this->tokens[$i]['line'];
                $len = $this->tokens[$i]['length'];
                $col = $this->tokens[$i]['column'];
                $content = Util\Common::prepare_for_output($this->tokens[$i]['content']);
                echo str_repeat("\t", $level + 1);
                echo "Process token {$i} on line {$line} [col:{$col};len:{$len};lvl:{$level};";
                if (empty($conditions) !== true) {
                    $condition_string = 'conds;';
                    foreach ($conditions as $condition) {
                        $condition_string .= Util\Tokens::token_name($condition) . ',';
                    }
                    echo rtrim($condition_string, ',') . ';';
                }
                echo "]: {$type} => {$content}" . PHP_EOL;
            }
            //end if
            $this->tokens[$i]['level'] = $level;
            $this->tokens[$i]['conditions'] = $conditions;
            if (isset($this->tokens[$i]['scope_condition']) === true) {
                // Check to see if this token opened the scope.
                if ($this->tokens[$i]['scope_opener'] === $i) {
                    $stack_ptr = $this->tokens[$i]['scope_condition'];
                    if (PHP_CODESNIFFER_VERBOSITY > 1) {
                        $type = $this->tokens[$stack_ptr]['type'];
                        echo str_repeat("\t", $level + 1);
                        echo "=> Found scope opener for {$stack_ptr}:{$type}" . PHP_EOL;
                    }
                    $stack_ptr = $this->tokens[$i]['scope_condition'];
                    // If we find a scope opener that has a shared closer,
                    // then we need to go back over the condition map that we
                    // just created and fix ourselves as we just added some
                    // conditions where there was none. This happens for T_CASE
                    // statements that are using the same break statement.
                    if ($last_opener !== null && $this->tokens[$last_opener]['scope_closer'] === $this->tokens[$i]['scope_closer']) {
                        // This opener shares its closer with the previous opener,
                        // but we still need to check if the two openers share their
                        // closer with each other directly (like CASE and DEFAULT)
                        // or if they are just sharing because one doesn't have a
                        // closer (like CASE with no BREAK using a SWITCHes closer).
                        $this_type = $this->tokens[$this->tokens[$i]['scope_condition']]['code'];
                        $opener = $this->tokens[$last_opener]['scope_condition'];
                        $is_shared = isset($this->scope_openers[$this_type]['with'][$this->tokens[$opener]['code']]);
                        reset($this->scope_openers[$this_type]['end']);
                        reset($this->scope_openers[$this->tokens[$opener]['code']]['end']);
                        $same_end = current($this->scope_openers[$this_type]['end']) === current($this->scope_openers[$this->tokens[$opener]['code']]['end']);
                        if ($is_shared === true && $same_end === true) {
                            $bad_token = $opener;
                            if (PHP_CODESNIFFER_VERBOSITY > 1) {
                                $type = $this->tokens[$bad_token]['type'];
                                echo str_repeat("\t", $level + 1);
                                echo "* shared closer, cleaning up {$bad_token}:{$type} *" . PHP_EOL;
                            }
                            for ($x = $this->tokens[$i]['scope_condition']; $x <= $i; $x++) {
                                $old_conditions = $this->tokens[$x]['conditions'];
                                $old_level = $this->tokens[$x]['level'];
                                $this->tokens[$x]['level']--;
                                unset($this->tokens[$x]['conditions'][$bad_token]);
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
                                    $new_level = $this->tokens[$x]['level'];
                                    echo str_repeat("\t", $level + 1);
                                    echo "* cleaned {$x}:{$type} *" . PHP_EOL;
                                    echo str_repeat("\t", $level + 2);
                                    echo "=> level changed from {$old_level} to {$new_level}" . PHP_EOL;
                                    echo str_repeat("\t", $level + 2);
                                    echo "=> conditions changed from {$old_conds} to {$new_conds}" . PHP_EOL;
                                }
                                //end if
                            }
                            //end for
                            unset($conditions[$bad_token]);
                            if (PHP_CODESNIFFER_VERBOSITY > 1) {
                                $type = $this->tokens[$bad_token]['type'];
                                echo str_repeat("\t", $level + 1);
                                echo "* token {$bad_token}:{$type} removed from conditions array *" . PHP_EOL;
                            }
                            unset($openers[$last_opener]);
                            $level--;
                            if (PHP_CODESNIFFER_VERBOSITY > 1) {
                                echo str_repeat("\t", $level + 2);
                                echo '* level decreased *' . PHP_EOL;
                            }
                        }
                        //end if
                    }
                    //end if
                    $level++;
                    if (PHP_CODESNIFFER_VERBOSITY > 1) {
                        echo str_repeat("\t", $level + 1);
                        echo '* level increased *' . PHP_EOL;
                    }
                    $conditions[$stack_ptr] = $this->tokens[$stack_ptr]['code'];
                    if (PHP_CODESNIFFER_VERBOSITY > 1) {
                        $type = $this->tokens[$stack_ptr]['type'];
                        echo str_repeat("\t", $level + 1);
                        echo "* token {$stack_ptr}:{$type} added to conditions array *" . PHP_EOL;
                    }
                    $last_opener = $this->tokens[$i]['scope_opener'];
                    if ($last_opener !== null) {
                        $openers[$last_opener] = $last_opener;
                    }
                } elseif ($last_opener !== null && $this->tokens[$last_opener]['scope_closer'] === $i) {
                    foreach (array_reverse($openers) as $opener) {
                        if ($this->tokens[$opener]['scope_closer'] === $i) {
                            $old_opener = array_pop($openers);
                            if (empty($openers) === false) {
                                $last_opener = array_pop($openers);
                                $openers[$last_opener] = $last_opener;
                            } else {
                                $last_opener = null;
                            }
                            if (PHP_CODESNIFFER_VERBOSITY > 1) {
                                $type = $this->tokens[$old_opener]['type'];
                                echo str_repeat("\t", $level + 1);
                                echo "=> Found scope closer for {$old_opener}:{$type}" . PHP_EOL;
                            }
                            $old_condition = array_pop($conditions);
                            if (PHP_CODESNIFFER_VERBOSITY > 1) {
                                echo str_repeat("\t", $level + 1);
                                echo '* token ' . Util\Tokens::token_name($old_condition) . ' removed from conditions array *' . PHP_EOL;
                            }
                            // Make sure this closer actually belongs to us.
                            // Either the condition also has to think this is the
                            // closer, or it has to allow sharing with us.
                            $condition = $this->tokens[$this->tokens[$i]['scope_condition']]['code'];
                            if ($condition !== $old_condition) {
                                if (isset($this->scope_openers[$old_condition]['with'][$condition]) === false) {
                                    $bad_token = $this->tokens[$old_opener]['scope_condition'];
                                    if (PHP_CODESNIFFER_VERBOSITY > 1) {
                                        $type = Util\Tokens::token_name($old_condition);
                                        echo str_repeat("\t", $level + 1);
                                        echo "* scope closer was bad, cleaning up {$bad_token}:{$type} *" . PHP_EOL;
                                    }
                                    for ($x = $old_opener + 1; $x <= $i; $x++) {
                                        $old_conditions = $this->tokens[$x]['conditions'];
                                        $old_level = $this->tokens[$x]['level'];
                                        $this->tokens[$x]['level']--;
                                        unset($this->tokens[$x]['conditions'][$bad_token]);
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
                                            $new_level = $this->tokens[$x]['level'];
                                            echo str_repeat("\t", $level + 1);
                                            echo "* cleaned {$x}:{$type} *" . PHP_EOL;
                                            echo str_repeat("\t", $level + 2);
                                            echo "=> level changed from {$old_level} to {$new_level}" . PHP_EOL;
                                            echo str_repeat("\t", $level + 2);
                                            echo "=> conditions changed from {$old_conds} to {$new_conds}" . PHP_EOL;
                                        }
                                        //end if
                                    }
                                    //end for
                                }
                                //end if
                            }
                            //end if
                            $level--;
                            if (PHP_CODESNIFFER_VERBOSITY > 1) {
                                echo str_repeat("\t", $level + 2);
                                echo '* level decreased *' . PHP_EOL;
                            }
                            $this->tokens[$i]['level'] = $level;
                            $this->tokens[$i]['conditions'] = $conditions;
                        }
                        //end if
                    }
                    //end foreach
                }
                //end if
            }
            //end if
        }
        //end for
        if (PHP_CODESNIFFER_VERBOSITY > 1) {
            echo "\t*** END LEVEL MAP ***" . PHP_EOL;
        }
    }
    //end createLevelMap()
}
//end class