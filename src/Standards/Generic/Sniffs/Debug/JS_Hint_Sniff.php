<?php

declare (strict_types=1);
/**
 * Runs jshint.js on the file.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @author    Alexander Wei§ <aweisswa@gmx.de>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Generic\Sniffs\Debug;

use Php_code_Sniffer\Config;
use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Common;
class Js_Hint_Sniff implements Sniff
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
     * @throws \PHP_CodeSniffer\Exceptions\RuntimeException If jshint.js could not be run
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $rhino_path = Config::get_executable_path('rhino');
        $jshint_path = Config::get_executable_path('jshint');
        if ($jshint_path === null) {
            return;
        }
        $file_name = $phpcs_file->get_filename();
        $jshint_path = Common::escapeshellcmd($jshint_path);
        if ($rhino_path !== null) {
            $rhino_path = Common::escapeshellcmd($rhino_path);
            $cmd = "{$rhino_path} \"{$jshint_path}\" " . escapeshellarg($file_name);
            exec($cmd, $output, $retval);
            $regex = '`^(?P<error>.+)\(.+:(?P<line>[0-9]+).*:[0-9]+\)$`';
        } else {
            $cmd = "{$jshint_path} " . escapeshellarg($file_name);
            exec($cmd, $output, $retval);
            $regex = '`^(.+?): line (?P<line>[0-9]+), col [0-9]+, (?P<error>.+)$`';
        }
        if (is_array($output) === true) {
            foreach ($output as $finding) {
                $matches = [];
                $num_matches = preg_match($regex, $finding, $matches);
                if ($num_matches === 0) {
                    continue;
                }
                $line = (int) $matches['line'];
                $message = 'jshint says: ' . trim($matches['error']);
                $phpcs_file->add_warning_on_line($message, $line, 'ExternalTool');
            }
        }
        // Ignore the rest of the file.
        return $phpcs_file->num_tokens + 1;
    }
    //end process()
}
//end class