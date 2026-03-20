<?php

declare (strict_types=1);
/**
 * Ensures that widgets are not manually created.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\My_Source\Sniffs\Objects;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Disallow_New_Widget_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_NEW];
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
        $class_name = $phpcs_file->find_next(T_WHITESPACE, $stack_ptr + 1, null, true);
        if ($tokens[$class_name]['code'] !== T_STRING) {
            return;
        }
        if (substr(strtolower($tokens[$class_name]['content']), -10) === 'widgettype') {
            $widget_type = substr($tokens[$class_name]['content'], 0, -10);
            $error = 'Manual creation of widget objects is banned; use Widget::getWidget(\'%s\'); instead';
            $data = [$widget_type];
            $phpcs_file->add_error($error, $stack_ptr, 'Found', $data);
        }
    }
    //end process()
}
//end class