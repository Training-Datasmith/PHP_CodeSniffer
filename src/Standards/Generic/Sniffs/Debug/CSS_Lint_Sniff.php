<?php

declare (strict_types=1);
/**
 * Runs csslint on the file.
 *
 * @author    Roman Levishchenko <index.0h@gmail.com>
 * @copyright 2013-2014 Roman Levishchenko
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Generic\Sniffs\Debug;

use Php_code_Sniffer\Config;
use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Common;
class Css_Lint_Sniff implements Sniff
{
    /**
     * A list of tokenizers this sniff supports.
     *
     * @var array
     */
    public $supported_tokenizers = ['CSS'];
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
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $csslint_path = Config::get_executable_path('csslint');
        if ($csslint_path === null) {
            return;
        }
        $file_name = $phpcs_file->get_filename();
        $cmd = Common::escapeshellcmd($csslint_path) . ' ' . escapeshellarg($file_name) . ' 2>&1';
        exec($cmd, $output, $retval);
        if (is_array($output) === false) {
            return;
        }
        $count = count($output);
        for ($i = 0; $i < $count; $i++) {
            $matches = [];
            $num_matches = preg_match('/(error|warning) at line (\d+)/', $output[$i], $matches);
            if ($num_matches === 0) {
                continue;
            }
            $line = (int) $matches[2];
            $message = 'csslint says: ' . $output[$i + 1];
            // First line is message with error line and error code.
            // Second is error message.
            // Third is wrong line in file.
            // Fourth is empty line.
            $i += 4;
            $phpcs_file->add_warning_on_line($message, $line, 'ExternalTool');
        }
        //end for
        // Ignore the rest of the file.
        return $phpcs_file->num_tokens + 1;
    }
    //end process()
}
//end class