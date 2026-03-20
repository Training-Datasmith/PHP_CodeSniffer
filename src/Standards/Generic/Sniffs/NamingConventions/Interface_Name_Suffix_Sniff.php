<?php

declare (strict_types=1);
/**
 * Checks that interfaces are suffixed by Interface.
 *
 * @author  Anna Borzenko <annnechko@gmail.com>
 * @license https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Generic\Sniffs\Naming_Conventions;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Interface_Name_Suffix_Sniff implements Sniff
{
    /**
     * Registers the tokens that this sniff wants to listen for.
     *
     * @return int[]
     */
    public function register()
    {
        return [T_INTERFACE];
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
        $interface_name = $phpcs_file->get_declaration_name($stack_ptr);
        if ($interface_name === null) {
            return;
        }
        $suffix = substr($interface_name, -9);
        if (strtolower($suffix) !== 'interface') {
            $phpcs_file->add_error('Interface names must be suffixed with "Interface"; found "%s"', $stack_ptr, 'Missing', [$interface_name]);
        }
    }
    //end process()
}
//end class