<?php

declare (strict_types=1);
/**
 * Runs gjslint on the file.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Generic\Sniffs\Debug;

use Php_code_Sniffer\Config;
use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Common;
class Closure_Linter_Sniff implements Sniff
{
    /**
     * A list of error codes that should show errors.
     *
     * All other error codes will show warnings.
     *
     * @var integer
     */
    public $error_codes = [];
    /**
     * A list of error codes to ignore.
     *
     * @var integer
     */
    public $ignore_codes = [];
    /**
     * A list of tokenizers this sniff supports.
     *
     * @var array
     */
    public $supported_tokenizers = ['JS'];
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
     * @throws \PHP_CodeSniffer\Exceptions\RuntimeException If jslint.js could not be run
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $lint_path = Config::get_executable_path('gjslint');
        if ($lint_path === null) {
            return;
        }
        $file_name = $phpcs_file->get_filename();
        $lint_path = Common::escapeshellcmd($lint_path);
        $cmd = $lint_path . ' --nosummary --notime --unix_mode ' . escapeshellarg($file_name);
        exec($cmd, $output, $retval);
        if (is_array($output) === false) {
            return;
        }
        foreach ($output as $finding) {
            $matches = [];
            $num_matches = preg_match('/^(.*):([0-9]+):\(.*?([0-9]+)\)(.*)$/', $finding, $matches);
            if ($num_matches === 0) {
                continue;
            }
            // Skip error codes we are ignoring.
            $code = $matches[3];
            if (in_array($code, $this->ignore_codes) === true) {
                continue;
            }
            $line = (int) $matches[2];
            $error = trim($matches[4]);
            $message = 'gjslint says: (%s) %s';
            $data = [$code, $error];
            if (in_array($code, $this->error_codes) === true) {
                $phpcs_file->add_error_on_line($message, $line, 'ExternalToolError', $data);
            } else {
                $phpcs_file->add_warning_on_line($message, $line, 'ExternalTool', $data);
            }
        }
        //end foreach
        // Ignore the rest of the file.
        return $phpcs_file->num_tokens + 1;
    }
    //end process()
}
//end class