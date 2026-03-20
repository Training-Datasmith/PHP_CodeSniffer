<?php

declare (strict_types=1);
/**
 * SVN blame report for PHP_CodeSniffer.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Reports;

use Php_code_Sniffer\Exceptions\Deep_Exit_Exception;
class Svnblame extends Version_Control
{
    /**
     * The name of the report we want in the output
     *
     * @var string
     */
    protected $report_name = 'SVN';
    /**
     * Extract the author from a blame line.
     *
     * @param string $line Line to parse.
     *
     * @return mixed string or false if impossible to recover.
     */
    protected function get_author($line)
    {
        $blame_parts = [];
        preg_match('|\s*([^\s]+)\s+([^\s]+)|', $line, $blame_parts);
        if (isset($blame_parts[2]) === false) {
            return false;
        }
        return $blame_parts[2];
    }
    //end getAuthor()
    /**
     * Gets the blame output.
     *
     * @param string $filename File to blame.
     *
     * @return array
     * @throws \PHP_CodeSniffer\Exceptions\DeepExitException
     */
    protected function get_blame_content($filename)
    {
        $command = 'svn blame "' . $filename . '" 2>&1';
        $handle = popen($command, 'r');
        if ($handle === false) {
            $error = 'ERROR: Could not execute "' . $command . '"' . PHP_EOL . PHP_EOL;
            throw new Deep_Exit_Exception($error, 3);
        }
        $raw_content = stream_get_contents($handle);
        pclose($handle);
        return explode("\n", $raw_content);
    }
    //end getBlameContent()
}
//end class