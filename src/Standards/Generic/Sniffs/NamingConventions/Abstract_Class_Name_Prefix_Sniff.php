<?php

declare (strict_types=1);
/**
 * Checks that abstract classes are prefixed by Abstract.
 *
 * @author  Anna Borzenko <annnechko@gmail.com>
 * @license https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Generic\Sniffs\Naming_Conventions;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Abstract_Class_Name_Prefix_Sniff implements Sniff
{
    /**
     * Registers the tokens that this sniff wants to listen for.
     *
     * @return int[]
     */
    public function register()
    {
        return [T_CLASS];
    }
    //end register()
    /**
     * Processes this sniff, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token
     *                                               in the stack passed in $tokens.
     *
     * @return void
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        if ($phpcs_file->get_class_properties($stack_ptr)['is_abstract'] === false) {
            // This class is not abstract so we don't need to check it.
            return;
        }
        $class_name = $phpcs_file->get_declaration_name($stack_ptr);
        if ($class_name === null) {
            // We are not interested in anonymous classes.
            return;
        }
        $prefix = substr($class_name, 0, 8);
        if (strtolower($prefix) !== 'abstract') {
            $phpcs_file->add_error('Abstract class names must be prefixed with "Abstract"; found "%s"', $stack_ptr, 'Missing', [$class_name]);
        }
    }
    //end process()
}
//end class