<?php

declare (strict_types=1);
/**
 * Functions for helping process standards.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Util;

use Php_code_Sniffer\Config;
class Standards
{
    /**
     * Get a list of paths where standards are installed.
     *
     * Unresolvable relative paths will be excluded from the results.
     *
     * @return array
     */
    public static function get_installed_standard_paths()
    {
        $ds = DIRECTORY_SEPARATOR;
        $installed_paths = [dirname(dirname(__DIR__)) . $ds . 'src' . $ds . 'Standards'];
        $config_paths = Config::get_config_data('installed_paths');
        if ($config_paths !== null) {
            $installed_paths = array_merge($installed_paths, explode(',', $config_paths));
        }
        $resolved_installed_paths = [];
        foreach ($installed_paths as $installed_path) {
            if (substr($installed_path, 0, 1) === '.') {
                $installed_path = Common::real_path(__DIR__ . $ds . '..' . $ds . '..' . $ds . $installed_path);
                if ($installed_path === false) {
                    continue;
                }
            }
            $resolved_installed_paths[] = $installed_path;
        }
        return $resolved_installed_paths;
    }
    //end getInstalledStandardPaths()
    /**
     * Get the details of all coding standards installed.
     *
     * Coding standards are directories located in the
     * CodeSniffer/Standards directory. Valid coding standards
     * include a Sniffs subdirectory.
     *
     * The details returned for each standard are:
     * - path:      the path to the coding standard's main directory
     * - name:      the name of the coding standard, as sourced from the ruleset.xml file
     * - namespace: the namespace used by the coding standard, as sourced from the ruleset.xml file
     *
     * If you only need the paths to the installed standards,
     * use getInstalledStandardPaths() instead as it performs less work to
     * retrieve coding standard names.
     *
     * @param boolean $includeGeneric If true, the special "Generic"
     *                                coding standard will be included
     *                                if installed.
     * @param string  $standardsDir   A specific directory to look for standards
     *                                in. If not specified, PHP_CodeSniffer will
     *                                look in its default locations.
     *
     * @return array
     * @see    getInstalledStandardPaths()
     */
    public static function get_installed_standard_details($include_generic = false, $standards_dir = '')
    {
        $rulesets = [];
        if ($standards_dir === '') {
            $installed_paths = self::get_installed_standard_paths();
        } else {
            $installed_paths = [$standards_dir];
        }
        foreach ($installed_paths as $standards_dir) {
            // Check if the installed dir is actually a standard itself.
            $cs_file = $standards_dir . '/ruleset.xml';
            if (is_file($cs_file) === true) {
                $rulesets[] = $cs_file;
                continue;
            }
            if (is_dir($standards_dir) === false) {
                continue;
            }
            $di = new \Directory_Iterator($standards_dir);
            foreach ($di as $file) {
                if ($file->is_dir() === true && $file->is_dot() === false) {
                    $filename = $file->get_filename();
                    // Ignore the special "Generic" standard.
                    if ($include_generic === false && $filename === 'Generic') {
                        continue;
                    }
                    // Valid coding standard dirs include a ruleset.
                    $cs_file = $file->get_pathname() . '/ruleset.xml';
                    if (is_file($cs_file) === true) {
                        $rulesets[] = $cs_file;
                    }
                }
            }
        }
        //end foreach
        $installed_standards = [];
        foreach ($rulesets as $ruleset_path) {
            $ruleset = @simplexml_load_string(file_get_contents($ruleset_path));
            if ($ruleset === false) {
                continue;
            }
            $standard_name = (string) $ruleset['name'];
            $dirname = basename(dirname($ruleset_path));
            if (isset($ruleset['namespace']) === true) {
                $namespace = (string) $ruleset['namespace'];
            } else {
                $namespace = $dirname;
            }
            $installed_standards[$dirname] = ['path' => dirname($ruleset_path), 'name' => $standard_name, 'namespace' => $namespace];
        }
        //end foreach
        return $installed_standards;
    }
    //end getInstalledStandardDetails()
    /**
     * Get a list of all coding standards installed.
     *
     * Coding standards are directories located in the
     * CodeSniffer/Standards directory. Valid coding standards
     * include a Sniffs subdirectory.
     *
     * @param boolean $includeGeneric If true, the special "Generic"
     *                                coding standard will be included
     *                                if installed.
     * @param string  $standardsDir   A specific directory to look for standards
     *                                in. If not specified, PHP_CodeSniffer will
     *                                look in its default locations.
     *
     * @return array
     * @see    isInstalledStandard()
     */
    public static function get_installed_standards($include_generic = false, $standards_dir = '')
    {
        $installed_standards = [];
        if ($standards_dir === '') {
            $installed_paths = self::get_installed_standard_paths();
        } else {
            $installed_paths = [$standards_dir];
        }
        foreach ($installed_paths as $standards_dir) {
            // Check if the installed dir is actually a standard itself.
            $cs_file = $standards_dir . '/ruleset.xml';
            if (is_file($cs_file) === true) {
                $basename = basename($standards_dir);
                $installed_standards[$basename] = $basename;
                continue;
            }
            if (is_dir($standards_dir) === false) {
                // Doesn't exist.
                continue;
            }
            $di = new \Directory_Iterator($standards_dir);
            $standards_in_dir = [];
            foreach ($di as $file) {
                if ($file->is_dir() === true && $file->is_dot() === false) {
                    $filename = $file->get_filename();
                    // Ignore the special "Generic" standard.
                    if ($include_generic === false && $filename === 'Generic') {
                        continue;
                    }
                    // Valid coding standard dirs include a ruleset.
                    $cs_file = $file->get_pathname() . '/ruleset.xml';
                    if (is_file($cs_file) === true) {
                        $standards_in_dir[$filename] = $filename;
                    }
                }
            }
            natsort($standards_in_dir);
            $installed_standards += $standards_in_dir;
        }
        //end foreach
        return $installed_standards;
    }
    //end getInstalledStandards()
    /**
     * Determine if a standard is installed.
     *
     * Coding standards are directories located in the
     * CodeSniffer/Standards directory. Valid coding standards
     * include a ruleset.xml file.
     *
     * @param string $standard The name of the coding standard.
     *
     * @return boolean
     * @see    getInstalledStandards()
     */
    public static function is_installed_standard($standard)
    {
        $path = self::get_installed_standard_path($standard);
        if ($path !== null && strpos($path, 'ruleset.xml') !== false) {
            return true;
        }
        // This could be a custom standard, installed outside our
        // standards directory.
        $standard = Common::real_path($standard);
        if ($standard === false) {
            return false;
        }
        // Might be an actual ruleset file itUtil.
        // If it has an XML extension, let's at least try it.
        if (is_file($standard) === true && (substr(strtolower($standard), -4) === '.xml' || substr(strtolower($standard), -9) === '.xml.dist')) {
            return true;
        }
        // If it is a directory with a ruleset.xml file in it,
        // it is a standard.
        $ruleset = rtrim($standard, ' /\\') . DIRECTORY_SEPARATOR . 'ruleset.xml';
        if (is_file($ruleset) === true) {
            return true;
        }
        //end if
        return false;
    }
    //end isInstalledStandard()
    /**
     * Return the path of an installed coding standard.
     *
     * Coding standards are directories located in the
     * CodeSniffer/Standards directory. Valid coding standards
     * include a ruleset.xml file.
     *
     * @param string $standard The name of the coding standard.
     *
     * @return string|null
     */
    public static function get_installed_standard_path($standard)
    {
        if (strpos($standard, '.') !== false) {
            return null;
        }
        $installed_paths = self::get_installed_standard_paths();
        foreach ($installed_paths as $installed_path) {
            $standard_path = $installed_path . DIRECTORY_SEPARATOR . $standard;
            if (file_exists($standard_path) === false) {
                if (basename($installed_path) !== $standard) {
                    continue;
                }
                $standard_path = $installed_path;
            }
            $path = Common::realpath($standard_path . DIRECTORY_SEPARATOR . 'ruleset.xml');
            if ($path !== false && is_file($path) === true) {
                return $path;
            }
            if (Common::is_phar_file($standard_path) === true) {
                $path = Common::realpath($standard_path);
                if ($path !== false) {
                    return $path;
                }
            }
        }
        //end foreach
        return null;
    }
    //end getInstalledStandardPath()
    /**
     * Prints out a list of installed coding standards.
     *
     * @return void
     */
    public static function print_installed_standards()
    {
        $installed_standards = self::get_installed_standards();
        $num_standards = count($installed_standards);
        if ($num_standards === 0) {
            echo 'No coding standards are installed.' . PHP_EOL;
        } else {
            $last_standard = array_pop($installed_standards);
            if ($num_standards === 1) {
                echo "The only coding standard installed is {$last_standard}" . PHP_EOL;
            } else {
                $standard_list = implode(', ', $installed_standards);
                $standard_list .= ' and ' . $last_standard;
                echo 'The installed coding standards are ' . $standard_list . PHP_EOL;
            }
        }
    }
    //end printInstalledStandards()
}
//end class