<?php

declare (strict_types=1);
/**
 * Reports errors if the same class or interface name is used in multiple files.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Generic\Sniffs\Classes;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Duplicate_Class_Name_Sniff implements Sniff
{
    /**
     * List of classes that have been found during checking.
     *
     * @var array
     */
    protected $found_classes = [];
    /**
     * Registers the tokens that this sniff wants to listen for.
     *
     * @return int[]
     */
    public function register()
    {
        return [T_OPEN_TAG];
    }
    //end register()
    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token
     *                                               in the stack passed in $tokens.
     *
     * @return void
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        $namespace = '';
        $find_tokens = [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM, T_NAMESPACE, T_CLOSE_TAG];
        $stack_ptr = $phpcs_file->find_next($find_tokens, $stack_ptr + 1);
        while ($stack_ptr !== false) {
            if ($tokens[$stack_ptr]['code'] === T_CLOSE_TAG) {
                // We can stop here. The sniff will continue from the next open
                // tag when PHPCS reaches that token, if there is one.
                return;
            }
            // Keep track of what namespace we are in.
            if ($tokens[$stack_ptr]['code'] === T_NAMESPACE) {
                $ns_end = $phpcs_file->find_next([T_NS_SEPARATOR, T_STRING, T_WHITESPACE], $stack_ptr + 1, null, true);
                $namespace = trim($phpcs_file->get_tokens_as_string($stack_ptr + 1, $ns_end - $stack_ptr - 1));
                $stack_ptr = $ns_end;
            } else {
                $name_token = $phpcs_file->find_next(T_STRING, $stack_ptr);
                $name = $tokens[$name_token]['content'];
                if ($namespace !== '') {
                    $name = $namespace . '\\' . $name;
                }
                $compare_name = strtolower($name);
                if (isset($this->found_classes[$compare_name]) === true) {
                    $type = strtolower($tokens[$stack_ptr]['content']);
                    $file = $this->found_classes[$compare_name]['file'];
                    $line = $this->found_classes[$compare_name]['line'];
                    $error = 'Duplicate %s name "%s" found; first defined in %s on line %s';
                    $data = [$type, $name, $file, $line];
                    $phpcs_file->add_warning($error, $stack_ptr, 'Found', $data);
                } else {
                    $this->found_classes[$compare_name] = ['file' => $phpcs_file->get_filename(), 'line' => $tokens[$stack_ptr]['line']];
                }
            }
            //end if
            $stack_ptr = $phpcs_file->find_next($find_tokens, $stack_ptr + 1);
        }
        //end while
    }
    //end process()
}
//end class