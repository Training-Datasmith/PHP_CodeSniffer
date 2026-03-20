<?php

declare (strict_types=1);
/**
 * Checks the function declaration is correct.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\Functions;

use Php_code_Sniffer\Sniffs\Abstract_Pattern_Sniff;
class Function_Declaration_Sniff extends Abstract_Pattern_Sniff
{
    /**
     * Returns an array of patterns to check are correct.
     *
     * @return array
     */
    protected function get_patterns()
    {
        return ['function abc(...);', 'function abc(...)', 'abstract function abc(...);'];
    }
    //end getPatterns()
}
//end class