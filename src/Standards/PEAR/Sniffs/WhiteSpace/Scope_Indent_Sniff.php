<?php

declare (strict_types=1);
/**
 * Checks that control structures are structured and indented correctly.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\PEAR\Sniffs\White_Space;

use Php_code_Sniffer\Standards\Generic\Sniffs\White_Space\Scope_Indent_Sniff as GenericScopeIndentSniff;
class Scope_Indent_Sniff extends Generic_Scope_Indent_Sniff
{
    /**
     * Any scope openers that should not cause an indent.
     *
     * @var int[]
     */
    protected $non_indenting_scopes = [T_SWITCH];
}
//end class