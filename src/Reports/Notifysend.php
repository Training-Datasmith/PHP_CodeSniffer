<?php

declare (strict_types=1);
/**
 * Notify-send report for PHP_CodeSniffer.
 *
 * Supported configuration parameters:
 * - notifysend_path    - Full path to notify-send cli command
 * - notifysend_timeout - Timeout in milliseconds
 * - notifysend_showok  - Show "ok, all fine" messages (0/1)
 *
 * @author    Christian Weiske <christian.weiske@netresearch.de>
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2012-2014 Christian Weiske
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Reports;

use Php_code_Sniffer\Config;
use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Util\Common;
class Notifysend implements Report
{
    /**
     * Notification timeout in milliseconds.
     *
     * @var integer
     */
    protected $timeout = 3000;
    /**
     * Path to notify-send command.
     *
     * @var string
     */
    protected $path = 'notify-send';
    /**
     * Show "ok, all fine" messages.
     *
     * @var boolean
     */
    protected $show_ok = true;
    /**
     * Version of installed notify-send executable.
     *
     * @var string
     */
    protected $version;
    /**
     * Load configuration data.
     */
    public function __construct()
    {
        $path = Config::get_executable_path('notifysend');
        if ($path !== null) {
            $this->path = Common::escapeshellcmd($path);
        }
        $timeout = Config::get_config_data('notifysend_timeout');
        if ($timeout !== null) {
            $this->timeout = (int) $timeout;
        }
        $show_ok = Config::get_config_data('notifysend_showok');
        if ($show_ok !== null) {
            $this->show_ok = (bool) $show_ok;
        }
        $this->version = str_replace('notify-send ', '', exec($this->path . ' --version'));
    }
    //end __construct()
    /**
     * Generate a partial report for a single processed file.
     *
     * Function should return TRUE if it printed or stored data about the file
     * and FALSE if it ignored the file. Returning TRUE indicates that the file and
     * its data should be counted in the grand totals.
     *
     * @param array                       $report      Prepared report data.
     * @param \PHP_CodeSniffer\Files\File $phpcsFile   The file being reported on.
     * @param bool                        $showSources Show sources?
     * @param int                         $width       Maximum allowed line width.
     *
     * @return bool
     */
    public function generate_file_report($report, File $phpcs_file, $show_sources = false, $width = 80)
    {
        echo $report['filename'] . PHP_EOL;
        // We want this file counted in the total number
        // of checked files even if it has no errors.
        return true;
    }
    //end generateFileReport()
    /**
     * Generates a summary of errors and warnings for each file processed.
     *
     * @param string $cachedData    Any partial report data that was returned from
     *                              generateFileReport during the run.
     * @param int    $totalFiles    Total number of files processed during the run.
     * @param int    $totalErrors   Total number of errors found during the run.
     * @param int    $totalWarnings Total number of warnings found during the run.
     * @param int    $totalFixable  Total number of problems that can be fixed.
     * @param bool   $showSources   Show sources?
     * @param int    $width         Maximum allowed line width.
     * @param bool   $interactive   Are we running in interactive mode?
     * @param bool   $toScreen      Is the report being printed to screen?
     *
     * @return void
     */
    public function generate($cached_data, $total_files, $total_errors, $total_warnings, $total_fixable, $show_sources = false, $width = 80, $interactive = false, $to_screen = true)
    {
        $checked_files = explode(PHP_EOL, trim($cached_data));
        $msg = $this->generate_message($checked_files, $total_errors, $total_warnings);
        if ($msg === null) {
            if ($this->show_ok === true) {
                $this->notify_all_fine();
            }
        } else {
            $this->notify_errors($msg);
        }
    }
    //end generate()
    /**
     * Generate the error message to show to the user.
     *
     * @param string[] $checkedFiles  The files checked during the run.
     * @param int      $totalErrors   Total number of errors found during the run.
     * @param int      $totalWarnings Total number of warnings found during the run.
     *
     * @return string Error message or NULL if no error/warning found.
     */
    protected function generate_message(array $checked_files, $total_errors, $total_warnings)
    {
        if ($total_errors === 0 && $total_warnings === 0) {
            // Nothing to print.
            return null;
        }
        $total_files = count($checked_files);
        $msg = '';
        if ($total_files > 1) {
            $msg .= 'Checked ' . $total_files . ' files' . PHP_EOL;
        } else {
            $msg .= $checked_files[0] . PHP_EOL;
        }
        if ($total_warnings > 0) {
            $msg .= $total_warnings . ' warnings' . PHP_EOL;
        }
        if ($total_errors > 0) {
            $msg .= $total_errors . ' errors' . PHP_EOL;
        }
        return $msg;
    }
    //end generateMessage()
    /**
     * Tell the user that all is fine and no error/warning has been found.
     *
     * @return void
     */
    protected function notify_all_fine()
    {
        $cmd = $this->get_basic_command();
        $cmd .= ' -i info';
        $cmd .= ' "PHP CodeSniffer: Ok"';
        $cmd .= ' "All fine"';
        exec($cmd);
    }
    //end notifyAllFine()
    /**
     * Tell the user that errors/warnings have been found.
     *
     * @param string $msg Message to display.
     *
     * @return void
     */
    protected function notify_errors($msg)
    {
        $cmd = $this->get_basic_command();
        $cmd .= ' -i error';
        $cmd .= ' "PHP CodeSniffer: Error"';
        $cmd .= ' ' . escapeshellarg(trim($msg));
        exec($cmd);
    }
    //end notifyErrors()
    /**
     * Generate and return the basic notify-send command string to execute.
     *
     * @return string Shell command with common parameters.
     */
    protected function get_basic_command()
    {
        $cmd = $this->path;
        $cmd .= ' --category dev.validate';
        $cmd .= ' -h int:transient:1';
        $cmd .= ' -t ' . (int) $this->timeout;
        if (version_compare($this->version, '0.7.3', '>=') === true) {
            $cmd .= ' -a phpcs';
        }
        return $cmd;
    }
    //end getBasicCommand()
}
//end class