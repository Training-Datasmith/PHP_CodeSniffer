<?php

declare (strict_types=1);
/**
 * Ensures that a system does not include itself.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\My_Source\Sniffs\Channels;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Include_Own_System_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_DOUBLE_COLON];
    }
    //end register()
    /**
     * Processes this sniff, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token in
     *                                               the stack passed in $tokens.
     *
     * @return void
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $file_name = $phpcs_file->get_filename();
        $matches = [];
        if (preg_match('|/systems/(.*)/([^/]+)?actions.inc$|i', $file_name, $matches) === 0) {
            // Not an actions file.
            return;
        }
        $own_class = $matches[2];
        $tokens = $phpcs_file->get_tokens();
        $type_name = $phpcs_file->find_next(T_CONSTANT_ENCAPSED_STRING, $stack_ptr + 2, null, false, true);
        $type_name = trim($tokens[$type_name]['content'], " '");
        switch (strtolower($tokens[$stack_ptr + 1]['content'])) {
            case 'includesystem':
                $included = strtolower($type_name);
                break;
            case 'includeasset':
                $included = strtolower($type_name) . 'assettype';
                break;
            case 'includewidget':
                $included = strtolower($type_name) . 'widgettype';
                break;
            default:
                return;
        }
        if ($included === strtolower($own_class)) {
            $error = "You do not need to include \"%s\" from within the system's own actions file";
            $data = [$own_class];
            $phpcs_file->add_error($error, $stack_ptr, 'NotRequired', $data);
        }
    }
    //end process()
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
    protected function get_included_class_from_token($phpcs_file, array $tokens, $stack_ptr)
    {
        return false;
    }
    //end getIncludedClassFromToken()
}
//end class