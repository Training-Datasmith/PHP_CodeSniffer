<?php

declare (strict_types=1);
/**
 * Checks the declaration of the class is correct.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\PSR1\Sniffs\Classes;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Class_Declaration_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM];
    }
    //end register()
    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param integer                     $stackPtr  The position of the current token in
     *                                               the token stack.
     *
     * @return void
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        if (isset($tokens[$stack_ptr]['scope_closer']) === false) {
            return;
        }
        $error_data = [strtolower($tokens[$stack_ptr]['content'])];
        $next_class = $phpcs_file->find_next([T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], $tokens[$stack_ptr]['scope_closer'] + 1);
        if ($next_class !== false) {
            $error = 'Each %s must be in a file by itself';
            $phpcs_file->add_error($error, $next_class, 'MultipleClasses', $error_data);
            $phpcs_file->record_metric($stack_ptr, 'One class per file', 'no');
        } else {
            $phpcs_file->record_metric($stack_ptr, 'One class per file', 'yes');
        }
        $namespace = $phpcs_file->find_next([T_NAMESPACE, T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], 0);
        if ($tokens[$namespace]['code'] !== T_NAMESPACE) {
            $error = 'Each %s must be in a namespace of at least one level (a top-level vendor name)';
            $phpcs_file->add_error($error, $stack_ptr, 'MissingNamespace', $error_data);
            $phpcs_file->record_metric($stack_ptr, 'Class defined in namespace', 'no');
        } else {
            $phpcs_file->record_metric($stack_ptr, 'Class defined in namespace', 'yes');
        }
    }
    //end process()
}
//end class