<?php

declare (strict_types=1);
/**
 * Checks that control structures are defined and indented correctly.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Generic\Sniffs\White_Space;

use Php_code_Sniffer\Config;
use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Scope_Indent_Sniff implements Sniff
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
     * Does the indent need to be exactly right?
     *
     * If TRUE, indent needs to be exactly $indent spaces. If FALSE,
     * indent needs to be at least $indent spaces (but can be more).
     *
     * @var boolean
     */
    public $exact = false;
    /**
     * Should tabs be used for indenting?
     *
     * If TRUE, fixes will be made using tabs instead of spaces.
     * The size of each tab is important, so it should be specified
     * using the --tab-width CLI argument.
     *
     * @var boolean
     */
    public $tab_indent = false;
    /**
     * The --tab-width CLI value that is being used.
     *
     * @var integer
     */
    private $tab_width;
    /**
     * List of tokens not needing to be checked for indentation.
     *
     * Useful to allow Sniffs based on this to easily ignore/skip some
     * tokens from verification. For example, inline HTML sections
     * or PHP open/close tags can escape from here and have their own
     * rules elsewhere.
     *
     * @var int[]
     */
    public $ignore_indentation_tokens = [];
    /**
     * List of tokens not needing to be checked for indentation.
     *
     * This is a cached copy of the public version of this var, which
     * can be set in a ruleset file, and some core ignored tokens.
     *
     * @var int[]
     */
    private $ignore_indentation = [];
    /**
     * Any scope openers that should not cause an indent.
     *
     * @var int[]
     */
    protected $non_indenting_scopes = [];
    /**
     * Show debug output for this sniff.
     *
     * @var boolean
     */
    private $debug = false;
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        if (defined('PHP_CODESNIFFER_IN_TESTS') === true) {
            $this->debug = false;
        }
        return [T_OPEN_TAG];
    }
    //end register()
    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile All the tokens found in the document.
     * @param int                         $stackPtr  The position of the current token
     *                                               in the stack passed in $tokens.
     *
     * @return int
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $debug = Config::get_config_data('scope_indent_debug');
        if ($debug !== null) {
            $this->debug = (bool) $debug;
        }
        if ($this->tab_width === null) {
            if (isset($phpcs_file->config->tab_width) === false || $phpcs_file->config->tab_width === 0) {
                // We have no idea how wide tabs are, so assume 4 spaces for fixing.
                // It shouldn't really matter because indent checks elsewhere in the
                // standard should fix things up.
                $this->tab_width = 4;
            } else {
                $this->tab_width = $phpcs_file->config->tab_width;
            }
        }
        $last_open_tag = $stack_ptr;
        $last_close_tag = null;
        $open_scopes = [];
        $adjustments = [];
        $set_indents = [];
        $disable_exact_stack = [];
        $disable_exact_end = 0;
        $tokens = $phpcs_file->get_tokens();
        $first = $phpcs_file->find_first_on_line(T_INLINE_HTML, $stack_ptr);
        $trimmed = ltrim($tokens[$first]['content']);
        if ($trimmed === '') {
            $current_indent = $tokens[$stack_ptr]['column'] - 1;
        } else {
            $current_indent = strlen($tokens[$first]['content']) - strlen($trimmed);
        }
        if ($this->debug === true) {
            $line = $tokens[$stack_ptr]['line'];
            echo "Start with token {$stack_ptr} on line {$line} with indent {$current_indent}" . PHP_EOL;
        }
        if (empty($this->ignore_indentation) === true) {
            $this->ignore_indentation = [T_INLINE_HTML => true];
            foreach ($this->ignore_indentation_tokens as $token) {
                if (is_int($token) === false) {
                    if (defined($token) === false) {
                        continue;
                    }
                    $token = constant($token);
                }
                $this->ignore_indentation[$token] = true;
            }
        }
        //end if
        $this->exact = (bool) $this->exact;
        $this->tab_indent = (bool) $this->tab_indent;
        $check_annotations = $phpcs_file->config->annotations;
        for ($i = $stack_ptr + 1; $i < $phpcs_file->num_tokens; $i++) {
            if ($i === false) {
                // Something has gone very wrong; maybe a parse error.
                break;
            }
            if ($check_annotations === true && $tokens[$i]['code'] === T_PHPCS_SET && isset($tokens[$i]['sniffCode']) === true && $tokens[$i]['sniffCode'] === 'Generic.WhiteSpace.ScopeIndent' && $tokens[$i]['sniffProperty'] === 'exact') {
                $value = $tokens[$i]['sniffPropertyValue'];
                if ($value === 'true') {
                    $value = true;
                } elseif ($value === 'false') {
                    $value = false;
                } else {
                    $value = (bool) $value;
                }
                $this->exact = $value;
                if ($this->debug === true) {
                    $line = $tokens[$i]['line'];
                    if ($this->exact === true) {
                        $value = 'true';
                    } else {
                        $value = 'false';
                    }
                    echo "* token {$i} on line {$line} set exact flag to {$value} *" . PHP_EOL;
                }
            }
            //end if
            $check_token = null;
            $check_indent = null;
            /*
                Don't check indents exactly between parenthesis or arrays as they
                tend to have custom rules, such as with multi-line function calls
                and control structure conditions.
            */
            $exact = $this->exact;
            if ($tokens[$i]['code'] === T_OPEN_PARENTHESIS && isset($tokens[$i]['parenthesis_closer']) === true) {
                $disable_exact_stack[$tokens[$i]['parenthesis_closer']] = $tokens[$i]['parenthesis_closer'];
                $disable_exact_end = max($disable_exact_end, $tokens[$i]['parenthesis_closer']);
                if ($this->debug === true) {
                    $line = $tokens[$i]['line'];
                    $type = $tokens[$disable_exact_end]['type'];
                    echo "Opening parenthesis found on line {$line}" . PHP_EOL;
                    echo "\t=> disabling exact indent checking until {$disable_exact_end} ({$type})" . PHP_EOL;
                }
            }
            if ($exact === true && $i < $disable_exact_end) {
                $exact = false;
            }
            // Detect line changes and figure out where the indent is.
            if ($tokens[$i]['column'] === 1) {
                $trimmed = ltrim($tokens[$i]['content']);
                if ($trimmed === '') {
                    if (isset($tokens[$i + 1]) === true && $tokens[$i]['line'] === $tokens[$i + 1]['line']) {
                        $check_token = $i + 1;
                        $token_indent = $tokens[$i + 1]['column'] - 1;
                    }
                } else {
                    $check_token = $i;
                    $token_indent = strlen($tokens[$i]['content']) - strlen($trimmed);
                }
            }
            // Closing parenthesis should just be indented to at least
            // the same level as where they were opened (but can be more).
            if ($check_token !== null && $tokens[$check_token]['code'] === T_CLOSE_PARENTHESIS && isset($tokens[$check_token]['parenthesis_opener']) === true || $tokens[$i]['code'] === T_CLOSE_PARENTHESIS && isset($tokens[$i]['parenthesis_opener']) === true) {
                if ($check_token !== null) {
                    $paren_closer = $check_token;
                } else {
                    $paren_closer = $i;
                }
                if ($this->debug === true) {
                    $line = $tokens[$i]['line'];
                    echo "Closing parenthesis found on line {$line}" . PHP_EOL;
                }
                $paren_opener = $tokens[$paren_closer]['parenthesis_opener'];
                if ($tokens[$paren_closer]['line'] !== $tokens[$paren_opener]['line']) {
                    $parens = 0;
                    if (isset($tokens[$paren_closer]['nested_parenthesis']) === true && empty($tokens[$paren_closer]['nested_parenthesis']) === false) {
                        $parens = $tokens[$paren_closer]['nested_parenthesis'];
                        end($parens);
                        $parens = key($parens);
                        if ($this->debug === true) {
                            $line = $tokens[$parens]['line'];
                            echo "\t* token has nested parenthesis {$parens} on line {$line} *" . PHP_EOL;
                        }
                    }
                    $condition = 0;
                    if (isset($tokens[$paren_closer]['conditions']) === true && empty($tokens[$paren_closer]['conditions']) === false && (isset($tokens[$paren_closer]['parenthesis_owner']) === false || $parens > 0)) {
                        $condition = $tokens[$paren_closer]['conditions'];
                        end($condition);
                        $condition = key($condition);
                        if ($this->debug === true) {
                            $line = $tokens[$condition]['line'];
                            $type = $tokens[$condition]['type'];
                            echo "\t* token is inside condition {$condition} ({$type}) on line {$line} *" . PHP_EOL;
                        }
                    }
                    if ($parens > $condition) {
                        if ($this->debug === true) {
                            echo "\t* using parenthesis *" . PHP_EOL;
                        }
                        $paren_opener = $parens;
                        $condition = 0;
                    } elseif ($condition > 0) {
                        if ($this->debug === true) {
                            echo "\t* using condition *" . PHP_EOL;
                        }
                        $paren_opener = $condition;
                        $parens = 0;
                    }
                    $exact = false;
                    $last_open_tag_conditions = array_keys($tokens[$last_open_tag]['conditions']);
                    $last_open_tag_condition = array_pop($last_open_tag_conditions);
                    if ($condition > 0 && $last_open_tag_condition === $condition) {
                        if ($this->debug === true) {
                            echo "\t* open tag is inside condition; using open tag *" . PHP_EOL;
                        }
                        $first = $phpcs_file->find_first_on_line([T_WHITESPACE, T_INLINE_HTML], $last_open_tag, true);
                        if ($this->debug === true) {
                            $line = $tokens[$first]['line'];
                            $type = $tokens[$first]['type'];
                            echo "\t* first token on line {$line} is {$first} ({$type}) *" . PHP_EOL;
                        }
                        $check_indent = $tokens[$first]['column'] - 1;
                        if (isset($adjustments[$condition]) === true) {
                            $check_indent += $adjustments[$condition];
                        }
                        $current_indent = $check_indent;
                        if ($this->debug === true) {
                            $type = $tokens[$last_open_tag]['type'];
                            echo "\t=> checking indent of {$check_indent}; main indent set to {$current_indent} by token {$last_open_tag} ({$type})" . PHP_EOL;
                        }
                    } elseif ($condition > 0 && isset($tokens[$condition]['scope_opener']) === true && isset($set_indents[$tokens[$condition]['scope_opener']]) === true) {
                        $check_indent = $set_indents[$tokens[$condition]['scope_opener']];
                        if (isset($adjustments[$condition]) === true) {
                            $check_indent += $adjustments[$condition];
                        }
                        $current_indent = $check_indent;
                        if ($this->debug === true) {
                            $type = $tokens[$condition]['type'];
                            echo "\t=> checking indent of {$check_indent}; main indent set to {$current_indent} by token {$condition} ({$type})" . PHP_EOL;
                        }
                    } else {
                        $first = $phpcs_file->find_first_on_line(T_WHITESPACE, $paren_opener, true);
                        $check_indent = $tokens[$first]['column'] - 1;
                        if (isset($adjustments[$first]) === true) {
                            $check_indent += $adjustments[$first];
                        }
                        if ($this->debug === true) {
                            $line = $tokens[$first]['line'];
                            $type = $tokens[$first]['type'];
                            echo "\t* first token on line {$line} is {$first} ({$type}) *" . PHP_EOL;
                        }
                        if ($first === $tokens[$paren_closer]['parenthesis_opener'] && $tokens[$first - 1]['line'] === $tokens[$first]['line']) {
                            // This is unlikely to be the start of the statement, so look
                            // back further to find it.
                            $first--;
                            if ($this->debug === true) {
                                $line = $tokens[$first]['line'];
                                $type = $tokens[$first]['type'];
                                echo "\t* first token is the parenthesis opener *" . PHP_EOL;
                                echo "\t* amended first token is {$first} ({$type}) on line {$line} *" . PHP_EOL;
                            }
                        }
                        $prev = $phpcs_file->find_start_of_statement($first, T_COMMA);
                        if ($prev !== $first) {
                            // This is not the start of the statement.
                            if ($this->debug === true) {
                                $line = $tokens[$prev]['line'];
                                $type = $tokens[$prev]['type'];
                                echo "\t* previous is {$type} on line {$line} *" . PHP_EOL;
                            }
                            $first = $phpcs_file->find_first_on_line([T_WHITESPACE, T_INLINE_HTML], $prev, true);
                            if ($first !== false) {
                                $prev = $phpcs_file->find_start_of_statement($first, T_COMMA);
                                $first = $phpcs_file->find_first_on_line([T_WHITESPACE, T_INLINE_HTML], $prev, true);
                            } else {
                                $first = $prev;
                            }
                            if ($this->debug === true) {
                                $line = $tokens[$first]['line'];
                                $type = $tokens[$first]['type'];
                                echo "\t* amended first token is {$first} ({$type}) on line {$line} *" . PHP_EOL;
                            }
                        }
                        //end if
                        if (isset($tokens[$first]['scope_closer']) === true && $tokens[$first]['scope_closer'] === $first) {
                            if ($this->debug === true) {
                                echo "\t* first token is a scope closer *" . PHP_EOL;
                            }
                            if (isset($tokens[$first]['scope_condition']) === true) {
                                $scope_closer = $first;
                                $first = $phpcs_file->find_first_on_line(T_WHITESPACE, $tokens[$scope_closer]['scope_condition'], true);
                                $current_indent = $tokens[$first]['column'] - 1;
                                if (isset($adjustments[$first]) === true) {
                                    $current_indent += $adjustments[$first];
                                }
                                // Make sure it is divisible by our expected indent.
                                if ($tokens[$tokens[$scope_closer]['scope_condition']]['code'] !== T_CLOSURE) {
                                    $current_indent = (int) (ceil($current_indent / $this->indent) * $this->indent);
                                }
                                $set_indents[$first] = $current_indent;
                                if ($this->debug === true) {
                                    $type = $tokens[$first]['type'];
                                    echo "\t=> indent set to {$current_indent} by token {$first} ({$type})" . PHP_EOL;
                                }
                            }
                            //end if
                        } else {
                            // Don't force current indent to be divisible because there could be custom
                            // rules in place between parenthesis, such as with arrays.
                            $current_indent = $tokens[$first]['column'] - 1;
                            if (isset($adjustments[$first]) === true) {
                                $current_indent += $adjustments[$first];
                            }
                            $set_indents[$first] = $current_indent;
                            if ($this->debug === true) {
                                $type = $tokens[$first]['type'];
                                echo "\t=> checking indent of {$check_indent}; main indent set to {$current_indent} by token {$first} ({$type})" . PHP_EOL;
                            }
                        }
                        //end if
                    }
                    //end if
                } elseif ($this->debug === true) {
                    echo "\t * ignoring single-line definition *" . PHP_EOL;
                }
                //end if
            }
            //end if
            // Closing short array bracket should just be indented to at least
            // the same level as where it was opened (but can be more).
            if ($tokens[$i]['code'] === T_CLOSE_SHORT_ARRAY || $check_token !== null && $tokens[$check_token]['code'] === T_CLOSE_SHORT_ARRAY) {
                if ($check_token !== null) {
                    $array_closer = $check_token;
                } else {
                    $array_closer = $i;
                }
                if ($this->debug === true) {
                    $line = $tokens[$array_closer]['line'];
                    echo "Closing short array bracket found on line {$line}" . PHP_EOL;
                }
                $array_opener = $tokens[$array_closer]['bracket_opener'];
                if ($tokens[$array_closer]['line'] !== $tokens[$array_opener]['line']) {
                    $first = $phpcs_file->find_first_on_line(T_WHITESPACE, $array_opener, true);
                    $exact = false;
                    if ($this->debug === true) {
                        $line = $tokens[$first]['line'];
                        $type = $tokens[$first]['type'];
                        echo "\t* first token on line {$line} is {$first} ({$type}) *" . PHP_EOL;
                    }
                    if ($first === $tokens[$array_closer]['bracket_opener']) {
                        // This is unlikely to be the start of the statement, so look
                        // back further to find it.
                        $first--;
                    }
                    $prev = $phpcs_file->find_start_of_statement($first, [T_COMMA, T_DOUBLE_ARROW]);
                    if ($prev !== $first) {
                        // This is not the start of the statement.
                        if ($this->debug === true) {
                            $line = $tokens[$prev]['line'];
                            $type = $tokens[$prev]['type'];
                            echo "\t* previous is {$type} on line {$line} *" . PHP_EOL;
                        }
                        $first = $phpcs_file->find_first_on_line(T_WHITESPACE, $prev, true);
                        $prev = $phpcs_file->find_start_of_statement($first, [T_COMMA, T_DOUBLE_ARROW]);
                        $first = $phpcs_file->find_first_on_line(T_WHITESPACE, $prev, true);
                        if ($this->debug === true) {
                            $line = $tokens[$first]['line'];
                            $type = $tokens[$first]['type'];
                            echo "\t* amended first token is {$first} ({$type}) on line {$line} *" . PHP_EOL;
                        }
                    } elseif ($tokens[$first]['code'] === T_WHITESPACE) {
                        $first = $phpcs_file->find_next(T_WHITESPACE, $first + 1, null, true);
                    }
                    $check_indent = $tokens[$first]['column'] - 1;
                    if (isset($adjustments[$first]) === true) {
                        $check_indent += $adjustments[$first];
                    }
                    if (isset($tokens[$first]['scope_closer']) === true && $tokens[$first]['scope_closer'] === $first) {
                        // The first token is a scope closer and would have already
                        // been processed and set the indent level correctly, so
                        // don't adjust it again.
                        if ($this->debug === true) {
                            echo "\t* first token is a scope closer; ignoring closing short array bracket *" . PHP_EOL;
                        }
                        if (isset($set_indents[$first]) === true) {
                            $current_indent = $set_indents[$first];
                            if ($this->debug === true) {
                                echo "\t=> indent reset to {$current_indent}" . PHP_EOL;
                            }
                        }
                    } else {
                        // Don't force current indent to be divisible because there could be custom
                        // rules in place for arrays.
                        $current_indent = $tokens[$first]['column'] - 1;
                        if (isset($adjustments[$first]) === true) {
                            $current_indent += $adjustments[$first];
                        }
                        $set_indents[$first] = $current_indent;
                        if ($this->debug === true) {
                            $type = $tokens[$first]['type'];
                            echo "\t=> checking indent of {$check_indent}; main indent set to {$current_indent} by token {$first} ({$type})" . PHP_EOL;
                        }
                    }
                    //end if
                } elseif ($this->debug === true) {
                    echo "\t * ignoring single-line definition *" . PHP_EOL;
                }
                //end if
            }
            //end if
            // Adjust lines within scopes while auto-fixing.
            if ($check_token !== null && $exact === false && (empty($tokens[$check_token]['conditions']) === false || isset($tokens[$check_token]['scope_opener']) === true && $tokens[$check_token]['scope_opener'] === $check_token)) {
                if (empty($tokens[$check_token]['conditions']) === false) {
                    $condition = $tokens[$check_token]['conditions'];
                    end($condition);
                    $condition = key($condition);
                } else {
                    $condition = $tokens[$check_token]['scope_condition'];
                }
                $first = $phpcs_file->find_first_on_line(T_WHITESPACE, $condition, true);
                if (isset($adjustments[$first]) === true && ($adjustments[$first] < 0 && $token_indent > $current_indent || $adjustments[$first] > 0 && $token_indent < $current_indent)) {
                    $length = $token_indent + $adjustments[$first];
                    // When fixing, we're going to adjust the indent of this line
                    // here automatically, so use this new padding value when
                    // comparing the expected padding to the actual padding.
                    if ($phpcs_file->fixer->enabled === true) {
                        $token_indent = $length;
                        $this->adjust_indent($phpcs_file, $check_token, $length, $adjustments[$first]);
                    }
                    if ($this->debug === true) {
                        $line = $tokens[$check_token]['line'];
                        $type = $tokens[$check_token]['type'];
                        echo "Indent adjusted to {$length} for {$type} on line {$line}" . PHP_EOL;
                    }
                    $adjustments[$check_token] = $adjustments[$first];
                    if ($this->debug === true) {
                        $line = $tokens[$check_token]['line'];
                        $type = $tokens[$check_token]['type'];
                        echo "\t=> add adjustment of " . $adjustments[$check_token] . " for token {$check_token} ({$type}) on line {$line}" . PHP_EOL;
                    }
                }
                //end if
            }
            //end if
            // Scope closers reset the required indent to the same level as the opening condition.
            if ($check_token !== null && (isset($open_scopes[$check_token]) === true || isset($tokens[$check_token]['scope_condition']) === true && isset($tokens[$check_token]['scope_closer']) === true && $tokens[$check_token]['scope_closer'] === $check_token && $tokens[$check_token]['line'] !== $tokens[$tokens[$check_token]['scope_opener']]['line']) || $check_token === null && isset($open_scopes[$i]) === true) {
                if ($this->debug === true) {
                    if ($check_token === null) {
                        $type = $tokens[$tokens[$i]['scope_condition']]['type'];
                        $line = $tokens[$i]['line'];
                    } else {
                        $type = $tokens[$tokens[$check_token]['scope_condition']]['type'];
                        $line = $tokens[$check_token]['line'];
                    }
                    echo "Close scope ({$type}) on line {$line}" . PHP_EOL;
                }
                $scope_closer = $check_token;
                if ($scope_closer === null) {
                    $scope_closer = $i;
                }
                $condition_token = array_pop($open_scopes);
                if ($this->debug === true) {
                    $line = $tokens[$condition_token]['line'];
                    $type = $tokens[$condition_token]['type'];
                    echo "\t=> removed open scope {$condition_token} ({$type}) on line {$line}" . PHP_EOL;
                }
                if (isset($tokens[$scope_closer]['scope_condition']) === true) {
                    $first = $phpcs_file->find_first_on_line([T_WHITESPACE, T_INLINE_HTML], $tokens[$scope_closer]['scope_condition'], true);
                    if ($this->debug === true) {
                        $line = $tokens[$first]['line'];
                        $type = $tokens[$first]['type'];
                        echo "\t* first token is {$first} ({$type}) on line {$line} *" . PHP_EOL;
                    }
                    while ($tokens[$first]['code'] === T_CONSTANT_ENCAPSED_STRING && $tokens[$first - 1]['code'] === T_CONSTANT_ENCAPSED_STRING) {
                        $first = $phpcs_file->find_first_on_line(T_WHITESPACE, $first - 1, true);
                        if ($this->debug === true) {
                            $line = $tokens[$first]['line'];
                            $type = $tokens[$first]['type'];
                            echo "\t* found multi-line string; amended first token is {$first} ({$type}) on line {$line} *" . PHP_EOL;
                        }
                    }
                    $current_indent = $tokens[$first]['column'] - 1;
                    if (isset($adjustments[$first]) === true) {
                        $current_indent += $adjustments[$first];
                    }
                    $set_indents[$scope_closer] = $current_indent;
                    if ($this->debug === true) {
                        $type = $tokens[$scope_closer]['type'];
                        echo "\t=> indent set to {$current_indent} by token {$scope_closer} ({$type})" . PHP_EOL;
                    }
                    // We only check the indent of scope closers if they are
                    // curly braces because other constructs tend to have different rules.
                    if ($tokens[$scope_closer]['code'] === T_CLOSE_CURLY_BRACKET) {
                        $exact = true;
                    } else {
                        $check_token = null;
                    }
                }
                //end if
            }
            //end if
            // Handle scope for JS object notation.
            if ($phpcs_file->tokenizer_type === 'JS' && ($check_token !== null && $tokens[$check_token]['code'] === T_CLOSE_OBJECT && $tokens[$check_token]['line'] !== $tokens[$tokens[$check_token]['bracket_opener']]['line'] || $check_token === null && $tokens[$i]['code'] === T_CLOSE_OBJECT && $tokens[$i]['line'] !== $tokens[$tokens[$i]['bracket_opener']]['line'])) {
                if ($this->debug === true) {
                    $line = $tokens[$i]['line'];
                    echo "Close JS object on line {$line}" . PHP_EOL;
                }
                $scope_closer = $check_token;
                if ($scope_closer === null) {
                    $scope_closer = $i;
                } else {
                    $condition_token = array_pop($open_scopes);
                    if ($this->debug === true) {
                        $line = $tokens[$condition_token]['line'];
                        $type = $tokens[$condition_token]['type'];
                        echo "\t=> removed open scope {$condition_token} ({$type}) on line {$line}" . PHP_EOL;
                    }
                }
                $parens = 0;
                if (isset($tokens[$scope_closer]['nested_parenthesis']) === true && empty($tokens[$scope_closer]['nested_parenthesis']) === false) {
                    $parens = $tokens[$scope_closer]['nested_parenthesis'];
                    end($parens);
                    $parens = key($parens);
                    if ($this->debug === true) {
                        $line = $tokens[$parens]['line'];
                        echo "\t* token has nested parenthesis {$parens} on line {$line} *" . PHP_EOL;
                    }
                }
                $condition = 0;
                if (isset($tokens[$scope_closer]['conditions']) === true && empty($tokens[$scope_closer]['conditions']) === false) {
                    $condition = $tokens[$scope_closer]['conditions'];
                    end($condition);
                    $condition = key($condition);
                    if ($this->debug === true) {
                        $line = $tokens[$condition]['line'];
                        $type = $tokens[$condition]['type'];
                        echo "\t* token is inside condition {$condition} ({$type}) on line {$line} *" . PHP_EOL;
                    }
                }
                if ($parens > $condition) {
                    if ($this->debug === true) {
                        echo "\t* using parenthesis *" . PHP_EOL;
                    }
                    $first = $phpcs_file->find_first_on_line(T_WHITESPACE, $parens, true);
                    $condition = 0;
                } elseif ($condition > 0) {
                    if ($this->debug === true) {
                        echo "\t* using condition *" . PHP_EOL;
                    }
                    $first = $phpcs_file->find_first_on_line(T_WHITESPACE, $condition, true);
                    $parens = 0;
                } else {
                    if ($this->debug === true) {
                        $line = $tokens[$tokens[$scope_closer]['bracket_opener']]['line'];
                        echo "\t* token is not in parenthesis or condition; using opener on line {$line} *" . PHP_EOL;
                    }
                    $first = $phpcs_file->find_first_on_line(T_WHITESPACE, $tokens[$scope_closer]['bracket_opener'], true);
                }
                //end if
                $current_indent = $tokens[$first]['column'] - 1;
                if (isset($adjustments[$first]) === true) {
                    $current_indent += $adjustments[$first];
                }
                if ($parens > 0 || $condition > 0) {
                    $check_indent = $tokens[$first]['column'] - 1;
                    if (isset($adjustments[$first]) === true) {
                        $check_indent += $adjustments[$first];
                    }
                    if ($condition > 0) {
                        $check_indent += $this->indent;
                        $current_indent += $this->indent;
                        $exact = true;
                    }
                } else {
                    $check_indent = $current_indent;
                }
                // Make sure it is divisible by our expected indent.
                $current_indent = (int) (ceil($current_indent / $this->indent) * $this->indent);
                $check_indent = (int) (ceil($check_indent / $this->indent) * $this->indent);
                $set_indents[$first] = $current_indent;
                if ($this->debug === true) {
                    $type = $tokens[$first]['type'];
                    echo "\t=> checking indent of {$check_indent}; main indent set to {$current_indent} by token {$first} ({$type})" . PHP_EOL;
                }
            }
            //end if
            if ($check_token !== null && isset(Tokens::$scope_openers[$tokens[$check_token]['code']]) === true && in_array($tokens[$check_token]['code'], $this->non_indenting_scopes, true) === false && isset($tokens[$check_token]['scope_opener']) === true) {
                $exact = true;
                if ($disable_exact_end > $check_token) {
                    foreach ($disable_exact_stack as $disable_exact_stack_end) {
                        if ($disable_exact_stack_end < $check_token) {
                            continue;
                        }
                        if ($tokens[$check_token]['conditions'] === $tokens[$disable_exact_stack_end]['conditions']) {
                            $exact = false;
                            break;
                        }
                    }
                }
                $last_opener = null;
                if (empty($open_scopes) === false) {
                    end($open_scopes);
                    $last_opener = current($open_scopes);
                }
                // A scope opener that shares a closer with another token (like multiple
                // CASEs using the same BREAK) needs to reduce the indent level so its
                // indent is checked correctly. It will then increase the indent again
                // (as all openers do) after being checked.
                if ($last_opener !== null && isset($tokens[$last_opener]['scope_closer']) === true && $tokens[$last_opener]['level'] === $tokens[$check_token]['level'] && $tokens[$last_opener]['scope_closer'] === $tokens[$check_token]['scope_closer']) {
                    $current_indent -= $this->indent;
                    $set_indents[$last_opener] = $current_indent;
                    if ($this->debug === true) {
                        $line = $tokens[$i]['line'];
                        $type = $tokens[$last_opener]['type'];
                        echo "Shared closer found on line {$line}" . PHP_EOL;
                        echo "\t=> indent set to {$current_indent} by token {$last_opener} ({$type})" . PHP_EOL;
                    }
                }
                if ($tokens[$check_token]['code'] === T_CLOSURE && $token_indent > $current_indent) {
                    // The opener is indented more than needed, which is fine.
                    // But just check that it is divisible by our expected indent.
                    $check_indent = (int) (ceil($token_indent / $this->indent) * $this->indent);
                    $exact = false;
                    if ($this->debug === true) {
                        $line = $tokens[$i]['line'];
                        echo "Closure found on line {$line}" . PHP_EOL;
                        echo "\t=> checking indent of {$check_indent}; main indent remains at {$current_indent}" . PHP_EOL;
                    }
                }
            }
            //end if
            // Method prefix indentation has to be exact or else it will break
            // the rest of the function declaration, and potentially future ones.
            if ($check_token !== null && isset(Tokens::$method_prefixes[$tokens[$check_token]['code']]) === true && $tokens[$check_token + 1]['code'] !== T_DOUBLE_COLON) {
                $next = $phpcs_file->find_next(Tokens::$empty_tokens, $check_token + 1, null, true);
                if ($next === false || $tokens[$next]['code'] !== T_CLOSURE && $tokens[$next]['code'] !== T_VARIABLE && $tokens[$next]['code'] !== T_FN) {
                    $is_method_prefix = true;
                    if (isset($tokens[$check_token]['nested_parenthesis']) === true) {
                        $parenthesis = array_keys($tokens[$check_token]['nested_parenthesis']);
                        $deepest_open = array_pop($parenthesis);
                        if (isset($tokens[$deepest_open]['parenthesis_owner']) === true && $tokens[$tokens[$deepest_open]['parenthesis_owner']]['code'] === T_FUNCTION) {
                            // This is constructor property promotion and not a method prefix.
                            $is_method_prefix = false;
                        }
                    }
                    if ($is_method_prefix === true) {
                        if ($this->debug === true) {
                            $line = $tokens[$check_token]['line'];
                            $type = $tokens[$check_token]['type'];
                            echo "\t* method prefix ({$type}) found on line {$line}; indent set to exact *" . PHP_EOL;
                        }
                        $exact = true;
                    }
                }
                //end if
            }
            //end if
            // JS property indentation has to be exact or else if will break
            // things like function and object indentation.
            if ($check_token !== null && $tokens[$check_token]['code'] === T_PROPERTY) {
                $exact = true;
            }
            // Open PHP tags needs to be indented to exact column positions
            // so they don't cause problems with indent checks for the code
            // within them, but they don't need to line up with the current indent
            // in most cases.
            if ($check_token !== null && ($tokens[$check_token]['code'] === T_OPEN_TAG || $tokens[$check_token]['code'] === T_OPEN_TAG_WITH_ECHO)) {
                $check_indent = $tokens[$check_token]['column'] - 1;
                // If we are re-opening a block that was closed in the same
                // scope as us, then reset the indent back to what the scope opener
                // set instead of using whatever indent this open tag has set.
                if (empty($tokens[$check_token]['conditions']) === false) {
                    $close = $phpcs_file->find_previous(T_CLOSE_TAG, $check_token - 1);
                    if ($close !== false && $tokens[$check_token]['conditions'] === $tokens[$close]['conditions']) {
                        $conditions = array_keys($tokens[$check_token]['conditions']);
                        $last_condition = array_pop($conditions);
                        $last_opener = $tokens[$last_condition]['scope_opener'];
                        $last_closer = $tokens[$last_condition]['scope_closer'];
                        if ($tokens[$last_closer]['line'] !== $tokens[$check_token]['line'] && isset($set_indents[$last_opener]) === true) {
                            $check_indent = $set_indents[$last_opener];
                        }
                    }
                }
            }
            //end if
            // Close tags needs to be indented to exact column positions.
            if ($check_token !== null && $tokens[$check_token]['code'] === T_CLOSE_TAG) {
                $exact = true;
                $check_indent = $current_indent;
                $check_indent = (int) (ceil($check_indent / $this->indent) * $this->indent);
            }
            // Special case for ELSE statements that are not on the same
            // line as the previous IF statements closing brace. They still need
            // to have the same indent or it will break code after the block.
            if ($check_token !== null && $tokens[$check_token]['code'] === T_ELSE) {
                $exact = true;
            }
            // Don't perform strict checking on chained method calls since they
            // are often covered by custom rules.
            if ($check_token !== null && ($tokens[$check_token]['code'] === T_OBJECT_OPERATOR || $tokens[$check_token]['code'] === T_NULLSAFE_OBJECT_OPERATOR) && $exact === true) {
                $exact = false;
            }
            if ($check_indent === null) {
                $check_indent = $current_indent;
            }
            /*
                The indent of the line is checked by the following IF block.
            
                Up until now, we've just been figuring out what the indent
                of this line should be.
            
                After this IF block, we adjust the indent again for
                the checking of future lines
            */
            if ($check_token !== null && isset($this->ignore_indentation[$tokens[$check_token]['code']]) === false && ($token_indent !== $check_indent && $exact === true || $token_indent < $check_indent && $exact === false)) {
                $type = 'IncorrectExact';
                $error = 'Line indented incorrectly; expected ';
                if ($exact === false) {
                    $error .= 'at least ';
                    $type = 'Incorrect';
                }
                if ($this->tab_indent === true) {
                    $expected_tabs = floor($check_indent / $this->tab_width);
                    $found_tabs = floor($token_indent / $this->tab_width);
                    $found_spaces = $token_indent - $found_tabs * $this->tab_width;
                    if ($found_spaces > 0) {
                        if ($found_tabs > 0) {
                            $error .= '%s tabs, found %s tabs and %s spaces';
                            $data = [$expected_tabs, $found_tabs, $found_spaces];
                        } else {
                            $error .= '%s tabs, found %s spaces';
                            $data = [$expected_tabs, $found_spaces];
                        }
                    } else {
                        $error .= '%s tabs, found %s';
                        $data = [$expected_tabs, $found_tabs];
                    }
                    //end if
                } else {
                    $error .= '%s spaces, found %s';
                    $data = [$check_indent, $token_indent];
                }
                //end if
                if ($this->debug === true) {
                    $line = $tokens[$check_token]['line'];
                    $message = vsprintf($error, $data);
                    echo "[Line {$line}] {$message}" . PHP_EOL;
                }
                // Assume the change would be applied and continue
                // checking indents under this assumption. This gives more
                // technically accurate error messages.
                $adjustments[$check_token] = $check_indent - $token_indent;
                $fix = $phpcs_file->add_fixable_error($error, $check_token, $type, $data);
                if ($fix === true || $this->debug === true) {
                    $accepted = $this->adjust_indent($phpcs_file, $check_token, $check_indent, $check_indent - $token_indent);
                    if ($accepted === true && $this->debug === true) {
                        $line = $tokens[$check_token]['line'];
                        $type = $tokens[$check_token]['type'];
                        echo "\t=> add adjustment of " . $adjustments[$check_token] . " for token {$check_token} ({$type}) on line {$line}" . PHP_EOL;
                    }
                }
            }
            //end if
            if ($check_token !== null) {
                $i = $check_token;
            }
            // Don't check indents exactly between arrays as they tend to have custom rules.
            if ($tokens[$i]['code'] === T_OPEN_SHORT_ARRAY) {
                $disable_exact_stack[$tokens[$i]['bracket_closer']] = $tokens[$i]['bracket_closer'];
                $disable_exact_end = max($disable_exact_end, $tokens[$i]['bracket_closer']);
                if ($this->debug === true) {
                    $line = $tokens[$i]['line'];
                    $type = $tokens[$disable_exact_end]['type'];
                    $end_line = $tokens[$disable_exact_end]['line'];
                    echo "Opening short array bracket found on line {$line}" . PHP_EOL;
                    if ($disable_exact_end === $tokens[$i]['bracket_closer']) {
                        echo "\t=> disabling exact indent checking until {$disable_exact_end} ({$type}) on line {$end_line}" . PHP_EOL;
                    } else {
                        echo "\t=> continuing to disable exact indent checking until {$disable_exact_end} ({$type}) on line {$end_line}" . PHP_EOL;
                    }
                }
            }
            // Completely skip here/now docs as the indent is a part of the
            // content itself.
            if ($tokens[$i]['code'] === T_START_HEREDOC || $tokens[$i]['code'] === T_START_NOWDOC) {
                if ($this->debug === true) {
                    $line = $tokens[$i]['line'];
                    echo "Here/nowdoc found on line {$line}" . PHP_EOL;
                }
                $i = $phpcs_file->find_next([T_END_HEREDOC, T_END_NOWDOC], $i + 1);
                $next = $phpcs_file->find_next(Tokens::$empty_tokens, $i + 1, null, true);
                if ($tokens[$next]['code'] === T_COMMA) {
                    $i = $next;
                }
                if ($this->debug === true) {
                    $line = $tokens[$i]['line'];
                    $type = $tokens[$i]['type'];
                    echo "\t* skipping to token {$i} ({$type}) on line {$line} *" . PHP_EOL;
                }
                continue;
            }
            //end if
            // Completely skip multi-line strings as the indent is a part of the
            // content itself.
            if ($tokens[$i]['code'] === T_CONSTANT_ENCAPSED_STRING || $tokens[$i]['code'] === T_DOUBLE_QUOTED_STRING) {
                $i = $phpcs_file->find_next($tokens[$i]['code'], $i + 1, null, true);
                $i--;
                continue;
            }
            // Completely skip doc comments as they tend to have complex
            // indentation rules.
            if ($tokens[$i]['code'] === T_DOC_COMMENT_OPEN_TAG) {
                $i = $tokens[$i]['comment_closer'];
                continue;
            }
            // Open tags reset the indent level.
            if ($tokens[$i]['code'] === T_OPEN_TAG || $tokens[$i]['code'] === T_OPEN_TAG_WITH_ECHO) {
                if ($this->debug === true) {
                    $line = $tokens[$i]['line'];
                    echo "Open PHP tag found on line {$line}" . PHP_EOL;
                }
                if ($check_token === null) {
                    $first = $phpcs_file->find_first_on_line(T_WHITESPACE, $i, true);
                    $current_indent = strlen($tokens[$first]['content']) - strlen(ltrim($tokens[$first]['content']));
                } else {
                    $current_indent = $tokens[$i]['column'] - 1;
                }
                $last_open_tag = $i;
                if (isset($adjustments[$i]) === true) {
                    $current_indent += $adjustments[$i];
                }
                // Make sure it is divisible by our expected indent.
                $current_indent = (int) (ceil($current_indent / $this->indent) * $this->indent);
                $set_indents[$i] = $current_indent;
                if ($this->debug === true) {
                    $type = $tokens[$i]['type'];
                    echo "\t=> indent set to {$current_indent} by token {$i} ({$type})" . PHP_EOL;
                }
                continue;
            }
            //end if
            // Close tags reset the indent level, unless they are closing a tag
            // opened on the same line.
            if ($tokens[$i]['code'] === T_CLOSE_TAG) {
                if ($this->debug === true) {
                    $line = $tokens[$i]['line'];
                    echo "Close PHP tag found on line {$line}" . PHP_EOL;
                }
                if ($tokens[$last_open_tag]['line'] !== $tokens[$i]['line']) {
                    $current_indent = $tokens[$i]['column'] - 1;
                    $last_close_tag = $i;
                } else if ($last_close_tag === null) {
                    $current_indent = 0;
                } else {
                    $current_indent = $tokens[$last_close_tag]['column'] - 1;
                }
                if (isset($adjustments[$i]) === true) {
                    $current_indent += $adjustments[$i];
                }
                // Make sure it is divisible by our expected indent.
                $current_indent = (int) (ceil($current_indent / $this->indent) * $this->indent);
                $set_indents[$i] = $current_indent;
                if ($this->debug === true) {
                    $type = $tokens[$i]['type'];
                    echo "\t=> indent set to {$current_indent} by token {$i} ({$type})" . PHP_EOL;
                }
                continue;
            }
            //end if
            // Anon classes and functions set the indent based on their own indent level.
            if ($tokens[$i]['code'] === T_CLOSURE || $tokens[$i]['code'] === T_ANON_CLASS) {
                $closer = $tokens[$i]['scope_closer'];
                if ($tokens[$i]['line'] === $tokens[$closer]['line']) {
                    if ($this->debug === true) {
                        $type = str_replace('_', ' ', strtolower(substr($tokens[$i]['type'], 2)));
                        $line = $tokens[$i]['line'];
                        echo "* ignoring single-line {$type} on line {$line} *" . PHP_EOL;
                    }
                    $i = $closer;
                    continue;
                }
                if ($this->debug === true) {
                    $type = str_replace('_', ' ', strtolower(substr($tokens[$i]['type'], 2)));
                    $line = $tokens[$i]['line'];
                    echo "Open {$type} on line {$line}" . PHP_EOL;
                }
                $first = $phpcs_file->find_first_on_line(T_WHITESPACE, $i, true);
                if ($this->debug === true) {
                    $line = $tokens[$first]['line'];
                    $type = $tokens[$first]['type'];
                    echo "\t* first token is {$first} ({$type}) on line {$line} *" . PHP_EOL;
                }
                while ($tokens[$first]['code'] === T_CONSTANT_ENCAPSED_STRING && $tokens[$first - 1]['code'] === T_CONSTANT_ENCAPSED_STRING) {
                    $first = $phpcs_file->find_first_on_line(T_WHITESPACE, $first - 1, true);
                    if ($this->debug === true) {
                        $line = $tokens[$first]['line'];
                        $type = $tokens[$first]['type'];
                        echo "\t* found multi-line string; amended first token is {$first} ({$type}) on line {$line} *" . PHP_EOL;
                    }
                }
                $current_indent = $tokens[$first]['column'] - 1 + $this->indent;
                $open_scopes[$tokens[$i]['scope_closer']] = $tokens[$i]['scope_condition'];
                if ($this->debug === true) {
                    $closer_token = $tokens[$i]['scope_closer'];
                    $closer_line = $tokens[$closer_token]['line'];
                    $closer_type = $tokens[$closer_token]['type'];
                    $condition_token = $tokens[$i]['scope_condition'];
                    $condition_line = $tokens[$condition_token]['line'];
                    $condition_type = $tokens[$condition_token]['type'];
                    echo "\t=> added open scope {$closer_token} ({$closer_type}) on line {$closer_line}, pointing to condition {$condition_token} ({$condition_type}) on line {$condition_line}" . PHP_EOL;
                }
                if (isset($adjustments[$first]) === true) {
                    $current_indent += $adjustments[$first];
                }
                // Make sure it is divisible by our expected indent.
                $current_indent = (int) (floor($current_indent / $this->indent) * $this->indent);
                $i = $tokens[$i]['scope_opener'];
                $set_indents[$i] = $current_indent;
                if ($this->debug === true) {
                    $type = $tokens[$i]['type'];
                    echo "\t=> indent set to {$current_indent} by token {$i} ({$type})" . PHP_EOL;
                }
                continue;
            }
            //end if
            // Scope openers increase the indent level.
            if (isset($tokens[$i]['scope_condition']) === true && isset($tokens[$i]['scope_opener']) === true && $tokens[$i]['scope_opener'] === $i) {
                $closer = $tokens[$i]['scope_closer'];
                if ($tokens[$i]['line'] === $tokens[$closer]['line']) {
                    if ($this->debug === true) {
                        $line = $tokens[$i]['line'];
                        $type = $tokens[$i]['type'];
                        echo "* ignoring single-line {$type} on line {$line} *" . PHP_EOL;
                    }
                    $i = $closer;
                    continue;
                }
                $condition = $tokens[$tokens[$i]['scope_condition']]['code'];
                if ($condition === T_FN) {
                    if ($this->debug === true) {
                        $line = $tokens[$tokens[$i]['scope_condition']]['line'];
                        echo "* ignoring arrow function on line {$line} *" . PHP_EOL;
                    }
                    $i = $closer;
                    continue;
                }
                if (isset(Tokens::$scope_openers[$condition]) === true && in_array($condition, $this->non_indenting_scopes, true) === false) {
                    if ($this->debug === true) {
                        $line = $tokens[$i]['line'];
                        $type = $tokens[$tokens[$i]['scope_condition']]['type'];
                        echo "Open scope ({$type}) on line {$line}" . PHP_EOL;
                    }
                    $current_indent += $this->indent;
                    $set_indents[$i] = $current_indent;
                    $open_scopes[$tokens[$i]['scope_closer']] = $tokens[$i]['scope_condition'];
                    if ($this->debug === true) {
                        $closer_token = $tokens[$i]['scope_closer'];
                        $closer_line = $tokens[$closer_token]['line'];
                        $closer_type = $tokens[$closer_token]['type'];
                        $condition_token = $tokens[$i]['scope_condition'];
                        $condition_line = $tokens[$condition_token]['line'];
                        $condition_type = $tokens[$condition_token]['type'];
                        echo "\t=> added open scope {$closer_token} ({$closer_type}) on line {$closer_line}, pointing to condition {$condition_token} ({$condition_type}) on line {$condition_line}" . PHP_EOL;
                        $type = $tokens[$i]['type'];
                        echo "\t=> indent set to {$current_indent} by token {$i} ({$type})" . PHP_EOL;
                    }
                    continue;
                }
                //end if
            }
            //end if
            // JS objects set the indent level.
            if ($phpcs_file->tokenizer_type === 'JS' && $tokens[$i]['code'] === T_OBJECT) {
                $closer = $tokens[$i]['bracket_closer'];
                if ($tokens[$i]['line'] === $tokens[$closer]['line']) {
                    if ($this->debug === true) {
                        $line = $tokens[$i]['line'];
                        echo "* ignoring single-line JS object on line {$line} *" . PHP_EOL;
                    }
                    $i = $closer;
                    continue;
                }
                if ($this->debug === true) {
                    $line = $tokens[$i]['line'];
                    echo "Open JS object on line {$line}" . PHP_EOL;
                }
                $first = $phpcs_file->find_first_on_line(T_WHITESPACE, $i, true);
                $current_indent = $tokens[$first]['column'] - 1 + $this->indent;
                if (isset($adjustments[$first]) === true) {
                    $current_indent += $adjustments[$first];
                }
                // Make sure it is divisible by our expected indent.
                $current_indent = (int) (ceil($current_indent / $this->indent) * $this->indent);
                $set_indents[$first] = $current_indent;
                if ($this->debug === true) {
                    $type = $tokens[$first]['type'];
                    echo "\t=> indent set to {$current_indent} by token {$first} ({$type})" . PHP_EOL;
                }
                continue;
            }
            //end if
            // Closing an anon class, closure, or match.
            // Each may be returned, which can confuse control structures that
            // use return as a closer, like CASE statements.
            if (isset($tokens[$i]['scope_condition']) === true && $tokens[$i]['scope_closer'] === $i && ($tokens[$tokens[$i]['scope_condition']]['code'] === T_CLOSURE || $tokens[$tokens[$i]['scope_condition']]['code'] === T_ANON_CLASS || $tokens[$tokens[$i]['scope_condition']]['code'] === T_MATCH)) {
                if ($this->debug === true) {
                    $type = str_replace('_', ' ', strtolower(substr($tokens[$tokens[$i]['scope_condition']]['type'], 2)));
                    $line = $tokens[$i]['line'];
                    echo "Close {$type} on line {$line}" . PHP_EOL;
                }
                $prev = false;
                $object = 0;
                if ($phpcs_file->tokenizer_type === 'JS') {
                    $conditions = $tokens[$i]['conditions'];
                    krsort($conditions, SORT_NUMERIC);
                    foreach ($conditions as $token => $condition) {
                        if ($condition === T_OBJECT) {
                            $object = $token;
                            break;
                        }
                    }
                    if ($this->debug === true && $object !== 0) {
                        $line = $tokens[$object]['line'];
                        echo "\t* token is inside JS object {$object} on line {$line} *" . PHP_EOL;
                    }
                }
                $parens = 0;
                if (isset($tokens[$i]['nested_parenthesis']) === true && empty($tokens[$i]['nested_parenthesis']) === false) {
                    $parens = $tokens[$i]['nested_parenthesis'];
                    end($parens);
                    $parens = key($parens);
                    if ($this->debug === true) {
                        $line = $tokens[$parens]['line'];
                        echo "\t* token has nested parenthesis {$parens} on line {$line} *" . PHP_EOL;
                    }
                }
                $condition = 0;
                if (isset($tokens[$i]['conditions']) === true && empty($tokens[$i]['conditions']) === false) {
                    $condition = $tokens[$i]['conditions'];
                    end($condition);
                    $condition = key($condition);
                    if ($this->debug === true) {
                        $line = $tokens[$condition]['line'];
                        $type = $tokens[$condition]['type'];
                        echo "\t* token is inside condition {$condition} ({$type}) on line {$line} *" . PHP_EOL;
                    }
                }
                if ($parens > $object && $parens > $condition) {
                    if ($this->debug === true) {
                        echo "\t* using parenthesis *" . PHP_EOL;
                    }
                    $prev = $phpcs_file->find_previous(Tokens::$empty_tokens, $parens - 1, null, true);
                    $object = 0;
                    $condition = 0;
                } elseif ($object > 0 && $object >= $condition) {
                    if ($this->debug === true) {
                        echo "\t* using object *" . PHP_EOL;
                    }
                    $prev = $object;
                    $parens = 0;
                    $condition = 0;
                } elseif ($condition > 0) {
                    if ($this->debug === true) {
                        echo "\t* using condition *" . PHP_EOL;
                    }
                    $prev = $condition;
                    $object = 0;
                    $parens = 0;
                }
                //end if
                if ($prev === false) {
                    $prev = $phpcs_file->find_previous([T_EQUAL, T_RETURN], $tokens[$i]['scope_condition'] - 1, null, false, null, true);
                    if ($prev === false) {
                        $prev = $i;
                        if ($this->debug === true) {
                            echo "\t* could not find a previous T_EQUAL or T_RETURN token; will use current token *" . PHP_EOL;
                        }
                    }
                }
                if ($this->debug === true) {
                    $line = $tokens[$prev]['line'];
                    $type = $tokens[$prev]['type'];
                    echo "\t* previous token is {$type} on line {$line} *" . PHP_EOL;
                }
                $first = $phpcs_file->find_first_on_line(T_WHITESPACE, $prev, true);
                if ($this->debug === true) {
                    $line = $tokens[$first]['line'];
                    $type = $tokens[$first]['type'];
                    echo "\t* first token on line {$line} is {$first} ({$type}) *" . PHP_EOL;
                }
                $prev = $phpcs_file->find_start_of_statement($first);
                if ($prev !== $first) {
                    // This is not the start of the statement.
                    if ($this->debug === true) {
                        $line = $tokens[$prev]['line'];
                        $type = $tokens[$prev]['type'];
                        echo "\t* amended previous is {$type} on line {$line} *" . PHP_EOL;
                    }
                    $first = $phpcs_file->find_first_on_line(T_WHITESPACE, $prev, true);
                    if ($this->debug === true) {
                        $line = $tokens[$first]['line'];
                        $type = $tokens[$first]['type'];
                        echo "\t* amended first token is {$first} ({$type}) on line {$line} *" . PHP_EOL;
                    }
                }
                $current_indent = $tokens[$first]['column'] - 1;
                if ($object > 0 || $condition > 0) {
                    $current_indent += $this->indent;
                }
                if (isset($tokens[$first]['scope_closer']) === true && $tokens[$first]['scope_closer'] === $first) {
                    if ($this->debug === true) {
                        echo "\t* first token is a scope closer *" . PHP_EOL;
                    }
                    if ($condition === 0 || $tokens[$condition]['scope_opener'] < $first) {
                        $current_indent = $set_indents[$first];
                    } elseif ($this->debug === true) {
                        echo "\t* ignoring scope closer *" . PHP_EOL;
                    }
                }
                // Make sure it is divisible by our expected indent.
                $current_indent = (int) (ceil($current_indent / $this->indent) * $this->indent);
                $set_indents[$first] = $current_indent;
                if ($this->debug === true) {
                    $type = $tokens[$first]['type'];
                    echo "\t=> indent set to {$current_indent} by token {$first} ({$type})" . PHP_EOL;
                }
            }
            //end if
        }
        //end for
        // Don't process the rest of the file.
        return $phpcs_file->num_tokens;
    }
    //end process()
    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile All the tokens found in the document.
     * @param int                         $stackPtr  The position of the current token
     *                                               in the stack passed in $tokens.
     * @param int                         $length    The length of the new indent.
     * @param int                         $change    The difference in length between
     *                                               the old and new indent.
     *
     * @return bool
     */
    protected function adjust_indent(File $phpcs_file, $stack_ptr, $length, $change)
    {
        $tokens = $phpcs_file->get_tokens();
        // We don't adjust indents outside of PHP.
        if ($tokens[$stack_ptr]['code'] === T_INLINE_HTML) {
            return false;
        }
        $padding = '';
        if ($length > 0) {
            if ($this->tab_indent === true) {
                $num_tabs = floor($length / $this->tab_width);
                if ($num_tabs > 0) {
                    $num_spaces = $length - $num_tabs * $this->tab_width;
                    $padding = str_repeat("\t", $num_tabs) . str_repeat(' ', $num_spaces);
                }
            } else {
                $padding = str_repeat(' ', $length);
            }
        }
        if ($tokens[$stack_ptr]['column'] === 1) {
            $trimmed = ltrim($tokens[$stack_ptr]['content']);
            $accepted = $phpcs_file->fixer->replace_token($stack_ptr, $padding . $trimmed);
        } else {
            // Easier to just replace the entire indent.
            $accepted = $phpcs_file->fixer->replace_token($stack_ptr - 1, $padding);
        }
        if ($accepted === false) {
            return false;
        }
        if ($tokens[$stack_ptr]['code'] === T_DOC_COMMENT_OPEN_TAG) {
            // We adjusted the start of a comment, so adjust the rest of it
            // as well so the alignment remains correct.
            for ($x = $stack_ptr + 1; $x < $tokens[$stack_ptr]['comment_closer']; $x++) {
                if ($tokens[$x]['column'] !== 1) {
                    continue;
                }
                $length = 0;
                if ($tokens[$x]['code'] === T_DOC_COMMENT_WHITESPACE) {
                    $length = $tokens[$x]['length'];
                }
                $padding = $length + $change;
                if ($padding > 0) {
                    if ($this->tab_indent === true) {
                        $num_tabs = floor($padding / $this->tab_width);
                        $num_spaces = $padding - $num_tabs * $this->tab_width;
                        $padding = str_repeat("\t", $num_tabs) . str_repeat(' ', $num_spaces);
                    } else {
                        $padding = str_repeat(' ', $padding);
                    }
                } else {
                    $padding = '';
                }
                $phpcs_file->fixer->replace_token($x, $padding);
                if ($this->debug === true) {
                    $length = strlen($padding);
                    $line = $tokens[$x]['line'];
                    $type = $tokens[$x]['type'];
                    echo "\t=> Indent adjusted to {$length} for {$type} on line {$line}" . PHP_EOL;
                }
            }
            //end for
        }
        //end if
        return true;
    }
    //end adjustIndent()
}
//end class