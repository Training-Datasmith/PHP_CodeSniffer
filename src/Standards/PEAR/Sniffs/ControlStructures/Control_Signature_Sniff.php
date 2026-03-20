<?php

declare (strict_types=1);
/**
 * Verifies that control statements conform to their coding standards.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\PEAR\Sniffs\Control_Structures;

use Php_code_Sniffer\Sniffs\Abstract_Pattern_Sniff;
class Control_Signature_Sniff extends Abstract_Pattern_Sniff
{
    /**
     * If true, comments will be ignored if they are found in the code.
     *
     * @var boolean
     */
    public $ignore_comments = true;
    /**
     * Returns the patterns that this test wishes to verify.
     *
     * @return string[]
     */
    protected function get_patterns()
    {
        return ['do {EOL...} while (...);EOL', 'while (...) {EOL', 'for (...) {EOL', 'if (...) {EOL', 'foreach (...) {EOL', '} else if (...) {EOL', '} elseif (...) {EOL', '} else {EOL', 'do {EOL', 'match (...) {EOL'];
    }
    //end getPatterns()
}
//end class