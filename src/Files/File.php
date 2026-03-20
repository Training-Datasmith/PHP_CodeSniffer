<?php

declare (strict_types=1);
/**
 * Represents a piece of content being checked during the run.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Files;

use Php_code_Sniffer\Config;
use Php_code_Sniffer\Exceptions\RuntimeException;
use Php_code_Sniffer\Exceptions\Tokenizer_Exception;
use Php_code_Sniffer\Fixer;
use Php_code_Sniffer\Ruleset;
use Php_code_Sniffer\Util;
class File
{
    /**
     * The absolute path to the file associated with this object.
     *
     * @var string
     */
    public $path = '';
    /**
     * The content of the file.
     *
     * @var string
     */
    protected $content = '';
    /**
     * The config data for the run.
     *
     * @var \PHP_CodeSniffer\Config
     */
    public $config;
    /**
     * The ruleset used for the run.
     *
     * @var \PHP_CodeSniffer\Ruleset
     */
    public $ruleset;
    /**
     * If TRUE, the entire file is being ignored.
     *
     * @var boolean
     */
    public $ignored = false;
    /**
     * The EOL character this file uses.
     *
     * @var string
     */
    public $eol_char = '';
    /**
     * The Fixer object to control fixing errors.
     *
     * @var \PHP_CodeSniffer\Fixer
     */
    public $fixer;
    /**
     * The tokenizer being used for this file.
     *
     * @var \PHP_CodeSniffer\Tokenizers\Tokenizer
     */
    public $tokenizer;
    /**
     * The name of the tokenizer being used for this file.
     *
     * @var string
     */
    public $tokenizer_type = 'PHP';
    /**
     * Was the file loaded from cache?
     *
     * If TRUE, the file was loaded from a local cache.
     * If FALSE, the file was tokenized and processed fully.
     *
     * @var boolean
     */
    public $from_cache = false;
    /**
     * The number of tokens in this file.
     *
     * Stored here to save calling count() everywhere.
     *
     * @var integer
     */
    public $num_tokens = 0;
    /**
     * The tokens stack map.
     *
     * @var array
     */
    protected $tokens = [];
    /**
     * The errors raised from sniffs.
     *
     * @var array
     * @see getErrors()
     */
    protected $errors = [];
    /**
     * The warnings raised from sniffs.
     *
     * @var array
     * @see getWarnings()
     */
    protected $warnings = [];
    /**
     * The metrics recorded by sniffs.
     *
     * @var array
     * @see getMetrics()
     */
    protected $metrics = [];
    /**
     * The metrics recorded for each token.
     *
     * Stops the same metric being recorded for the same token twice.
     *
     * @var array
     * @see getMetrics()
     */
    private $metric_tokens = [];
    /**
     * The total number of errors raised.
     *
     * @var integer
     */
    protected $error_count = 0;
    /**
     * The total number of warnings raised.
     *
     * @var integer
     */
    protected $warning_count = 0;
    /**
     * The total number of errors and warnings that can be fixed.
     *
     * @var integer
     */
    protected $fixable_count = 0;
    /**
     * The total number of errors and warnings that were fixed.
     *
     * @var integer
     */
    protected $fixed_count = 0;
    /**
     * TRUE if errors are being replayed from the cache.
     *
     * @var boolean
     */
    protected $replaying_errors = false;
    /**
     * An array of sniffs that are being ignored.
     *
     * @var array
     */
    protected $ignored_listeners = [];
    /**
     * An array of message codes that are being ignored.
     *
     * @var array
     */
    protected $ignored_codes = [];
    /**
     * An array of sniffs listening to this file's processing.
     *
     * @var \PHP_CodeSniffer\Sniffs\Sniff[]
     */
    protected $listeners = [];
    /**
     * The class name of the sniff currently processing the file.
     *
     * @var string
     */
    protected $active_listener = '';
    /**
     * An array of sniffs being processed and how long they took.
     *
     * @var array
     */
    protected $listener_times = [];
    /**
     * A cache of often used config settings to improve performance.
     *
     * Storing them here saves 10k+ calls to __get() in the Config class.
     *
     * @var array
     */
    protected $config_cache = [];
    /**
     * Constructs a file.
     *
     * @param string                   $path    The absolute path to the file to process.
     * @param \PHP_CodeSniffer\Ruleset $ruleset The ruleset used for the run.
     * @param \PHP_CodeSniffer\Config  $config  The config data for the run.
     */
    public function __construct($path, Ruleset $ruleset, Config $config)
    {
        $this->path = $path;
        $this->ruleset = $ruleset;
        $this->config = $config;
        $this->fixer = new Fixer();
        $parts = explode('.', $path);
        $extension = array_pop($parts);
        if (isset($config->extensions[$extension]) === true) {
            $this->tokenizer_type = $config->extensions[$extension];
        } else {
            // Revert to default.
            $this->tokenizer_type = 'PHP';
        }
        $this->config_cache['cache'] = $this->config->cache;
        $this->config_cache['sniffs'] = array_map('strtolower', $this->config->sniffs);
        $this->config_cache['exclude'] = array_map('strtolower', $this->config->exclude);
        $this->config_cache['errorSeverity'] = $this->config->error_severity;
        $this->config_cache['warningSeverity'] = $this->config->warning_severity;
        $this->config_cache['recordErrors'] = $this->config->record_errors;
        $this->config_cache['ignorePatterns'] = $this->ruleset->ignore_patterns;
        $this->config_cache['includePatterns'] = $this->ruleset->include_patterns;
    }
    //end __construct()
    /**
     * Set the content of the file.
     *
     * Setting the content also calculates the EOL char being used.
     *
     * @param string $content The file content.
     *
     * @return void
     */
    public function set_content($content)
    {
        $this->content = $content;
        $this->tokens = [];
        try {
            $this->eol_char = Util\Common::detect_line_endings($content);
        } catch (RuntimeException $e) {
            $this->add_warning_on_line($e->get_message(), 1, 'Internal.DetectLineEndings');
            return;
        }
    }
    //end setContent()
    /**
     * Reloads the content of the file.
     *
     * By default, we have no idea where our content comes from,
     * so we can't do anything.
     *
     * @return void
     */
    public function reload_content()
    {
    }
    //end reloadContent()
    /**
     * Disables caching of this file.
     *
     * @return void
     */
    public function disable_caching()
    {
        $this->config_cache['cache'] = false;
    }
    //end disableCaching()
    /**
     * Starts the stack traversal and tells listeners when tokens are found.
     *
     * @return void
     */
    public function process()
    {
        if ($this->ignored === true) {
            return;
        }
        $this->errors = [];
        $this->warnings = [];
        $this->error_count = 0;
        $this->warning_count = 0;
        $this->fixable_count = 0;
        $this->parse();
        // Check if tokenizer errors cause this file to be ignored.
        if ($this->ignored === true) {
            return;
        }
        $this->fixer->start_file($this);
        if (PHP_CODESNIFFER_VERBOSITY > 2) {
            echo "\t*** START TOKEN PROCESSING ***" . PHP_EOL;
        }
        $found_code = false;
        $listener_ignore_to = [];
        $in_tests = defined('PHP_CODESNIFFER_IN_TESTS');
        $check_annotations = $this->config->annotations;
        // Foreach of the listeners that have registered to listen for this
        // token, get them to process it.
        foreach ($this->tokens as $stack_ptr => $token) {
            // Check for ignored lines.
            if ($check_annotations === true && ($token['code'] === T_COMMENT || $token['code'] === T_PHPCS_IGNORE_FILE || $token['code'] === T_PHPCS_SET || $token['code'] === T_DOC_COMMENT_STRING || $token['code'] === T_DOC_COMMENT_TAG || $in_tests === true && $token['code'] === T_INLINE_HTML)) {
                $comment_text = ltrim($this->tokens[$stack_ptr]['content'], " \t/*#");
                $comment_text_lower = strtolower($comment_text);
                if (strpos($comment_text, '@codingStandards') !== false) {
                    if (strpos($comment_text, '@codingStandardsIgnoreFile') !== false) {
                        // Ignoring the whole file, just a little late.
                        $this->errors = [];
                        $this->warnings = [];
                        $this->error_count = 0;
                        $this->warning_count = 0;
                        $this->fixable_count = 0;
                        return;
                    }
                    if (strpos($comment_text, '@codingStandardsChangeSetting') !== false) {
                        $start = strpos($comment_text, '@codingStandardsChangeSetting');
                        $comment = substr($comment_text, $start + 30);
                        $parts = explode(' ', $comment);
                        if (count($parts) >= 2) {
                            $sniff_parts = explode('.', $parts[0]);
                            if (count($sniff_parts) >= 3) {
                                // If the sniff code is not known to us, it has not been registered in this run.
                                // But don't throw an error as it could be there for a different standard to use.
                                if (isset($this->ruleset->sniff_codes[$parts[0]]) === true) {
                                    $listener_code = array_shift($parts);
                                    $property_code = array_shift($parts);
                                    $settings = ['value' => rtrim(implode(' ', $parts), " */\r\n"), 'scope' => 'sniff'];
                                    $listener_class = $this->ruleset->sniff_codes[$listener_code];
                                    $this->ruleset->set_sniff_property($listener_class, $property_code, $settings);
                                }
                            }
                        }
                    }
                    //end if
                } else {
                    if (substr($comment_text_lower, 0, 16) === 'phpcs:ignorefile' || substr($comment_text_lower, 0, 17) === '@phpcs:ignorefile') {
                        // Ignoring the whole file, just a little late.
                        $this->errors = [];
                        $this->warnings = [];
                        $this->error_count = 0;
                        $this->warning_count = 0;
                        $this->fixable_count = 0;
                        return;
                    }
                    if (substr($comment_text_lower, 0, 9) === 'phpcs:set' || substr($comment_text_lower, 0, 10) === '@phpcs:set') {
                        if (isset($token['sniffCode']) === true) {
                            $listener_code = $token['sniffCode'];
                            if (isset($this->ruleset->sniff_codes[$listener_code]) === true) {
                                $property_code = $token['sniffProperty'];
                                $settings = ['value' => $token['sniffPropertyValue'], 'scope' => 'sniff'];
                                $listener_class = $this->ruleset->sniff_codes[$listener_code];
                                $this->ruleset->set_sniff_property($listener_class, $property_code, $settings);
                            }
                        }
                    }
                }
                //end if
            }
            //end if
            if (PHP_CODESNIFFER_VERBOSITY > 2) {
                $type = $token['type'];
                $content = Util\Common::prepare_for_output($token['content']);
                echo "\t\tProcess token {$stack_ptr}: {$type} => {$content}" . PHP_EOL;
            }
            if ($token['code'] !== T_INLINE_HTML) {
                $found_code = true;
            }
            if (isset($this->ruleset->token_listeners[$token['code']]) === false) {
                continue;
            }
            foreach ($this->ruleset->token_listeners[$token['code']] as $listener_data) {
                if (isset($this->ignored_listeners[$listener_data['class']]) === true) {
                    // This sniff is ignoring past this token, or the whole file.
                    continue;
                }
                if (isset($listener_ignore_to[$listener_data['class']]) === true && $listener_ignore_to[$listener_data['class']] > $stack_ptr) {
                    // This sniff is ignoring past this token, or the whole file.
                    continue;
                }
                // Make sure this sniff supports the tokenizer
                // we are currently using.
                $class = $listener_data['class'];
                if (isset($listener_data['tokenizers'][$this->tokenizer_type]) === false) {
                    continue;
                }
                if (trim($this->path, '\'"') !== 'STDIN') {
                    // If the file path matches one of our ignore patterns, skip it.
                    // While there is support for a type of each pattern
                    // (absolute or relative) we don't actually support it here.
                    foreach ($listener_data['ignore'] as $pattern) {
                        // We assume a / directory separator, as do the exclude rules
                        // most developers write, so we need a special case for any system
                        // that is different.
                        if (DIRECTORY_SEPARATOR === '\\') {
                            $pattern = str_replace('/', '\\\\', $pattern);
                        }
                        $pattern = '`' . $pattern . '`i';
                        if (preg_match($pattern, $this->path) === 1) {
                            $this->ignored_listeners[$class] = true;
                            continue 2;
                        }
                    }
                    // If the file path does not match one of our include patterns, skip it.
                    // While there is support for a type of each pattern
                    // (absolute or relative) we don't actually support it here.
                    if (empty($listener_data['include']) === false) {
                        $included = false;
                        foreach ($listener_data['include'] as $pattern) {
                            // We assume a / directory separator, as do the exclude rules
                            // most developers write, so we need a special case for any system
                            // that is different.
                            if (DIRECTORY_SEPARATOR === '\\') {
                                $pattern = str_replace('/', '\\\\', $pattern);
                            }
                            $pattern = '`' . $pattern . '`i';
                            if (preg_match($pattern, $this->path) === 1) {
                                $included = true;
                                break;
                            }
                        }
                        if ($included === false) {
                            $this->ignored_listeners[$class] = true;
                            continue;
                        }
                    }
                    //end if
                }
                //end if
                $this->active_listener = $class;
                if (PHP_CODESNIFFER_VERBOSITY > 2) {
                    $start_time = microtime(true);
                    echo "\t\t\tProcessing " . $this->active_listener . '... ';
                }
                $ignore_to = $this->ruleset->sniffs[$class]->process($this, $stack_ptr);
                if ($ignore_to !== null) {
                    $listener_ignore_to[$this->active_listener] = $ignore_to;
                }
                if (PHP_CODESNIFFER_VERBOSITY > 2) {
                    $time_taken = microtime(true) - $start_time;
                    if (isset($this->listener_times[$this->active_listener]) === false) {
                        $this->listener_times[$this->active_listener] = 0;
                    }
                    $this->listener_times[$this->active_listener] += $time_taken;
                    $time_taken = round($time_taken, 4);
                    echo "DONE in {$time_taken} seconds" . PHP_EOL;
                }
                $this->active_listener = '';
            }
            //end foreach
        }
        //end foreach
        // If short open tags are off but the file being checked uses
        // short open tags, the whole content will be inline HTML
        // and nothing will be checked. So try and handle this case.
        // We don't show this error for STDIN because we can't be sure the content
        // actually came directly from the user. It could be something like
        // refs from a Git pre-push hook.
        if ($found_code === false && $this->tokenizer_type === 'PHP' && $this->path !== 'STDIN') {
            $short_tags = (bool) ini_get('short_open_tag');
            if ($short_tags === false) {
                $error = 'No PHP code was found in this file and short open tags are not allowed by this install of PHP. This file may be using short open tags but PHP does not allow them.';
                $this->add_warning($error, null, 'Internal.NoCodeFound');
            }
        }
        if (PHP_CODESNIFFER_VERBOSITY > 2) {
            echo "\t*** END TOKEN PROCESSING ***" . PHP_EOL;
            echo "\t*** START SNIFF PROCESSING REPORT ***" . PHP_EOL;
            asort($this->listener_times, SORT_NUMERIC);
            $this->listener_times = array_reverse($this->listener_times, true);
            foreach ($this->listener_times as $listener => $time_taken) {
                echo "\t{$listener}: " . round($time_taken, 4) . ' secs' . PHP_EOL;
            }
            echo "\t*** END SNIFF PROCESSING REPORT ***" . PHP_EOL;
        }
        $this->fixed_count += $this->fixer->get_fix_count();
    }
    //end process()
    /**
     * Tokenizes the file and prepares it for the test run.
     *
     * @return void
     */
    public function parse()
    {
        if (empty($this->tokens) === false) {
            // File has already been parsed.
            return;
        }
        try {
            $tokenizer_class = 'PHP_CodeSniffer\Tokenizers\\' . $this->tokenizer_type;
            $this->tokenizer = new $tokenizer_class($this->content, $this->config, $this->eol_char);
            $this->tokens = $this->tokenizer->get_tokens();
        } catch (Tokenizer_Exception $e) {
            $this->ignored = true;
            $this->add_warning($e->get_message(), null, 'Internal.Tokenizer.Exception');
            if (PHP_CODESNIFFER_VERBOSITY > 0) {
                echo "[{$this->tokenizer_type} => tokenizer error]... ";
                if (PHP_CODESNIFFER_VERBOSITY > 1) {
                    echo PHP_EOL;
                }
            }
            return;
        }
        $this->num_tokens = count($this->tokens);
        // Check for mixed line endings as these can cause tokenizer errors and we
        // should let the user know that the results they get may be incorrect.
        // This is done by removing all backslashes, removing the newline char we
        // detected, then converting newlines chars into text. If any backslashes
        // are left at the end, we have additional newline chars in use.
        $contents = str_replace('\\', '', $this->content);
        $contents = str_replace($this->eol_char, '', $contents);
        $contents = str_replace("\n", '\n', $contents);
        $contents = str_replace("\r", '\r', $contents);
        if (strpos($contents, '\\') !== false) {
            $error = 'File has mixed line endings; this may cause incorrect results';
            $this->add_warning_on_line($error, 1, 'Internal.LineEndings.Mixed');
        }
        if (PHP_CODESNIFFER_VERBOSITY > 0) {
            if ($this->num_tokens === 0) {
                $num_lines = 0;
            } else {
                $num_lines = $this->tokens[$this->num_tokens - 1]['line'];
            }
            echo "[{$this->tokenizer_type} => {$this->num_tokens} tokens in {$num_lines} lines]... ";
            if (PHP_CODESNIFFER_VERBOSITY > 1) {
                echo PHP_EOL;
            }
        }
    }
    //end parse()
    /**
     * Returns the token stack for this file.
     *
     * @return array
     */
    public function get_tokens()
    {
        return $this->tokens;
    }
    //end getTokens()
    /**
     * Remove vars stored in this file that are no longer required.
     *
     * @return void
     */
    public function clean_up()
    {
        $this->listener_times = null;
        $this->content = null;
        $this->tokens = null;
        $this->metric_tokens = null;
        $this->tokenizer = null;
        $this->fixer = null;
        $this->config = null;
        $this->ruleset = null;
    }
    //end cleanUp()
    /**
     * Records an error against a specific token in the file.
     *
     * @param string  $error    The error message.
     * @param int     $stackPtr The stack position where the error occurred.
     * @param string  $code     A violation code unique to the sniff message.
     * @param array   $data     Replacements for the error message.
     * @param int     $severity The severity level for this error. A value of 0
     *                          will be converted into the default severity level.
     * @param boolean $fixable  Can the error be fixed by the sniff?
     *
     * @return boolean
     */
    public function add_error($error, $stack_ptr, $code, $data = [], $severity = 0, $fixable = false)
    {
        if ($stack_ptr === null) {
            $line = 1;
            $column = 1;
        } else {
            $line = $this->tokens[$stack_ptr]['line'];
            $column = $this->tokens[$stack_ptr]['column'];
        }
        return $this->add_message(true, $error, $line, $column, $code, $data, $severity, $fixable);
    }
    //end addError()
    /**
     * Records a warning against a specific token in the file.
     *
     * @param string  $warning  The error message.
     * @param int     $stackPtr The stack position where the error occurred.
     * @param string  $code     A violation code unique to the sniff message.
     * @param array   $data     Replacements for the warning message.
     * @param int     $severity The severity level for this warning. A value of 0
     *                          will be converted into the default severity level.
     * @param boolean $fixable  Can the warning be fixed by the sniff?
     *
     * @return boolean
     */
    public function add_warning($warning, $stack_ptr, $code, $data = [], $severity = 0, $fixable = false)
    {
        if ($stack_ptr === null) {
            $line = 1;
            $column = 1;
        } else {
            $line = $this->tokens[$stack_ptr]['line'];
            $column = $this->tokens[$stack_ptr]['column'];
        }
        return $this->add_message(false, $warning, $line, $column, $code, $data, $severity, $fixable);
    }
    //end addWarning()
    /**
     * Records an error against a specific line in the file.
     *
     * @param string $error    The error message.
     * @param int    $line     The line on which the error occurred.
     * @param string $code     A violation code unique to the sniff message.
     * @param array  $data     Replacements for the error message.
     * @param int    $severity The severity level for this error. A value of 0
     *                         will be converted into the default severity level.
     *
     * @return boolean
     */
    public function add_error_on_line($error, $line, $code, $data = [], $severity = 0)
    {
        return $this->add_message(true, $error, $line, 1, $code, $data, $severity, false);
    }
    //end addErrorOnLine()
    /**
     * Records a warning against a specific token in the file.
     *
     * @param string $warning  The error message.
     * @param int    $line     The line on which the warning occurred.
     * @param string $code     A violation code unique to the sniff message.
     * @param array  $data     Replacements for the warning message.
     * @param int    $severity The severity level for this warning. A value of 0 will
     *                         will be converted into the default severity level.
     *
     * @return boolean
     */
    public function add_warning_on_line($warning, $line, $code, $data = [], $severity = 0)
    {
        return $this->add_message(false, $warning, $line, 1, $code, $data, $severity, false);
    }
    //end addWarningOnLine()
    /**
     * Records a fixable error against a specific token in the file.
     *
     * Returns true if the error was recorded and should be fixed.
     *
     * @param string $error    The error message.
     * @param int    $stackPtr The stack position where the error occurred.
     * @param string $code     A violation code unique to the sniff message.
     * @param array  $data     Replacements for the error message.
     * @param int    $severity The severity level for this error. A value of 0
     *                         will be converted into the default severity level.
     *
     * @return boolean
     */
    public function add_fixable_error($error, $stack_ptr, $code, $data = [], $severity = 0)
    {
        $recorded = $this->add_error($error, $stack_ptr, $code, $data, $severity, true);
        if ($recorded === true && $this->fixer->enabled === true) {
            return true;
        }
        return false;
    }
    //end addFixableError()
    /**
     * Records a fixable warning against a specific token in the file.
     *
     * Returns true if the warning was recorded and should be fixed.
     *
     * @param string $warning  The error message.
     * @param int    $stackPtr The stack position where the error occurred.
     * @param string $code     A violation code unique to the sniff message.
     * @param array  $data     Replacements for the warning message.
     * @param int    $severity The severity level for this warning. A value of 0
     *                         will be converted into the default severity level.
     *
     * @return boolean
     */
    public function add_fixable_warning($warning, $stack_ptr, $code, $data = [], $severity = 0)
    {
        $recorded = $this->add_warning($warning, $stack_ptr, $code, $data, $severity, true);
        if ($recorded === true && $this->fixer->enabled === true) {
            return true;
        }
        return false;
    }
    //end addFixableWarning()
    /**
     * Adds an error to the error stack.
     *
     * @param boolean $error    Is this an error message?
     * @param string  $message  The text of the message.
     * @param int     $line     The line on which the message occurred.
     * @param int     $column   The column at which the message occurred.
     * @param string  $code     A violation code unique to the sniff message.
     * @param array   $data     Replacements for the message.
     * @param int     $severity The severity level for this message. A value of 0
     *                          will be converted into the default severity level.
     * @param boolean $fixable  Can the problem be fixed by the sniff?
     *
     * @return boolean
     */
    protected function add_message($error, $message, $line, $column, $code, $data, $severity, $fixable)
    {
        // Check if this line is ignoring all message codes.
        if (isset($this->tokenizer->ignored_lines[$line]['.all']) === true) {
            return false;
        }
        // Work out which sniff generated the message.
        $parts = explode('.', $code);
        if ($parts[0] === 'Internal') {
            // An internal message.
            $listener_code = Util\Common::get_sniff_code($this->active_listener);
            $sniff_code = $code;
            $check_codes = [$sniff_code];
        } else {
            if ($parts[0] !== $code) {
                // The full message code has been passed in.
                $sniff_code = $code;
                $listener_code = substr($sniff_code, 0, strrpos($sniff_code, '.'));
            } else {
                $listener_code = Util\Common::get_sniff_code($this->active_listener);
                $sniff_code = $listener_code . '.' . $code;
                $parts = explode('.', $sniff_code);
            }
            $check_codes = [$sniff_code, $parts[0] . '.' . $parts[1] . '.' . $parts[2], $parts[0] . '.' . $parts[1], $parts[0]];
        }
        //end if
        if (isset($this->tokenizer->ignored_lines[$line]) === true) {
            // Check if this line is ignoring this specific message.
            $ignored = false;
            foreach ($check_codes as $check_code) {
                if (isset($this->tokenizer->ignored_lines[$line][$check_code]) === true) {
                    $ignored = true;
                    break;
                }
            }
            // If it is ignored, make sure it's not whitelisted.
            if ($ignored === true && isset($this->tokenizer->ignored_lines[$line]['.except']) === true) {
                foreach ($check_codes as $check_code) {
                    if (isset($this->tokenizer->ignored_lines[$line]['.except'][$check_code]) === true) {
                        $ignored = false;
                        break;
                    }
                }
            }
            if ($ignored === true) {
                return false;
            }
        }
        //end if
        $include_all = true;
        if ($this->config_cache['cache'] === false || $this->config_cache['recordErrors'] === false) {
            $include_all = false;
        }
        // Filter out any messages for sniffs that shouldn't have run
        // due to the use of the --sniffs command line argument.
        if ($include_all === false && (empty($this->config_cache['sniffs']) === false && in_array(strtolower($listener_code), $this->config_cache['sniffs'], true) === false || empty($this->config_cache['exclude']) === false && in_array(strtolower($listener_code), $this->config_cache['exclude'], true) === true)) {
            return false;
        }
        // If we know this sniff code is being ignored for this file, return early.
        foreach ($check_codes as $check_code) {
            if (isset($this->ignored_codes[$check_code]) === true) {
                return false;
            }
        }
        $opposite_type = 'warning';
        if ($error === false) {
            $opposite_type = 'error';
        }
        foreach ($check_codes as $check_code) {
            // Make sure this message type has not been set to the opposite message type.
            if (isset($this->ruleset->ruleset[$check_code]['type']) === true && $this->ruleset->ruleset[$check_code]['type'] === $opposite_type) {
                $error = !$error;
                break;
            }
        }
        if ($error === true) {
            $config_severity = $this->config_cache['errorSeverity'];
            $message_count =& $this->error_count;
            $messages =& $this->errors;
        } else {
            $config_severity = $this->config_cache['warningSeverity'];
            $message_count =& $this->warning_count;
            $messages =& $this->warnings;
        }
        if ($include_all === false && $config_severity === 0) {
            // Don't bother doing any processing as these messages are just going to
            // be hidden in the reports anyway.
            return false;
        }
        if ($severity === 0) {
            $severity = 5;
        }
        foreach ($check_codes as $check_code) {
            // Make sure we are interested in this severity level.
            if (isset($this->ruleset->ruleset[$check_code]['severity']) === true) {
                $severity = $this->ruleset->ruleset[$check_code]['severity'];
                break;
            }
        }
        if ($include_all === false && $config_severity > $severity) {
            return false;
        }
        // Make sure we are not ignoring this file.
        $included = null;
        if (trim($this->path, '\'"') === 'STDIN') {
            $included = true;
        } else {
            foreach ($check_codes as $check_code) {
                $patterns = null;
                if (isset($this->config_cache['includePatterns'][$check_code]) === true) {
                    $patterns = $this->config_cache['includePatterns'][$check_code];
                    $excluding = false;
                } elseif (isset($this->config_cache['ignorePatterns'][$check_code]) === true) {
                    $patterns = $this->config_cache['ignorePatterns'][$check_code];
                    $excluding = true;
                }
                if ($patterns === null) {
                    continue;
                }
                foreach ($patterns as $pattern => $type) {
                    // While there is support for a type of each pattern
                    // (absolute or relative) we don't actually support it here.
                    $replacements = ['\,' => ',', '*' => '.*'];
                    // We assume a / directory separator, as do the exclude rules
                    // most developers write, so we need a special case for any system
                    // that is different.
                    if (DIRECTORY_SEPARATOR === '\\') {
                        $replacements['/'] = '\\\\';
                    }
                    $pattern = '`' . strtr($pattern, $replacements) . '`i';
                    $matched = preg_match($pattern, $this->path);
                    if ($matched === 0) {
                        if ($excluding === false && $included === null) {
                            // This file path is not being included.
                            $included = false;
                        }
                        continue;
                    }
                    if ($excluding === true) {
                        // This file path is being excluded.
                        $this->ignored_codes[$check_code] = true;
                        return false;
                    }
                    // This file path is being included.
                    $included = true;
                    break;
                }
                //end foreach
            }
            //end foreach
        }
        //end if
        if ($included === false) {
            // There were include rules set, but this file
            // path didn't match any of them.
            return false;
        }
        $message_count++;
        if ($fixable === true) {
            $this->fixable_count++;
        }
        if ($this->config_cache['recordErrors'] === false && $include_all === false) {
            return true;
        }
        // See if there is a custom error message format to use.
        // But don't do this if we are replaying errors because replayed
        // errors have already used the custom format and have had their
        // data replaced.
        if ($this->replaying_errors === false && isset($this->ruleset->ruleset[$sniff_code]['message']) === true) {
            $message = $this->ruleset->ruleset[$sniff_code]['message'];
        }
        if (empty($data) === false) {
            $message = vsprintf($message, $data);
        }
        if (isset($messages[$line]) === false) {
            $messages[$line] = [];
        }
        if (isset($messages[$line][$column]) === false) {
            $messages[$line][$column] = [];
        }
        $messages[$line][$column][] = ['message' => $message, 'source' => $sniff_code, 'listener' => $this->active_listener, 'severity' => $severity, 'fixable' => $fixable];
        if (PHP_CODESNIFFER_VERBOSITY > 1 && $this->fixer->enabled === true && $fixable === true) {
            @ob_end_clean();
            echo "\tE: [Line {$line}] {$message} ({$sniff_code})" . PHP_EOL;
            ob_start();
        }
        return true;
    }
    //end addMessage()
    /**
     * Record a metric about the file being examined.
     *
     * @param int    $stackPtr The stack position where the metric was recorded.
     * @param string $metric   The name of the metric being recorded.
     * @param string $value    The value of the metric being recorded.
     *
     * @return boolean
     */
    public function record_metric($stack_ptr, $metric, $value)
    {
        if (isset($this->metrics[$metric]) === false) {
            $this->metrics[$metric] = ['values' => [$value => 1]];
            $this->metric_tokens[$metric][$stack_ptr] = true;
        } elseif (isset($this->metric_tokens[$metric][$stack_ptr]) === false) {
            $this->metric_tokens[$metric][$stack_ptr] = true;
            if (isset($this->metrics[$metric]['values'][$value]) === false) {
                $this->metrics[$metric]['values'][$value] = 1;
            } else {
                $this->metrics[$metric]['values'][$value]++;
            }
        }
        return true;
    }
    //end recordMetric()
    /**
     * Returns the number of errors raised.
     *
     * @return int
     */
    public function get_error_count()
    {
        return $this->error_count;
    }
    //end getErrorCount()
    /**
     * Returns the number of warnings raised.
     *
     * @return int
     */
    public function get_warning_count()
    {
        return $this->warning_count;
    }
    //end getWarningCount()
    /**
     * Returns the number of fixable errors/warnings raised.
     *
     * @return int
     */
    public function get_fixable_count()
    {
        return $this->fixable_count;
    }
    //end getFixableCount()
    /**
     * Returns the number of fixed errors/warnings.
     *
     * @return int
     */
    public function get_fixed_count()
    {
        return $this->fixed_count;
    }
    //end getFixedCount()
    /**
     * Returns the list of ignored lines.
     *
     * @return array
     */
    public function get_ignored_lines()
    {
        return $this->tokenizer->ignored_lines;
    }
    //end getIgnoredLines()
    /**
     * Returns the errors raised from processing this file.
     *
     * @return array
     */
    public function get_errors()
    {
        return $this->errors;
    }
    //end getErrors()
    /**
     * Returns the warnings raised from processing this file.
     *
     * @return array
     */
    public function get_warnings()
    {
        return $this->warnings;
    }
    //end getWarnings()
    /**
     * Returns the metrics found while processing this file.
     *
     * @return array
     */
    public function get_metrics()
    {
        return $this->metrics;
    }
    //end getMetrics()
    /**
     * Returns the absolute filename of this file.
     *
     * @return string
     */
    public function get_filename()
    {
        return $this->path;
    }
    //end getFilename()
    /**
     * Returns the declaration name for classes, interfaces, traits, enums, and functions.
     *
     * @param int $stackPtr The position of the declaration token which
     *                      declared the class, interface, trait, or function.
     *
     * @return string|null The name of the class, interface, trait, or function;
     *                     or NULL if the function or class is anonymous.
     * @throws \PHP_CodeSniffer\Exceptions\RuntimeException If the specified token is not of type
     *                                                      T_FUNCTION, T_CLASS, T_ANON_CLASS,
     *                                                      T_CLOSURE, T_TRAIT, T_ENUM, or T_INTERFACE.
     */
    public function get_declaration_name($stack_ptr)
    {
        $token_code = $this->tokens[$stack_ptr]['code'];
        if ($token_code === T_ANON_CLASS || $token_code === T_CLOSURE) {
            return null;
        }
        if ($token_code !== T_FUNCTION && $token_code !== T_CLASS && $token_code !== T_INTERFACE && $token_code !== T_TRAIT && $token_code !== T_ENUM) {
            throw new RuntimeException('Token type "' . $this->tokens[$stack_ptr]['type'] . '" is not T_FUNCTION, T_CLASS, T_INTERFACE, T_TRAIT or T_ENUM');
        }
        if ($token_code === T_FUNCTION && strtolower($this->tokens[$stack_ptr]['content']) !== 'function') {
            // This is a function declared without the "function" keyword.
            // So this token is the function name.
            return $this->tokens[$stack_ptr]['content'];
        }
        $content = null;
        for ($i = $stack_ptr; $i < $this->num_tokens; $i++) {
            if ($this->tokens[$i]['code'] === T_STRING) {
                $content = $this->tokens[$i]['content'];
                break;
            }
        }
        return $content;
    }
    //end getDeclarationName()
    /**
     * Returns the method parameters for the specified function token.
     *
     * Also supports passing in a USE token for a closure use group.
     *
     * Each parameter is in the following format:
     *
     * <code>
     *   0 => array(
     *         'name'                => '$var',  // The variable name.
     *         'token'               => integer, // The stack pointer to the variable name.
     *         'content'             => string,  // The full content of the variable definition.
     *         'has_attributes'      => boolean, // Does the parameter have one or more attributes attached ?
     *         'pass_by_reference'   => boolean, // Is the variable passed by reference?
     *         'reference_token'     => integer, // The stack pointer to the reference operator
     *                                           // or FALSE if the param is not passed by reference.
     *         'variable_length'     => boolean, // Is the param of variable length through use of `...` ?
     *         'variadic_token'      => integer, // The stack pointer to the ... operator
     *                                           // or FALSE if the param is not variable length.
     *         'type_hint'           => string,  // The type hint for the variable.
     *         'type_hint_token'     => integer, // The stack pointer to the start of the type hint
     *                                           // or FALSE if there is no type hint.
     *         'type_hint_end_token' => integer, // The stack pointer to the end of the type hint
     *                                           // or FALSE if there is no type hint.
     *         'nullable_type'       => boolean, // TRUE if the type is preceded by the nullability
     *                                           // operator.
     *         'comma_token'         => integer, // The stack pointer to the comma after the param
     *                                           // or FALSE if this is the last param.
     *        )
     * </code>
     *
     * Parameters with default values have additional array indexes of:
     *         'default'             => string,  // The full content of the default value.
     *         'default_token'       => integer, // The stack pointer to the start of the default value.
     *         'default_equal_token' => integer, // The stack pointer to the equals sign.
     *
     * Parameters declared using PHP 8 constructor property promotion, have these additional array indexes:
     *         'property_visibility' => string,        // The property visibility as declared.
     *         'visibility_token'    => integer|false, // The stack pointer to the visibility modifier token
     *                                                 // or FALSE if the visibility is not explicitly declared.
     *         'property_readonly'   => boolean,       // TRUE if the readonly keyword was found.
     *         'readonly_token'      => integer,       // The stack pointer to the readonly modifier token.
     *                                                 // This index will only be set if the property is readonly.
     *
     * @param int $stackPtr The position in the stack of the function token
     *                      to acquire the parameters for.
     *
     * @return array
     * @throws \PHP_CodeSniffer\Exceptions\RuntimeException If the specified $stackPtr is not of
     *                                                      type T_FUNCTION, T_CLOSURE, T_USE,
     *                                                      or T_FN.
     */
    public function get_method_parameters($stack_ptr)
    {
        if ($this->tokens[$stack_ptr]['code'] !== T_FUNCTION && $this->tokens[$stack_ptr]['code'] !== T_CLOSURE && $this->tokens[$stack_ptr]['code'] !== T_USE && $this->tokens[$stack_ptr]['code'] !== T_FN) {
            throw new RuntimeException('$stackPtr must be of type T_FUNCTION or T_CLOSURE or T_USE or T_FN');
        }
        if ($this->tokens[$stack_ptr]['code'] === T_USE) {
            $opener = $this->find_next(T_OPEN_PARENTHESIS, $stack_ptr + 1);
            if ($opener === false || isset($this->tokens[$opener]['parenthesis_owner']) === true) {
                throw new RuntimeException('$stackPtr was not a valid T_USE');
            }
        } else {
            if (isset($this->tokens[$stack_ptr]['parenthesis_opener']) === false) {
                // Live coding or syntax error, so no params to find.
                return [];
            }
            $opener = $this->tokens[$stack_ptr]['parenthesis_opener'];
        }
        if (isset($this->tokens[$opener]['parenthesis_closer']) === false) {
            // Live coding or syntax error, so no params to find.
            return [];
        }
        $closer = $this->tokens[$opener]['parenthesis_closer'];
        $vars = [];
        $curr_var = null;
        $param_start = $opener + 1;
        $default_start = null;
        $equal_token = null;
        $param_count = 0;
        $has_attributes = false;
        $pass_by_reference = false;
        $reference_token = false;
        $variable_length = false;
        $variadic_token = false;
        $type_hint = '';
        $type_hint_token = false;
        $type_hint_end_token = false;
        $nullable_type = false;
        $visibility_token = null;
        $readonly_token = null;
        for ($i = $param_start; $i <= $closer; $i++) {
            // Check to see if this token has a parenthesis or bracket opener. If it does
            // it's likely to be an array which might have arguments in it. This
            // could cause problems in our parsing below, so lets just skip to the
            // end of it.
            if (isset($this->tokens[$i]['parenthesis_opener']) === true) {
                // Don't do this if it's the close parenthesis for the method.
                if ($i !== $this->tokens[$i]['parenthesis_closer']) {
                    $i = $this->tokens[$i]['parenthesis_closer'];
                    continue;
                }
            }
            if (isset($this->tokens[$i]['bracket_opener']) === true) {
                if ($i !== $this->tokens[$i]['bracket_closer']) {
                    $i = $this->tokens[$i]['bracket_closer'];
                    continue;
                }
            }
            switch ($this->tokens[$i]['code']) {
                case T_ATTRIBUTE:
                    $has_attributes = true;
                    // Skip to the end of the attribute.
                    $i = $this->tokens[$i]['attribute_closer'];
                    break;
                case T_BITWISE_AND:
                    if ($default_start === null) {
                        $pass_by_reference = true;
                        $reference_token = $i;
                    }
                    break;
                case T_VARIABLE:
                    $curr_var = $i;
                    break;
                case T_ELLIPSIS:
                    $variable_length = true;
                    $variadic_token = $i;
                    break;
                case T_CALLABLE:
                    if ($type_hint_token === false) {
                        $type_hint_token = $i;
                    }
                    $type_hint .= $this->tokens[$i]['content'];
                    $type_hint_end_token = $i;
                    break;
                case T_SELF:
                case T_PARENT:
                case T_STATIC:
                    // Self and parent are valid, static invalid, but was probably intended as type hint.
                    if (isset($default_start) === false) {
                        if ($type_hint_token === false) {
                            $type_hint_token = $i;
                        }
                        $type_hint .= $this->tokens[$i]['content'];
                        $type_hint_end_token = $i;
                    }
                    break;
                case T_STRING:
                    // This is a string, so it may be a type hint, but it could
                    // also be a constant used as a default value.
                    $prev_comma = false;
                    for ($t = $i; $t >= $opener; $t--) {
                        if ($this->tokens[$t]['code'] === T_COMMA) {
                            $prev_comma = $t;
                            break;
                        }
                    }
                    if ($prev_comma !== false) {
                        $next_equals = false;
                        for ($t = $prev_comma; $t < $i; $t++) {
                            if ($this->tokens[$t]['code'] === T_EQUAL) {
                                $next_equals = $t;
                                break;
                            }
                        }
                        if ($next_equals !== false) {
                            break;
                        }
                    }
                    if ($default_start === null) {
                        if ($type_hint_token === false) {
                            $type_hint_token = $i;
                        }
                        $type_hint .= $this->tokens[$i]['content'];
                        $type_hint_end_token = $i;
                    }
                    break;
                case T_NAMESPACE:
                case T_NS_SEPARATOR:
                case T_TYPE_UNION:
                case T_TYPE_INTERSECTION:
                case T_FALSE:
                case T_NULL:
                    // Part of a type hint or default value.
                    if ($default_start === null) {
                        if ($type_hint_token === false) {
                            $type_hint_token = $i;
                        }
                        $type_hint .= $this->tokens[$i]['content'];
                        $type_hint_end_token = $i;
                    }
                    break;
                case T_NULLABLE:
                    if ($default_start === null) {
                        $nullable_type = true;
                        $type_hint .= $this->tokens[$i]['content'];
                        $type_hint_end_token = $i;
                    }
                    break;
                case T_PUBLIC:
                case T_PROTECTED:
                case T_PRIVATE:
                    if ($default_start === null) {
                        $visibility_token = $i;
                    }
                    break;
                case T_READONLY:
                    if ($default_start === null) {
                        $readonly_token = $i;
                    }
                    break;
                case T_CLOSE_PARENTHESIS:
                case T_COMMA:
                    // If it's null, then there must be no parameters for this
                    // method.
                    if ($curr_var === null) {
                        continue 2;
                    }
                    $vars[$param_count] = [];
                    $vars[$param_count]['token'] = $curr_var;
                    $vars[$param_count]['name'] = $this->tokens[$curr_var]['content'];
                    $vars[$param_count]['content'] = trim($this->get_tokens_as_string($param_start, $i - $param_start));
                    if ($default_start !== null) {
                        $vars[$param_count]['default'] = trim($this->get_tokens_as_string($default_start, $i - $default_start));
                        $vars[$param_count]['default_token'] = $default_start;
                        $vars[$param_count]['default_equal_token'] = $equal_token;
                    }
                    $vars[$param_count]['has_attributes'] = $has_attributes;
                    $vars[$param_count]['pass_by_reference'] = $pass_by_reference;
                    $vars[$param_count]['reference_token'] = $reference_token;
                    $vars[$param_count]['variable_length'] = $variable_length;
                    $vars[$param_count]['variadic_token'] = $variadic_token;
                    $vars[$param_count]['type_hint'] = $type_hint;
                    $vars[$param_count]['type_hint_token'] = $type_hint_token;
                    $vars[$param_count]['type_hint_end_token'] = $type_hint_end_token;
                    $vars[$param_count]['nullable_type'] = $nullable_type;
                    if ($visibility_token !== null || $readonly_token !== null) {
                        $vars[$param_count]['property_visibility'] = 'public';
                        $vars[$param_count]['visibility_token'] = false;
                        $vars[$param_count]['property_readonly'] = false;
                        if ($visibility_token !== null) {
                            $vars[$param_count]['property_visibility'] = $this->tokens[$visibility_token]['content'];
                            $vars[$param_count]['visibility_token'] = $visibility_token;
                        }
                        if ($readonly_token !== null) {
                            $vars[$param_count]['property_readonly'] = true;
                            $vars[$param_count]['readonly_token'] = $readonly_token;
                        }
                    }
                    if ($this->tokens[$i]['code'] === T_COMMA) {
                        $vars[$param_count]['comma_token'] = $i;
                    } else {
                        $vars[$param_count]['comma_token'] = false;
                    }
                    // Reset the vars, as we are about to process the next parameter.
                    $curr_var = null;
                    $param_start = $i + 1;
                    $default_start = null;
                    $equal_token = null;
                    $has_attributes = false;
                    $pass_by_reference = false;
                    $reference_token = false;
                    $variable_length = false;
                    $variadic_token = false;
                    $type_hint = '';
                    $type_hint_token = false;
                    $type_hint_end_token = false;
                    $nullable_type = false;
                    $visibility_token = null;
                    $readonly_token = null;
                    $param_count++;
                    break;
                case T_EQUAL:
                    $default_start = $this->find_next(Util\Tokens::$empty_tokens, $i + 1, null, true);
                    $equal_token = $i;
                    break;
            }
            //end switch
        }
        //end for
        return $vars;
    }
    //end getMethodParameters()
    /**
     * Returns the visibility and implementation properties of a method.
     *
     * The format of the return value is:
     * <code>
     *   array(
     *    'scope'                 => 'public', // Public, private, or protected
     *    'scope_specified'       => true,     // TRUE if the scope keyword was found.
     *    'return_type'           => '',       // The return type of the method.
     *    'return_type_token'     => integer,  // The stack pointer to the start of the return type
     *                                         // or FALSE if there is no return type.
     *    'return_type_end_token' => integer,  // The stack pointer to the end of the return type
     *                                         // or FALSE if there is no return type.
     *    'nullable_return_type'  => false,    // TRUE if the return type is preceded by the
     *                                         // nullability operator.
     *    'is_abstract'           => false,    // TRUE if the abstract keyword was found.
     *    'is_final'              => false,    // TRUE if the final keyword was found.
     *    'is_static'             => false,    // TRUE if the static keyword was found.
     *    'has_body'              => false,    // TRUE if the method has a body
     *   );
     * </code>
     *
     * @param int $stackPtr The position in the stack of the function token to
     *                      acquire the properties for.
     *
     * @return array
     * @throws \PHP_CodeSniffer\Exceptions\RuntimeException If the specified position is not a
     *                                                      T_FUNCTION, T_CLOSURE, or T_FN token.
     */
    public function get_method_properties($stack_ptr)
    {
        if ($this->tokens[$stack_ptr]['code'] !== T_FUNCTION && $this->tokens[$stack_ptr]['code'] !== T_CLOSURE && $this->tokens[$stack_ptr]['code'] !== T_FN) {
            throw new RuntimeException('$stackPtr must be of type T_FUNCTION or T_CLOSURE or T_FN');
        }
        if ($this->tokens[$stack_ptr]['code'] === T_FUNCTION) {
            $valid = [T_PUBLIC => T_PUBLIC, T_PRIVATE => T_PRIVATE, T_PROTECTED => T_PROTECTED, T_STATIC => T_STATIC, T_FINAL => T_FINAL, T_ABSTRACT => T_ABSTRACT, T_WHITESPACE => T_WHITESPACE, T_COMMENT => T_COMMENT, T_DOC_COMMENT => T_DOC_COMMENT];
        } else {
            $valid = [T_STATIC => T_STATIC, T_WHITESPACE => T_WHITESPACE, T_COMMENT => T_COMMENT, T_DOC_COMMENT => T_DOC_COMMENT];
        }
        $scope = 'public';
        $scope_specified = false;
        $is_abstract = false;
        $is_final = false;
        $is_static = false;
        for ($i = $stack_ptr - 1; $i > 0; $i--) {
            if (isset($valid[$this->tokens[$i]['code']]) === false) {
                break;
            }
            switch ($this->tokens[$i]['code']) {
                case T_PUBLIC:
                    $scope = 'public';
                    $scope_specified = true;
                    break;
                case T_PRIVATE:
                    $scope = 'private';
                    $scope_specified = true;
                    break;
                case T_PROTECTED:
                    $scope = 'protected';
                    $scope_specified = true;
                    break;
                case T_ABSTRACT:
                    $is_abstract = true;
                    break;
                case T_FINAL:
                    $is_final = true;
                    break;
                case T_STATIC:
                    $is_static = true;
                    break;
            }
            //end switch
        }
        //end for
        $return_type = '';
        $return_type_token = false;
        $return_type_end_token = false;
        $nullable_return_type = false;
        $has_body = true;
        if (isset($this->tokens[$stack_ptr]['parenthesis_closer']) === true) {
            $scope_opener = null;
            if (isset($this->tokens[$stack_ptr]['scope_opener']) === true) {
                $scope_opener = $this->tokens[$stack_ptr]['scope_opener'];
            }
            $valid = [T_STRING => T_STRING, T_CALLABLE => T_CALLABLE, T_SELF => T_SELF, T_PARENT => T_PARENT, T_STATIC => T_STATIC, T_FALSE => T_FALSE, T_NULL => T_NULL, T_NAMESPACE => T_NAMESPACE, T_NS_SEPARATOR => T_NS_SEPARATOR, T_TYPE_UNION => T_TYPE_UNION, T_TYPE_INTERSECTION => T_TYPE_INTERSECTION];
            for ($i = $this->tokens[$stack_ptr]['parenthesis_closer']; $i < $this->num_tokens; $i++) {
                if ($scope_opener === null && $this->tokens[$i]['code'] === T_SEMICOLON || $scope_opener !== null && $i === $scope_opener) {
                    // End of function definition.
                    break;
                }
                if ($this->tokens[$i]['code'] === T_NULLABLE) {
                    $nullable_return_type = true;
                }
                if (isset($valid[$this->tokens[$i]['code']]) === true) {
                    if ($return_type_token === false) {
                        $return_type_token = $i;
                    }
                    $return_type .= $this->tokens[$i]['content'];
                    $return_type_end_token = $i;
                }
            }
            //end for
            if ($this->tokens[$stack_ptr]['code'] === T_FN) {
                $body_token = T_FN_ARROW;
            } else {
                $body_token = T_OPEN_CURLY_BRACKET;
            }
            $end = $this->find_next([$body_token, T_SEMICOLON], $this->tokens[$stack_ptr]['parenthesis_closer']);
            $has_body = $this->tokens[$end]['code'] === $body_token;
        }
        //end if
        if ($return_type !== '' && $nullable_return_type === true) {
            $return_type = '?' . $return_type;
        }
        return ['scope' => $scope, 'scope_specified' => $scope_specified, 'return_type' => $return_type, 'return_type_token' => $return_type_token, 'return_type_end_token' => $return_type_end_token, 'nullable_return_type' => $nullable_return_type, 'is_abstract' => $is_abstract, 'is_final' => $is_final, 'is_static' => $is_static, 'has_body' => $has_body];
    }
    //end getMethodProperties()
    /**
     * Returns the visibility and implementation properties of a class member var.
     *
     * The format of the return value is:
     *
     * <code>
     *   array(
     *    'scope'           => string,  // Public, private, or protected.
     *    'scope_specified' => boolean, // TRUE if the scope was explicitly specified.
     *    'is_static'       => boolean, // TRUE if the static keyword was found.
     *    'is_readonly'     => boolean, // TRUE if the readonly keyword was found.
     *    'type'            => string,  // The type of the var (empty if no type specified).
     *    'type_token'      => integer, // The stack pointer to the start of the type
     *                                  // or FALSE if there is no type.
     *    'type_end_token'  => integer, // The stack pointer to the end of the type
     *                                  // or FALSE if there is no type.
     *    'nullable_type'   => boolean, // TRUE if the type is preceded by the nullability
     *                                  // operator.
     *   );
     * </code>
     *
     * @param int $stackPtr The position in the stack of the T_VARIABLE token to
     *                      acquire the properties for.
     *
     * @return array
     * @throws \PHP_CodeSniffer\Exceptions\RuntimeException If the specified position is not a
     *                                                      T_VARIABLE token, or if the position is not
     *                                                      a class member variable.
     */
    public function get_member_properties($stack_ptr)
    {
        if ($this->tokens[$stack_ptr]['code'] !== T_VARIABLE) {
            throw new RuntimeException('$stackPtr must be of type T_VARIABLE');
        }
        $conditions = array_keys($this->tokens[$stack_ptr]['conditions']);
        $ptr = array_pop($conditions);
        if (isset($this->tokens[$ptr]) === false || $this->tokens[$ptr]['code'] !== T_CLASS && $this->tokens[$ptr]['code'] !== T_ANON_CLASS && $this->tokens[$ptr]['code'] !== T_TRAIT) {
            if (isset($this->tokens[$ptr]) === true && ($this->tokens[$ptr]['code'] === T_INTERFACE || $this->tokens[$ptr]['code'] === T_ENUM)) {
                // T_VARIABLEs in interfaces/enums can actually be method arguments
                // but they won't be seen as being inside the method because there
                // are no scope openers and closers for abstract methods. If it is in
                // parentheses, we can be pretty sure it is a method argument.
                if (isset($this->tokens[$stack_ptr]['nested_parenthesis']) === false || empty($this->tokens[$stack_ptr]['nested_parenthesis']) === true) {
                    $error = 'Possible parse error: %ss may not include member vars';
                    $code = sprintf('Internal.ParseError.%sHasMemberVar', ucfirst($this->tokens[$ptr]['content']));
                    $data = [strtolower($this->tokens[$ptr]['content'])];
                    $this->add_warning($error, $stack_ptr, $code, $data);
                    return [];
                }
            } else {
                throw new RuntimeException('$stackPtr is not a class member var');
            }
        }
        //end if
        // Make sure it's not a method parameter.
        if (empty($this->tokens[$stack_ptr]['nested_parenthesis']) === false) {
            $parenthesis = array_keys($this->tokens[$stack_ptr]['nested_parenthesis']);
            $deepest_open = array_pop($parenthesis);
            if ($deepest_open > $ptr && isset($this->tokens[$deepest_open]['parenthesis_owner']) === true && $this->tokens[$this->tokens[$deepest_open]['parenthesis_owner']]['code'] === T_FUNCTION) {
                throw new RuntimeException('$stackPtr is not a class member var');
            }
        }
        $valid = [T_PUBLIC => T_PUBLIC, T_PRIVATE => T_PRIVATE, T_PROTECTED => T_PROTECTED, T_STATIC => T_STATIC, T_VAR => T_VAR, T_READONLY => T_READONLY];
        $valid += Util\Tokens::$empty_tokens;
        $scope = 'public';
        $scope_specified = false;
        $is_static = false;
        $is_readonly = false;
        $start_of_statement = $this->find_previous([T_SEMICOLON, T_OPEN_CURLY_BRACKET, T_CLOSE_CURLY_BRACKET, T_ATTRIBUTE_END], $stack_ptr - 1);
        for ($i = $start_of_statement + 1; $i < $stack_ptr; $i++) {
            if (isset($valid[$this->tokens[$i]['code']]) === false) {
                break;
            }
            switch ($this->tokens[$i]['code']) {
                case T_PUBLIC:
                    $scope = 'public';
                    $scope_specified = true;
                    break;
                case T_PRIVATE:
                    $scope = 'private';
                    $scope_specified = true;
                    break;
                case T_PROTECTED:
                    $scope = 'protected';
                    $scope_specified = true;
                    break;
                case T_STATIC:
                    $is_static = true;
                    break;
                case T_READONLY:
                    $is_readonly = true;
                    break;
            }
        }
        //end for
        $type = '';
        $type_token = false;
        $type_end_token = false;
        $nullable_type = false;
        if ($i < $stack_ptr) {
            // We've found a type.
            $valid = [T_STRING => T_STRING, T_CALLABLE => T_CALLABLE, T_SELF => T_SELF, T_PARENT => T_PARENT, T_FALSE => T_FALSE, T_NULL => T_NULL, T_NAMESPACE => T_NAMESPACE, T_NS_SEPARATOR => T_NS_SEPARATOR, T_TYPE_UNION => T_TYPE_UNION, T_TYPE_INTERSECTION => T_TYPE_INTERSECTION];
            for ($i; $i < $stack_ptr; $i++) {
                if ($this->tokens[$i]['code'] === T_VARIABLE) {
                    // Hit another variable in a group definition.
                    break;
                }
                if ($this->tokens[$i]['code'] === T_NULLABLE) {
                    $nullable_type = true;
                }
                if (isset($valid[$this->tokens[$i]['code']]) === true) {
                    $type_end_token = $i;
                    if ($type_token === false) {
                        $type_token = $i;
                    }
                    $type .= $this->tokens[$i]['content'];
                }
            }
            if ($type !== '' && $nullable_type === true) {
                $type = '?' . $type;
            }
        }
        //end if
        return ['scope' => $scope, 'scope_specified' => $scope_specified, 'is_static' => $is_static, 'is_readonly' => $is_readonly, 'type' => $type, 'type_token' => $type_token, 'type_end_token' => $type_end_token, 'nullable_type' => $nullable_type];
    }
    //end getMemberProperties()
    /**
     * Returns the visibility and implementation properties of a class.
     *
     * The format of the return value is:
     * <code>
     *   array(
     *    'is_abstract' => false, // true if the abstract keyword was found.
     *    'is_final'    => false, // true if the final keyword was found.
     *    'is_readonly' => false, // true if the readonly keyword was found.
     *   );
     * </code>
     *
     * @param int $stackPtr The position in the stack of the T_CLASS token to
     *                      acquire the properties for.
     *
     * @return array
     * @throws \PHP_CodeSniffer\Exceptions\RuntimeException If the specified position is not a
     *                                                      T_CLASS token.
     */
    public function get_class_properties($stack_ptr)
    {
        if ($this->tokens[$stack_ptr]['code'] !== T_CLASS) {
            throw new RuntimeException('$stackPtr must be of type T_CLASS');
        }
        $valid = [T_FINAL => T_FINAL, T_ABSTRACT => T_ABSTRACT, T_READONLY => T_READONLY, T_WHITESPACE => T_WHITESPACE, T_COMMENT => T_COMMENT, T_DOC_COMMENT => T_DOC_COMMENT];
        $is_abstract = false;
        $is_final = false;
        $is_readonly = false;
        for ($i = $stack_ptr - 1; $i > 0; $i--) {
            if (isset($valid[$this->tokens[$i]['code']]) === false) {
                break;
            }
            switch ($this->tokens[$i]['code']) {
                case T_ABSTRACT:
                    $is_abstract = true;
                    break;
                case T_FINAL:
                    $is_final = true;
                    break;
                case T_READONLY:
                    $is_readonly = true;
                    break;
            }
        }
        //end for
        return ['is_abstract' => $is_abstract, 'is_final' => $is_final, 'is_readonly' => $is_readonly];
    }
    //end getClassProperties()
    /**
     * Determine if the passed token is a reference operator.
     *
     * Returns true if the specified token position represents a reference.
     * Returns false if the token represents a bitwise operator.
     *
     * @param int $stackPtr The position of the T_BITWISE_AND token.
     *
     * @return boolean
     */
    public function is_reference($stack_ptr)
    {
        if ($this->tokens[$stack_ptr]['code'] !== T_BITWISE_AND) {
            return false;
        }
        $token_before = $this->find_previous(Util\Tokens::$empty_tokens, $stack_ptr - 1, null, true);
        if ($this->tokens[$token_before]['code'] === T_FUNCTION || $this->tokens[$token_before]['code'] === T_CLOSURE || $this->tokens[$token_before]['code'] === T_FN) {
            // Function returns a reference.
            return true;
        }
        if ($this->tokens[$token_before]['code'] === T_DOUBLE_ARROW) {
            // Inside a foreach loop or array assignment, this is a reference.
            return true;
        }
        if ($this->tokens[$token_before]['code'] === T_AS) {
            // Inside a foreach loop, this is a reference.
            return true;
        }
        if (isset(Util\Tokens::$assignment_tokens[$this->tokens[$token_before]['code']]) === true) {
            // This is directly after an assignment. It's a reference. Even if
            // it is part of an operation, the other tests will handle it.
            return true;
        }
        $token_after = $this->find_next(Util\Tokens::$empty_tokens, $stack_ptr + 1, null, true);
        if ($this->tokens[$token_after]['code'] === T_NEW) {
            return true;
        }
        if (isset($this->tokens[$stack_ptr]['nested_parenthesis']) === true) {
            $brackets = $this->tokens[$stack_ptr]['nested_parenthesis'];
            $last_bracket = array_pop($brackets);
            if (isset($this->tokens[$last_bracket]['parenthesis_owner']) === true) {
                $owner = $this->tokens[$this->tokens[$last_bracket]['parenthesis_owner']];
                if ($owner['code'] === T_FUNCTION || $owner['code'] === T_CLOSURE || $owner['code'] === T_FN) {
                    $params = $this->get_method_parameters($this->tokens[$last_bracket]['parenthesis_owner']);
                    foreach ($params as $param) {
                        if ($param['reference_token'] === $stack_ptr) {
                            // Function parameter declared to be passed by reference.
                            return true;
                        }
                    }
                }
                //end if
            } else {
                $prev = false;
                for ($t = $this->tokens[$last_bracket]['parenthesis_opener'] - 1; $t >= 0; $t--) {
                    if ($this->tokens[$t]['code'] !== T_WHITESPACE) {
                        $prev = $t;
                        break;
                    }
                }
                if ($prev !== false && $this->tokens[$prev]['code'] === T_USE) {
                    // Closure use by reference.
                    return true;
                }
            }
            //end if
        }
        //end if
        // Pass by reference in function calls and assign by reference in arrays.
        if ($this->tokens[$token_before]['code'] === T_OPEN_PARENTHESIS || $this->tokens[$token_before]['code'] === T_COMMA || $this->tokens[$token_before]['code'] === T_OPEN_SHORT_ARRAY) {
            if ($this->tokens[$token_after]['code'] === T_VARIABLE) {
                return true;
            }
            $skip = Util\Tokens::$empty_tokens;
            $skip[] = T_NS_SEPARATOR;
            $skip[] = T_SELF;
            $skip[] = T_PARENT;
            $skip[] = T_STATIC;
            $skip[] = T_STRING;
            $skip[] = T_NAMESPACE;
            $skip[] = T_DOUBLE_COLON;
            $next_significant_after = $this->find_next($skip, $stack_ptr + 1, null, true);
            if ($this->tokens[$next_significant_after]['code'] === T_VARIABLE) {
                return true;
            }
            //end if
        }
        //end if
        return false;
    }
    //end isReference()
    /**
     * Returns the content of the tokens from the specified start position in
     * the token stack for the specified length.
     *
     * @param int  $start       The position to start from in the token stack.
     * @param int  $length      The length of tokens to traverse from the start pos.
     * @param bool $origContent Whether the original content or the tab replaced
     *                          content should be used.
     *
     * @return string The token contents.
     * @throws \PHP_CodeSniffer\Exceptions\RuntimeException If the specified position does not exist.
     */
    public function get_tokens_as_string($start, $length, $orig_content = false)
    {
        if (is_int($start) === false || isset($this->tokens[$start]) === false) {
            throw new RuntimeException('The $start position for getTokensAsString() must exist in the token stack');
        }
        if (is_int($length) === false || $length <= 0) {
            return '';
        }
        $str = '';
        $end = $start + $length;
        if ($end > $this->num_tokens) {
            $end = $this->num_tokens;
        }
        for ($i = $start; $i < $end; $i++) {
            // If tabs are being converted to spaces by the tokeniser, the
            // original content should be used instead of the converted content.
            if ($orig_content === true && isset($this->tokens[$i]['orig_content']) === true) {
                $str .= $this->tokens[$i]['orig_content'];
            } else {
                $str .= $this->tokens[$i]['content'];
            }
        }
        return $str;
    }
    //end getTokensAsString()
    /**
     * Returns the position of the previous specified token(s).
     *
     * If a value is specified, the previous token of the specified type(s)
     * containing the specified value will be returned.
     *
     * Returns false if no token can be found.
     *
     * @param int|string|array $types   The type(s) of tokens to search for.
     * @param int              $start   The position to start searching from in the
     *                                  token stack.
     * @param int|null         $end     The end position to fail if no token is found.
     *                                  if not specified or null, end will default to
     *                                  the start of the token stack.
     * @param bool             $exclude If true, find the previous token that is NOT of
     *                                  the types specified in $types.
     * @param string|null      $value   The value that the token(s) must be equal to.
     *                                  If value is omitted, tokens with any value will
     *                                  be returned.
     * @param bool             $local   If true, tokens outside the current statement
     *                                  will not be checked. IE. checking will stop
     *                                  at the previous semi-colon found.
     *
     * @return int|false
     * @see    findNext()
     */
    public function find_previous($types, $start, $end = null, $exclude = false, $value = null, $local = false)
    {
        $types = (array) $types;
        if ($end === null) {
            $end = 0;
        }
        for ($i = $start; $i >= $end; $i--) {
            $found = (bool) $exclude;
            foreach ($types as $type) {
                if ($this->tokens[$i]['code'] === $type) {
                    $found = !$exclude;
                    break;
                }
            }
            if ($found === true) {
                if ($value === null) {
                    return $i;
                }
                if ($this->tokens[$i]['content'] === $value) {
                    return $i;
                }
            }
            if ($local === true) {
                if (isset($this->tokens[$i]['scope_opener']) === true && $i === $this->tokens[$i]['scope_closer']) {
                    $i = $this->tokens[$i]['scope_opener'];
                } elseif (isset($this->tokens[$i]['bracket_opener']) === true && $i === $this->tokens[$i]['bracket_closer']) {
                    $i = $this->tokens[$i]['bracket_opener'];
                } elseif (isset($this->tokens[$i]['parenthesis_opener']) === true && $i === $this->tokens[$i]['parenthesis_closer']) {
                    $i = $this->tokens[$i]['parenthesis_opener'];
                } elseif ($this->tokens[$i]['code'] === T_SEMICOLON) {
                    break;
                }
            }
        }
        //end for
        return false;
    }
    //end findPrevious()
    /**
     * Returns the position of the next specified token(s).
     *
     * If a value is specified, the next token of the specified type(s)
     * containing the specified value will be returned.
     *
     * Returns false if no token can be found.
     *
     * @param int|string|array $types   The type(s) of tokens to search for.
     * @param int              $start   The position to start searching from in the
     *                                  token stack.
     * @param int|null         $end     The end position to fail if no token is found.
     *                                  if not specified or null, end will default to
     *                                  the end of the token stack.
     * @param bool             $exclude If true, find the next token that is NOT of
     *                                  a type specified in $types.
     * @param string|null      $value   The value that the token(s) must be equal to.
     *                                  If value is omitted, tokens with any value will
     *                                  be returned.
     * @param bool             $local   If true, tokens outside the current statement
     *                                  will not be checked. i.e., checking will stop
     *                                  at the next semi-colon found.
     *
     * @return int|false
     * @see    findPrevious()
     */
    public function find_next($types, $start, $end = null, $exclude = false, $value = null, $local = false)
    {
        $types = (array) $types;
        if ($end === null || $end > $this->num_tokens) {
            $end = $this->num_tokens;
        }
        for ($i = $start; $i < $end; $i++) {
            $found = (bool) $exclude;
            foreach ($types as $type) {
                if ($this->tokens[$i]['code'] === $type) {
                    $found = !$exclude;
                    break;
                }
            }
            if ($found === true) {
                if ($value === null) {
                    return $i;
                }
                if ($this->tokens[$i]['content'] === $value) {
                    return $i;
                }
            }
            if ($local === true && $this->tokens[$i]['code'] === T_SEMICOLON) {
                break;
            }
        }
        //end for
        return false;
    }
    //end findNext()
    /**
     * Returns the position of the first non-whitespace token in a statement.
     *
     * @param int              $start  The position to start searching from in the token stack.
     * @param int|string|array $ignore Token types that should not be considered stop points.
     *
     * @return int
     */
    public function find_start_of_statement($start, $ignore = null)
    {
        $start_tokens = Util\Tokens::$block_openers;
        $start_tokens[T_OPEN_SHORT_ARRAY] = true;
        $start_tokens[T_OPEN_TAG] = true;
        $start_tokens[T_OPEN_TAG_WITH_ECHO] = true;
        $end_tokens = [T_CLOSE_TAG => true, T_COLON => true, T_COMMA => true, T_DOUBLE_ARROW => true, T_MATCH_ARROW => true, T_SEMICOLON => true];
        if ($ignore !== null) {
            $ignore = (array) $ignore;
            foreach ($ignore as $code) {
                if (isset($start_tokens[$code]) === true) {
                    unset($start_tokens[$code]);
                }
                if (isset($end_tokens[$code]) === true) {
                    unset($end_tokens[$code]);
                }
            }
        }
        // If the start token is inside the case part of a match expression,
        // find the start of the condition. If it's in the statement part, find
        // the token that comes after the match arrow.
        $match_expression = $this->get_condition($start, T_MATCH);
        if ($match_expression !== false) {
            for ($prev_match = $start; $prev_match > $this->tokens[$match_expression]['scope_opener']; $prev_match--) {
                if ($prev_match !== $start && ($this->tokens[$prev_match]['code'] === T_MATCH_ARROW || $this->tokens[$prev_match]['code'] === T_COMMA)) {
                    break;
                }
                // Skip nested statements.
                if (isset($this->tokens[$prev_match]['bracket_opener']) === true && $prev_match === $this->tokens[$prev_match]['bracket_closer']) {
                    $prev_match = $this->tokens[$prev_match]['bracket_opener'];
                } elseif (isset($this->tokens[$prev_match]['parenthesis_opener']) === true && $prev_match === $this->tokens[$prev_match]['parenthesis_closer']) {
                    $prev_match = $this->tokens[$prev_match]['parenthesis_opener'];
                }
            }
            if ($prev_match <= $this->tokens[$match_expression]['scope_opener']) {
                // We're before the arrow in the first case.
                $next = $this->find_next(Util\Tokens::$empty_tokens, $this->tokens[$match_expression]['scope_opener'] + 1, null, true);
                if ($next === false) {
                    return $start;
                }
                return $next;
            }
            if ($this->tokens[$prev_match]['code'] === T_COMMA) {
                // We're before the arrow, but not in the first case.
                $prev_match_arrow = $this->find_previous(T_MATCH_ARROW, $prev_match - 1, $this->tokens[$match_expression]['scope_opener']);
                if ($prev_match_arrow === false) {
                    // We're before the arrow in the first case.
                    $next = $this->find_next(Util\Tokens::$empty_tokens, $this->tokens[$match_expression]['scope_opener'] + 1, null, true);
                    return $next;
                }
                $end = $this->find_end_of_statement($prev_match_arrow);
                return $this->find_next(Util\Tokens::$empty_tokens, $end + 1, null, true);
            }
        }
        //end if
        $last_not_empty = $start;
        // If we are starting at a token that ends a scope block, skip to
        // the start and continue from there.
        // If we are starting at a token that ends a statement, skip this
        // token so we find the true start of the statement.
        while (isset($end_tokens[$this->tokens[$start]['code']]) === true || isset($this->tokens[$start]['scope_condition']) === true && $start === $this->tokens[$start]['scope_closer']) {
            if (isset($this->tokens[$start]['scope_condition']) === true) {
                $start = $this->tokens[$start]['scope_condition'];
            } else {
                $start--;
            }
        }
        for ($i = $start; $i >= 0; $i--) {
            if (isset($start_tokens[$this->tokens[$i]['code']]) === true || isset($end_tokens[$this->tokens[$i]['code']]) === true) {
                // Found the end of the previous statement.
                return $last_not_empty;
            }
            if (isset($this->tokens[$i]['scope_opener']) === true && $i === $this->tokens[$i]['scope_closer'] && $this->tokens[$i]['code'] !== T_CLOSE_PARENTHESIS && $this->tokens[$i]['code'] !== T_END_NOWDOC && $this->tokens[$i]['code'] !== T_END_HEREDOC && $this->tokens[$i]['code'] !== T_BREAK && $this->tokens[$i]['code'] !== T_RETURN && $this->tokens[$i]['code'] !== T_CONTINUE && $this->tokens[$i]['code'] !== T_THROW && $this->tokens[$i]['code'] !== T_EXIT) {
                // Found the end of the previous scope block.
                return $last_not_empty;
            }
            // Skip nested statements.
            if (isset($this->tokens[$i]['bracket_opener']) === true && $i === $this->tokens[$i]['bracket_closer']) {
                $i = $this->tokens[$i]['bracket_opener'];
            } elseif (isset($this->tokens[$i]['parenthesis_opener']) === true && $i === $this->tokens[$i]['parenthesis_closer']) {
                $i = $this->tokens[$i]['parenthesis_opener'];
            } elseif ($this->tokens[$i]['code'] === T_CLOSE_USE_GROUP) {
                $start = $this->find_previous(T_OPEN_USE_GROUP, $i - 1);
                if ($start !== false) {
                    $i = $start;
                }
            }
            //end if
            if (isset(Util\Tokens::$empty_tokens[$this->tokens[$i]['code']]) === false) {
                $last_not_empty = $i;
            }
        }
        //end for
        return 0;
    }
    //end findStartOfStatement()
    /**
     * Returns the position of the last non-whitespace token in a statement.
     *
     * @param int              $start  The position to start searching from in the token stack.
     * @param int|string|array $ignore Token types that should not be considered stop points.
     *
     * @return int
     */
    public function find_end_of_statement($start, $ignore = null)
    {
        $end_tokens = [T_COLON => true, T_COMMA => true, T_DOUBLE_ARROW => true, T_SEMICOLON => true, T_CLOSE_PARENTHESIS => true, T_CLOSE_SQUARE_BRACKET => true, T_CLOSE_CURLY_BRACKET => true, T_CLOSE_SHORT_ARRAY => true, T_OPEN_TAG => true, T_CLOSE_TAG => true];
        if ($ignore !== null) {
            $ignore = (array) $ignore;
            foreach ($ignore as $code) {
                unset($end_tokens[$code]);
            }
        }
        // If the start token is inside the case part of a match expression,
        // advance to the match arrow and continue looking for the
        // end of the statement from there so that we skip over commas.
        if ($this->tokens[$start]['code'] !== T_MATCH_ARROW) {
            $match_expression = $this->get_condition($start, T_MATCH);
            if ($match_expression !== false) {
                $before_arrow = true;
                $prev_match_arrow = $this->find_previous(T_MATCH_ARROW, $start - 1, $this->tokens[$match_expression]['scope_opener']);
                if ($prev_match_arrow !== false) {
                    $prev_comma = $this->find_next(T_COMMA, $prev_match_arrow + 1, $start);
                    if ($prev_comma === false) {
                        // No comma between this token and the last match arrow,
                        // so this token exists after the arrow and we can continue
                        // checking as normal.
                        $before_arrow = false;
                    }
                }
                if ($before_arrow === true) {
                    $next_match_arrow = $this->find_next(T_MATCH_ARROW, $start + 1, $this->tokens[$match_expression]['scope_closer']);
                    if ($next_match_arrow !== false) {
                        $start = $next_match_arrow;
                    }
                }
            }
            //end if
        }
        //end if
        $last_not_empty = $start;
        for ($i = $start; $i < $this->num_tokens; $i++) {
            if ($i !== $start && isset($end_tokens[$this->tokens[$i]['code']]) === true) {
                // Found the end of the statement.
                if ($this->tokens[$i]['code'] === T_CLOSE_PARENTHESIS || $this->tokens[$i]['code'] === T_CLOSE_SQUARE_BRACKET || $this->tokens[$i]['code'] === T_CLOSE_CURLY_BRACKET || $this->tokens[$i]['code'] === T_CLOSE_SHORT_ARRAY || $this->tokens[$i]['code'] === T_OPEN_TAG || $this->tokens[$i]['code'] === T_CLOSE_TAG) {
                    return $last_not_empty;
                }
                return $i;
            }
            // Skip nested statements.
            if (isset($this->tokens[$i]['scope_closer']) === true && ($i === $this->tokens[$i]['scope_opener'] || $i === $this->tokens[$i]['scope_condition'])) {
                if ($this->tokens[$i]['code'] === T_FN) {
                    $last_not_empty = $this->tokens[$i]['scope_closer'];
                    $i = $this->tokens[$i]['scope_closer'] - 1;
                    continue;
                }
                if ($i === $start && isset(Util\Tokens::$scope_openers[$this->tokens[$i]['code']]) === true) {
                    return $this->tokens[$i]['scope_closer'];
                }
                $i = $this->tokens[$i]['scope_closer'];
            } elseif (isset($this->tokens[$i]['bracket_closer']) === true && $i === $this->tokens[$i]['bracket_opener']) {
                $i = $this->tokens[$i]['bracket_closer'];
            } elseif (isset($this->tokens[$i]['parenthesis_closer']) === true && $i === $this->tokens[$i]['parenthesis_opener']) {
                $i = $this->tokens[$i]['parenthesis_closer'];
            } elseif ($this->tokens[$i]['code'] === T_OPEN_USE_GROUP) {
                $end = $this->find_next(T_CLOSE_USE_GROUP, $i + 1);
                if ($end !== false) {
                    $i = $end;
                }
            }
            //end if
            if (isset(Util\Tokens::$empty_tokens[$this->tokens[$i]['code']]) === false) {
                $last_not_empty = $i;
            }
        }
        //end for
        return $this->num_tokens - 1;
    }
    //end findEndOfStatement()
    /**
     * Returns the position of the first token on a line, matching given type.
     *
     * Returns false if no token can be found.
     *
     * @param int|string|array $types   The type(s) of tokens to search for.
     * @param int              $start   The position to start searching from in the
     *                                  token stack. The first token matching on
     *                                  this line before this token will be returned.
     * @param bool             $exclude If true, find the token that is NOT of
     *                                  the types specified in $types.
     * @param string           $value   The value that the token must be equal to.
     *                                  If value is omitted, tokens with any value will
     *                                  be returned.
     *
     * @return int|false
     */
    public function find_first_on_line($types, $start, $exclude = false, $value = null)
    {
        if (is_array($types) === false) {
            $types = [$types];
        }
        $found_token = false;
        for ($i = $start; $i >= 0; $i--) {
            if ($this->tokens[$i]['line'] < $this->tokens[$start]['line']) {
                break;
            }
            $found = $exclude;
            foreach ($types as $type) {
                if ($exclude === false) {
                    if ($this->tokens[$i]['code'] === $type) {
                        $found = true;
                        break;
                    }
                } else if ($this->tokens[$i]['code'] === $type) {
                    $found = false;
                    break;
                }
            }
            if ($found === true) {
                if ($value === null) {
                    $found_token = $i;
                } elseif ($this->tokens[$i]['content'] === $value) {
                    $found_token = $i;
                }
            }
        }
        //end for
        return $found_token;
    }
    //end findFirstOnLine()
    /**
     * Determine if the passed token has a condition of one of the passed types.
     *
     * @param int              $stackPtr The position of the token we are checking.
     * @param int|string|array $types    The type(s) of tokens to search for.
     *
     * @return boolean
     */
    public function has_condition($stack_ptr, $types)
    {
        // Check for the existence of the token.
        if (isset($this->tokens[$stack_ptr]) === false) {
            return false;
        }
        // Make sure the token has conditions.
        if (isset($this->tokens[$stack_ptr]['conditions']) === false) {
            return false;
        }
        $types = (array) $types;
        $conditions = $this->tokens[$stack_ptr]['conditions'];
        foreach ($types as $type) {
            if (in_array($type, $conditions, true) === true) {
                // We found a token with the required type.
                return true;
            }
        }
        return false;
    }
    //end hasCondition()
    /**
     * Return the position of the condition for the passed token.
     *
     * Returns FALSE if the token does not have the condition.
     *
     * @param int        $stackPtr The position of the token we are checking.
     * @param int|string $type     The type of token to search for.
     * @param bool       $first    If TRUE, will return the matched condition
     *                             furthest away from the passed token.
     *                             If FALSE, will return the matched condition
     *                             closest to the passed token.
     *
     * @return int|false
     */
    public function get_condition($stack_ptr, $type, $first = true)
    {
        // Check for the existence of the token.
        if (isset($this->tokens[$stack_ptr]) === false) {
            return false;
        }
        // Make sure the token has conditions.
        if (isset($this->tokens[$stack_ptr]['conditions']) === false) {
            return false;
        }
        $conditions = $this->tokens[$stack_ptr]['conditions'];
        if ($first === false) {
            $conditions = array_reverse($conditions, true);
        }
        foreach ($conditions as $token => $condition) {
            if ($condition === $type) {
                return $token;
            }
        }
        return false;
    }
    //end getCondition()
    /**
     * Returns the name of the class that the specified class extends.
     * (works for classes, anonymous classes and interfaces)
     *
     * Returns FALSE on error or if there is no extended class name.
     *
     * @param int $stackPtr The stack position of the class.
     *
     * @return string|false
     */
    public function find_extended_class_name($stack_ptr)
    {
        // Check for the existence of the token.
        if (isset($this->tokens[$stack_ptr]) === false) {
            return false;
        }
        if ($this->tokens[$stack_ptr]['code'] !== T_CLASS && $this->tokens[$stack_ptr]['code'] !== T_ANON_CLASS && $this->tokens[$stack_ptr]['code'] !== T_INTERFACE) {
            return false;
        }
        if (isset($this->tokens[$stack_ptr]['scope_opener']) === false) {
            return false;
        }
        $class_opener_index = $this->tokens[$stack_ptr]['scope_opener'];
        $extends_index = $this->find_next(T_EXTENDS, $stack_ptr, $class_opener_index);
        if ($extends_index === false) {
            return false;
        }
        $find = [T_NS_SEPARATOR, T_STRING, T_WHITESPACE];
        $end = $this->find_next($find, $extends_index + 1, $class_opener_index + 1, true);
        $name = $this->get_tokens_as_string($extends_index + 1, $end - $extends_index - 1);
        $name = trim($name);
        if ($name === '') {
            return false;
        }
        return $name;
    }
    //end findExtendedClassName()
    /**
     * Returns the names of the interfaces that the specified class or enum implements.
     *
     * Returns FALSE on error or if there are no implemented interface names.
     *
     * @param int $stackPtr The stack position of the class or enum token.
     *
     * @return array|false
     */
    public function find_implemented_interface_names($stack_ptr)
    {
        // Check for the existence of the token.
        if (isset($this->tokens[$stack_ptr]) === false) {
            return false;
        }
        if ($this->tokens[$stack_ptr]['code'] !== T_CLASS && $this->tokens[$stack_ptr]['code'] !== T_ANON_CLASS && $this->tokens[$stack_ptr]['code'] !== T_ENUM) {
            return false;
        }
        if (isset($this->tokens[$stack_ptr]['scope_closer']) === false) {
            return false;
        }
        $class_opener_index = $this->tokens[$stack_ptr]['scope_opener'];
        $implements_index = $this->find_next(T_IMPLEMENTS, $stack_ptr, $class_opener_index);
        if ($implements_index === false) {
            return false;
        }
        $find = [T_NS_SEPARATOR, T_STRING, T_WHITESPACE, T_COMMA];
        $end = $this->find_next($find, $implements_index + 1, $class_opener_index + 1, true);
        $name = $this->get_tokens_as_string($implements_index + 1, $end - $implements_index - 1);
        $name = trim($name);
        if ($name === '') {
            return false;
        }
        $names = explode(',', $name);
        return array_map('trim', $names);
    }
    //end findImplementedInterfaceNames()
}
//end class