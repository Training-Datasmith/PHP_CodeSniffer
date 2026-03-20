<?php

declare (strict_types=1);
/**
 * Runs eslint on the file.
 *
 * @author    Ryan McCue <ryan+gh@hmn.md>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Generic\Sniffs\Debug;

use Php_code_Sniffer\Config;
use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Common;
class Es_Lint_Sniff implements Sniff
{
    /**
     * A list of tokenizers this sniff supports.
     *
     * @var array
     */
    public $supported_tokenizers = ['JS'];
    /**
     * ESLint configuration file path.
     *
     * @var string|null Path to eslintrc. Null to autodetect.
     */
    public $config_file;
    /**
     * Returns the token types that this sniff is interested in.
     *
     * @return int[]
     */
    public function register()
    {
        return [T_OPEN_TAG];
    }
    //end register()
    /**
     * Processes the tokens that this sniff is interested in.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file where the token was found.
     * @param int                         $stackPtr  The position in the stack where
     *                                               the token was found.
     *
     * @return void
     * @throws \PHP_CodeSniffer\Exceptions\RuntimeException If jshint.js could not be run
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $eslint_path = Config::get_executable_path('eslint');
        if ($eslint_path === null) {
            return;
        }
        $filename = $phpcs_file->get_filename();
        $config_file = $this->config_file;
        if (empty($config_file) === true) {
            // Attempt to autodetect.
            $candidates = glob('.eslintrc{.js,.yaml,.yml,.json}', GLOB_BRACE);
            if (empty($candidates) === false) {
                $config_file = $candidates[0];
            }
        }
        $eslint_options = ['--format json'];
        if (empty($config_file) === false) {
            $eslint_options[] = '--config ' . escapeshellarg($config_file);
        }
        $cmd = Common::escapeshellcmd(escapeshellarg($eslint_path) . ' ' . implode(' ', $eslint_options) . ' ' . escapeshellarg($filename));
        // Execute!
        exec($cmd, $stdout, $code);
        if ($code <= 0) {
            // No errors, continue.
            return $phpcs_file->num_tokens + 1;
        }
        $data = json_decode(implode("\n", $stdout));
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Ignore any errors.
            return $phpcs_file->num_tokens + 1;
        }
        // Data is a list of files, but we only pass a single one.
        $messages = $data[0]->messages;
        foreach ($messages as $error) {
            $message = 'eslint says: ' . $error->message;
            if (empty($error->fatal) === false || $error->severity === 2) {
                $phpcs_file->add_error_on_line($message, $error->line, 'ExternalTool');
            } else {
                $phpcs_file->add_warning_on_line($message, $error->line, 'ExternalTool');
            }
        }
        // Ignore the rest of the file.
        return $phpcs_file->num_tokens + 1;
    }
    //end process()
}
//end class