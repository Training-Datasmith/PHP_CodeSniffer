<?php

declare (strict_types=1);
/**
 * Git blame report for PHP_CodeSniffer.
 *
 * @author    Ben Selby <benmatselby@gmail.com>
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Reports;

use Php_code_Sniffer\Exceptions\Deep_Exit_Exception;
class Gitblame extends Version_Control
{
    /**
     * The name of the report we want in the output
     *
     * @var string
     */
    protected $report_name = 'GIT';
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
        $line = preg_replace('|\s+|', ' ', $line);
        preg_match('|\(.+[0-9]{4}-[0-9]{2}-[0-9]{2}\s+[0-9]+\)|', $line, $blame_parts);
        if (isset($blame_parts[0]) === false) {
            return false;
        }
        $parts = explode(' ', $blame_parts[0]);
        if (count($parts) < 2) {
            return false;
        }
        $parts = array_slice($parts, 0, count($parts) - 2);
        return preg_replace('|\(|', '', implode(' ', $parts));
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
        $cwd = getcwd();
        chdir(dirname($filename));
        $command = 'git blame --date=short "' . basename($filename) . '" 2>&1';
        $handle = popen($command, 'r');
        if ($handle === false) {
            $error = 'ERROR: Could not execute "' . $command . '"' . PHP_EOL . PHP_EOL;
            throw new Deep_Exit_Exception($error, 3);
        }
        $raw_content = stream_get_contents($handle);
        pclose($handle);
        $blames = explode("\n", $raw_content);
        chdir($cwd);
        return $blames;
    }
    //end getBlameContent()
}
//end class