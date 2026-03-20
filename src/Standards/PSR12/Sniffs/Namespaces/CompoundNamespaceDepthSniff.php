<?php

declare (strict_types=1);
/**
 * Verifies that compound namespaces are not defined too deep.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\PSR12\Sniffs\Namespaces;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Compound_Namespace_Depth_Sniff implements Sniff
{
    /**
     * The max depth for compound namespaces.
     *
     * @var integer
     */
    public $max_depth = 2;
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_OPEN_USE_GROUP];
    }
    //end register()
    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token in the
     *                                               stack passed in $tokens.
     *
     * @return void
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $this->max_depth = (int) $this->max_depth;
        $tokens = $phpcs_file->get_tokens();
        $end = $phpcs_file->find_next(T_CLOSE_USE_GROUP, $stack_ptr + 1);
        if ($end === false) {
            return;
        }
        $depth = 1;
        for ($i = $stack_ptr + 1; $i <= $end; $i++) {
            if ($tokens[$i]['code'] === T_NS_SEPARATOR) {
                $depth++;
                continue;
            }
            if ($i === $end || $tokens[$i]['code'] === T_COMMA) {
                // End of a namespace.
                if ($depth > $this->max_depth) {
                    $error = 'Compound namespaces cannot have a depth more than %s';
                    $data = [$this->max_depth];
                    $phpcs_file->add_error($error, $i, 'TooDeep', $data);
                }
                $depth = 1;
            }
        }
    }
    //end process()
}
//end class