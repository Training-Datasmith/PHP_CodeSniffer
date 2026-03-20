<?php

declare (strict_types=1);
/**
 * Runs JavaScript Lint on the file.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\Debug;

use Php_code_Sniffer\Config;
use Php_code_Sniffer\Exceptions\RuntimeException;
use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Common;
class Java_Script_Lint_Sniff implements Sniff
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
     * @throws \PHP_CodeSniffer\Exceptions\RuntimeException If Javascript Lint ran into trouble.
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $jsl_path = Config::get_executable_path('jsl');
        if ($jsl_path === null) {
            return;
        }
        $file_name = $phpcs_file->get_filename();
        $cmd = '"' . Common::escapeshellcmd($jsl_path) . '" -nologo -nofilelisting -nocontext -nosummary -output-format __LINE__:__ERROR__ -process ' . escapeshellarg($file_name);
        $msg = exec($cmd, $output, $retval);
        // Variable $exitCode is the last line of $output if no error occurs, on
        // error it is numeric. Try to handle various error conditions and
        // provide useful error reporting.
        if ($retval === 2 || $retval === 4) {
            if (is_array($output) === true) {
                $msg = join('\n', $output);
            }
            throw new RuntimeException("Failed invoking JavaScript Lint, retval was [{$retval}], output was [{$msg}]");
        }
        if (is_array($output) === true) {
            foreach ($output as $finding) {
                $split = strpos($finding, ':');
                $line = substr($finding, 0, $split);
                $message = substr($finding, $split + 1);
                $phpcs_file->add_warning_on_line(trim($message), $line, 'ExternalTool');
            }
        }
        // Ignore the rest of the file.
        return $phpcs_file->num_tokens + 1;
    }
    //end process()
}
//end class