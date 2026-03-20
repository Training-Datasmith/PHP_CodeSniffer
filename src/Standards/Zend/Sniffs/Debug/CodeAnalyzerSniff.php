<?php

declare (strict_types=1);
/**
 * Runs the Zend Code Analyzer (from Zend Studio) on the file.
 *
 * @author    Holger Kral <holger.kral@zend.com>
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Zend\Sniffs\Debug;

use Php_code_Sniffer\Config;
use Php_code_Sniffer\Exceptions\RuntimeException;
use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Common;
class Code_Analyzer_Sniff implements Sniff
{
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
     * @return int
     * @throws \PHP_CodeSniffer\Exceptions\RuntimeException If ZendCodeAnalyzer could not be run.
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $analyzer_path = Config::get_executable_path('zend_ca');
        if ($analyzer_path === null) {
            return;
        }
        $file_name = $phpcs_file->get_filename();
        // In the command, 2>&1 is important because the code analyzer sends its
        // findings to stderr. $output normally contains only stdout, so using 2>&1
        // will pipe even stderr to stdout.
        $cmd = Common::escapeshellcmd($analyzer_path) . ' ' . escapeshellarg($file_name) . ' 2>&1';
        // There is the possibility to pass "--ide" as an option to the analyzer.
        // This would result in an output format which would be easier to parse.
        // The problem here is that no cleartext error messages are returned; only
        // error-code-labels. So for a start we go for cleartext output.
        $exit_code = exec($cmd, $output, $retval);
        // Variable $exitCode is the last line of $output if no error occurs, on
        // error it is numeric. Try to handle various error conditions and
        // provide useful error reporting.
        if (is_numeric($exit_code) === true && $exit_code > 0) {
            if (is_array($output) === true) {
                $msg = join('\n', $output);
            }
            throw new RuntimeException("Failed invoking ZendCodeAnalyzer, exitcode was [{$exit_code}], retval was [{$retval}], output was [{$msg}]");
        }
        if (is_array($output) === true) {
            foreach ($output as $finding) {
                // The first two lines of analyzer output contain
                // something like this:
                // > Zend Code Analyzer 1.2.2
                // > Analyzing <filename>...
                // So skip these...
                $res = preg_match("/^.+\\(line ([0-9]+)\\):(.+)\$/", $finding, $regs);
                if (empty($regs) === true) {
                    continue;
                }
                if ($res === false) {
                    continue;
                }
                $phpcs_file->add_warning_on_line(trim($regs[2]), $regs[1], 'ExternalTool');
            }
        }
        // Ignore the rest of the file.
        return $phpcs_file->num_tokens + 1;
    }
    //end process()
}
//end class