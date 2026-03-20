<?php

declare (strict_types=1);
/**
 * Runs jslint.js on the file.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\Debug;

use Php_code_Sniffer\Config;
use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Common;
class Js_Lint_Sniff implements Sniff
{
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
        $rhino_path = Config::get_executable_path('rhino');
        $jslint_path = Config::get_executable_path('jslint');
        if ($rhino_path === null || $jslint_path === null) {
            return;
        }
        $file_name = $phpcs_file->get_filename();
        $rhino_path = Common::escapeshellcmd($rhino_path);
        $jslint_path = Common::escapeshellcmd($jslint_path);
        $cmd = "{$rhino_path} \"{$jslint_path}\" " . escapeshellarg($file_name);
        exec($cmd, $output, $retval);
        if (is_array($output) === true) {
            foreach ($output as $finding) {
                $matches = [];
                $num_matches = preg_match('/Lint at line ([0-9]+).*:(.*)$/', $finding, $matches);
                if ($num_matches === 0) {
                    continue;
                }
                $line = (int) $matches[1];
                $message = 'jslint says: ' . trim($matches[2]);
                $phpcs_file->add_warning_on_line($message, $line, 'ExternalTool');
            }
        }
        // Ignore the rest of the file.
        return $phpcs_file->num_tokens + 1;
    }
    //end process()
}
//end class