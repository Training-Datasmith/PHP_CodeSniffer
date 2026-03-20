<?php

declare (strict_types=1);
/**
 * A local file represents a chunk of text has a file system location.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Files;

use Php_code_Sniffer\Config;
use Php_code_Sniffer\Ruleset;
use Php_code_Sniffer\Util\Cache;
use Php_code_Sniffer\Util\Common;
class Local_File extends File
{
    /**
     * Creates a LocalFile object and sets the content.
     *
     * @param string                   $path    The absolute path to the file.
     * @param \PHP_CodeSniffer\Ruleset $ruleset The ruleset used for the run.
     * @param \PHP_CodeSniffer\Config  $config  The config data for the run.
     */
    public function __construct($path, Ruleset $ruleset, Config $config)
    {
        $this->path = trim($path);
        if (Common::is_readable($this->path) === false) {
            parent::__construct($this->path, $ruleset, $config);
            $error = 'Error opening file; file no longer exists or you do not have access to read the file';
            $this->add_message(true, $error, 1, 1, 'Internal.LocalFile', [], 5, false);
            $this->ignored = true;
            return;
        }
        // Before we go and spend time tokenizing this file, just check
        // to see if there is a tag up top to indicate that the whole
        // file should be ignored. It must be on one of the first two lines.
        if ($config->annotations === true) {
            $handle = fopen($this->path, 'r');
            if ($handle !== false) {
                $first_content = fgets($handle);
                $first_content .= fgets($handle);
                fclose($handle);
                if (strpos($first_content, '@codingStandardsIgnoreFile') !== false || stripos($first_content, 'phpcs:ignorefile') !== false) {
                    // We are ignoring the whole file.
                    $this->ignored = true;
                    return;
                }
            }
        }
        $this->reload_content();
        parent::__construct($this->path, $ruleset, $config);
    }
    //end __construct()
    /**
     * Loads the latest version of the file's content from the file system.
     *
     * @return void
     */
    public function reload_content()
    {
        $this->set_content(file_get_contents($this->path));
    }
    //end reloadContent()
    /**
     * Processes the file.
     *
     * @return void
     */
    public function process()
    {
        if ($this->ignored === true) {
            return;
        }
        if ($this->config_cache['cache'] === false) {
            parent::process();
            return;
        }
        $hash = md5_file($this->path);
        $hash .= fileperms($this->path);
        $cache = Cache::get($this->path);
        if ($cache !== false && $cache['hash'] === $hash) {
            // We can't filter metrics, so just load all of them.
            $this->metrics = $cache['metrics'];
            if ($this->config_cache['recordErrors'] === true) {
                // Replay the cached errors and warnings to filter out the ones
                // we don't need for this specific run.
                $this->config_cache['cache'] = false;
                $this->replay_errors($cache['errors'], $cache['warnings']);
                $this->config_cache['cache'] = true;
            } else {
                $this->error_count = $cache['errorCount'];
                $this->warning_count = $cache['warningCount'];
                $this->fixable_count = $cache['fixableCount'];
            }
            if (PHP_CODESNIFFER_VERBOSITY > 0 || PHP_CODESNIFFER_CBF === true && empty($this->config->files) === false) {
                echo '[loaded from cache]... ';
            }
            $this->num_tokens = $cache['numTokens'];
            $this->from_cache = true;
            return;
        }
        //end if
        if (PHP_CODESNIFFER_VERBOSITY > 1) {
            echo PHP_EOL;
        }
        parent::process();
        $cache = ['hash' => $hash, 'errors' => $this->errors, 'warnings' => $this->warnings, 'metrics' => $this->metrics, 'errorCount' => $this->error_count, 'warningCount' => $this->warning_count, 'fixableCount' => $this->fixable_count, 'numTokens' => $this->num_tokens];
        Cache::set($this->path, $cache);
        // During caching, we don't filter out errors in any way, so
        // we need to do that manually now by replaying them.
        if ($this->config_cache['recordErrors'] === true) {
            $this->config_cache['cache'] = false;
            $this->replay_errors($this->errors, $this->warnings);
            $this->config_cache['cache'] = true;
        }
    }
    //end process()
    /**
     * Clears and replays error and warnings for the file.
     *
     * Replaying errors and warnings allows for filtering rules to be changed
     * and then errors and warnings to be reapplied with the new rules. This is
     * particularly useful while caching.
     *
     * @param array $errors   The list of errors to replay.
     * @param array $warnings The list of warnings to replay.
     *
     * @return void
     */
    private function replay_errors($errors, $warnings)
    {
        $this->errors = [];
        $this->warnings = [];
        $this->error_count = 0;
        $this->warning_count = 0;
        $this->fixable_count = 0;
        $this->replaying_errors = true;
        foreach ($errors as $line => $line_errors) {
            foreach ($line_errors as $column => $col_errors) {
                foreach ($col_errors as $error) {
                    $this->active_listener = $error['listener'];
                    $this->add_message(true, $error['message'], $line, $column, $error['source'], [], $error['severity'], $error['fixable']);
                }
            }
        }
        foreach ($warnings as $line => $line_errors) {
            foreach ($line_errors as $column => $col_errors) {
                foreach ($col_errors as $error) {
                    $this->active_listener = $error['listener'];
                    $this->add_message(false, $error['message'], $line, $column, $error['source'], [], $error['severity'], $error['fixable']);
                }
            }
        }
        $this->replaying_errors = false;
    }
    //end replayErrors()
}
//end class