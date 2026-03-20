<?php

declare (strict_types=1);
/**
 * Ensures that systems, asset types and libs are included before they are used.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\My_Source\Sniffs\Channels;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Abstract_Scope_Sniff;
use Php_code_Sniffer\Util\Tokens;
class Include_System_Sniff extends Abstract_Scope_Sniff
{
    /**
     * A list of classes that don't need to be included.
     *
     * @var string[]
     */
    private $ignore = ['self' => true, 'static' => true, 'parent' => true, 'channels' => true, 'basesystem' => true, 'dal' => true, 'init' => true, 'pdo' => true, 'util' => true, 'ziparchive' => true, 'phpunit_framework_assert' => true, 'abstractmysourceunittest' => true, 'abstractdatacleanunittest' => true, 'exception' => true, 'abstractwidgetwidgettype' => true, 'domdocument' => true];
    /**
     * Constructs an AbstractScopeSniff.
     */
    public function __construct()
    {
        parent::__construct([T_FUNCTION], [T_DOUBLE_COLON, T_EXTENDS], true);
    }
    //end __construct()
    /**
     * Processes the function tokens within the class.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file where this token was found.
     * @param integer                     $stackPtr  The position where the token was found.
     * @param integer                     $currScope The current scope opener token.
     *
     * @return void
     */
    protected function process_token_within_scope(File $phpcs_file, $stack_ptr, $curr_scope)
    {
        $tokens = $phpcs_file->get_tokens();
        // Determine the name of the class that the static function
        // is being called on.
        $class_name_token = $phpcs_file->find_previous(T_WHITESPACE, $stack_ptr - 1, null, true);
        // Don't process class names represented by variables as this can be
        // an inexact science.
        if ($tokens[$class_name_token]['code'] === T_VARIABLE) {
            return;
        }
        $class_name = $tokens[$class_name_token]['content'];
        if (isset($this->ignore[strtolower($class_name)]) === true) {
            return;
        }
        $included_classes = [];
        $file_name = strtolower($phpcs_file->get_filename());
        $matches = [];
        if (preg_match('|/systems/(.*)/([^/]+)?actions.inc$|', $file_name, $matches) !== 0) {
            // This is an actions file, which means we don't
            // have to include the system in which it exists.
            $included_classes[$matches[2]] = true;
            // Or a system it implements.
            $class = $phpcs_file->get_condition($stack_ptr, T_CLASS);
            $implements = $phpcs_file->find_next(T_IMPLEMENTS, $class, $class + 10);
            if ($implements !== false) {
                $implements_class = $phpcs_file->find_next(T_STRING, $implements);
                $implements_class_name = strtolower($tokens[$implements_class]['content']);
                if (substr($implements_class_name, -7) === 'actions') {
                    $included_classes[substr($implements_class_name, 0, -7)] = true;
                }
            }
        }
        // Go searching for includeSystem and includeAsset calls within this
        // function, or the inclusion of .inc files, which
        // would be library files.
        for ($i = $curr_scope + 1; $i < $stack_ptr; $i++) {
            $name = $this->get_included_class_from_token($phpcs_file, $tokens, $i);
            if ($name !== false) {
                $included_classes[$name] = true;
                // Special case for Widgets cause they are, well, special.
            } elseif (strtolower($tokens[$i]['content']) === 'includewidget') {
                $type_name = $phpcs_file->find_next(T_CONSTANT_ENCAPSED_STRING, $i + 1);
                $type_name = trim($tokens[$type_name]['content'], " '");
                $included_classes[strtolower($type_name) . 'widgettype'] = true;
            }
        }
        // Now go searching for includeSystem, includeAsset or require/include
        // calls outside our scope. If we are in a class, look outside the
        // class. If we are not, look outside the function.
        $cond_ptr = $curr_scope;
        if ($phpcs_file->has_condition($stack_ptr, T_CLASS) === true) {
            foreach ($tokens[$stack_ptr]['conditions'] as $cond_type) {
                if ($cond_type === T_CLASS) {
                    break;
                }
            }
        }
        for ($i = 0; $i < $cond_ptr; $i++) {
            // Skip other scopes.
            if (isset($tokens[$i]['scope_closer']) === true) {
                $i = $tokens[$i]['scope_closer'];
                continue;
            }
            $name = $this->get_included_class_from_token($phpcs_file, $tokens, $i);
            if ($name !== false) {
                $included_classes[$name] = true;
            }
        }
        // If we are in a testing class, we might have also included
        // some systems and classes in our setUp() method.
        $setup_function = null;
        if ($phpcs_file->has_condition($stack_ptr, T_CLASS) === true) {
            foreach ($tokens[$stack_ptr]['conditions'] as $cond_ptr => $cond_type) {
                if ($cond_type === T_CLASS) {
                    // Is this is a testing class?
                    $name = $phpcs_file->find_next(T_STRING, $cond_ptr);
                    $name = $tokens[$name]['content'];
                    if (substr($name, -8) === 'UnitTest') {
                        // Look for a method called setUp().
                        $end = $tokens[$cond_ptr]['scope_closer'];
                        $function = $phpcs_file->find_next(T_FUNCTION, $cond_ptr + 1, $end);
                        while ($function !== false) {
                            $name = $phpcs_file->find_next(T_STRING, $function);
                            if ($tokens[$name]['content'] === 'setUp') {
                                $setup_function = $function;
                                break;
                            }
                            $function = $phpcs_file->find_next(T_FUNCTION, $function + 1, $end);
                        }
                    }
                }
            }
            //end foreach
        }
        //end if
        if ($setup_function !== null) {
            $start = $tokens[$setup_function]['scope_opener'] + 1;
            $end = $tokens[$setup_function]['scope_closer'];
            for ($i = $start; $i < $end; $i++) {
                $name = $this->get_included_class_from_token($phpcs_file, $tokens, $i);
                if ($name !== false) {
                    $included_classes[$name] = true;
                }
            }
        }
        //end if
        if (isset($included_classes[strtolower($class_name)]) === false) {
            $error = 'Static method called on non-included class or system "%s"; include system with Channels::includeSystem() or include class with require_once';
            $data = [$class_name];
            $phpcs_file->add_error($error, $stack_ptr, 'NotIncludedCall', $data);
        }
    }
    //end processTokenWithinScope()
    /**
     * Processes a token within the scope that this test is listening to.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file where the token was found.
     * @param int                         $stackPtr  The position in the stack where
     *                                               this token was found.
     *
     * @return void
     */
    protected function process_token_outside_scope(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        if ($tokens[$stack_ptr]['code'] === T_EXTENDS) {
            // Find the class name.
            $class_name_token = $phpcs_file->find_next(T_STRING, $stack_ptr + 1);
            $class_name = $tokens[$class_name_token]['content'];
        } else {
            // Determine the name of the class that the static function
            // is being called on. But don't process class names represented by
            // variables as this can be an inexact science.
            $class_name_token = $phpcs_file->find_previous(T_WHITESPACE, $stack_ptr - 1, null, true);
            if ($tokens[$class_name_token]['code'] === T_VARIABLE) {
                return;
            }
            $class_name = $tokens[$class_name_token]['content'];
        }
        // Some systems are always available.
        if (isset($this->ignore[strtolower($class_name)]) === true) {
            return;
        }
        $included_classes = [];
        $file_name = strtolower($phpcs_file->get_filename());
        $matches = [];
        if (preg_match('|/systems/([^/]+)/([^/]+)?actions.inc$|', $file_name, $matches) !== 0) {
            // This is an actions file, which means we don't
            // have to include the system in which it exists
            // We know the system from the path.
            $included_classes[$matches[1]] = true;
        }
        // Go searching for includeSystem, includeAsset or require/include
        // calls outside our scope.
        for ($i = 0; $i < $stack_ptr; $i++) {
            // Skip classes and functions as will we never get
            // into their scopes when including this file, although
            // we have a chance of getting into IF, WHILE etc.
            if (($tokens[$i]['code'] === T_CLASS || $tokens[$i]['code'] === T_INTERFACE || $tokens[$i]['code'] === T_FUNCTION) && isset($tokens[$i]['scope_closer']) === true) {
                $i = $tokens[$i]['scope_closer'];
                continue;
            }
            $name = $this->get_included_class_from_token($phpcs_file, $tokens, $i);
            if ($name !== false) {
                $included_classes[$name] = true;
                // Special case for Widgets cause they are, well, special.
            } elseif (strtolower($tokens[$i]['content']) === 'includewidget') {
                $type_name = $phpcs_file->find_next(T_CONSTANT_ENCAPSED_STRING, $i + 1);
                $type_name = trim($tokens[$type_name]['content'], " '");
                $included_classes[strtolower($type_name) . 'widgettype'] = true;
            }
        }
        //end for
        if (isset($included_classes[strtolower($class_name)]) === false) {
            if ($tokens[$stack_ptr]['code'] === T_EXTENDS) {
                $error = 'Class extends non-included class or system "%s"; include system with Channels::includeSystem() or include class with require_once';
                $data = [$class_name];
                $phpcs_file->add_error($error, $stack_ptr, 'NotIncludedExtends', $data);
            } else {
                $error = 'Static method called on non-included class or system "%s"; include system with Channels::includeSystem() or include class with require_once';
                $data = [$class_name];
                $phpcs_file->add_error($error, $stack_ptr, 'NotIncludedCall', $data);
            }
        }
    }
    //end processTokenOutsideScope()
    /**
     * Determines the included class name from given token.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file where this token was found.
     * @param array                       $tokens    The array of file tokens.
     * @param int                         $stackPtr  The position in the tokens array of the
     *                                               potentially included class.
     *
     * @return string
     */
    protected function get_included_class_from_token(File $phpcs_file, array $tokens, $stack_ptr)
    {
        if (strtolower($tokens[$stack_ptr]['content']) === 'includesystem') {
            $system_name = $phpcs_file->find_next(T_CONSTANT_ENCAPSED_STRING, $stack_ptr + 1);
            $system_name = trim($tokens[$system_name]['content'], " '");
            return strtolower($system_name);
        }
        if (strtolower($tokens[$stack_ptr]['content']) === 'includeasset') {
            $type_name = $phpcs_file->find_next(T_CONSTANT_ENCAPSED_STRING, $stack_ptr + 1);
            $type_name = trim($tokens[$type_name]['content'], " '");
            return strtolower($type_name) . 'assettype';
        }
        if (isset(Tokens::$include_tokens[$tokens[$stack_ptr]['code']]) === true) {
            $file_path = $phpcs_file->find_next(T_CONSTANT_ENCAPSED_STRING, $stack_ptr + 1);
            $file_path = $tokens[$file_path]['content'];
            $file_path = trim($file_path, " '");
            $file_path = basename($file_path, '.inc');
            return strtolower($file_path);
        }
        return false;
    }
    //end getIncludedClassFromToken()
}
//end class