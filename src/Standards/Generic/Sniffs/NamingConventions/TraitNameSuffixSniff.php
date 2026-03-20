<?php

declare (strict_types=1);
/**
 * Checks that traits are suffixed by Trait.
 *
 * @author  Anna Borzenko <annnechko@gmail.com>
 * @license https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Generic\Sniffs\Naming_Conventions;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Trait_Name_Suffix_Sniff implements Sniff
{
    /**
     * Registers the tokens that this sniff wants to listen for.
     *
     * @return int[]
     */
    public function register()
    {
        return [T_TRAIT];
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
        $trait_name = $phpcs_file->get_declaration_name($stack_ptr);
        if ($trait_name === null) {
            return;
        }
        $suffix = substr($trait_name, -5);
        if (strtolower($suffix) !== 'trait') {
            $phpcs_file->add_error('Trait names must be suffixed with "Trait"; found "%s"', $stack_ptr, 'Missing', [$trait_name]);
        }
    }
    //end process()
}
//end class