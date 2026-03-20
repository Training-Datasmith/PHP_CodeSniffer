<?php

declare (strict_types=1);
/**
 * Discourages the use of debug functions.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\PHP;

use Php_code_Sniffer\Standards\Generic\Sniffs\PHP\Forbidden_Functions_Sniff as GenericForbiddenFunctionsSniff;
class Discouraged_Functions_Sniff extends Generic_Forbidden_Functions_Sniff
{
    /**
     * A list of forbidden functions with their alternatives.
     *
     * The value is NULL if no alternative exists. IE, the
     * function should just not be used.
     *
     * @var array<string, string|null>
     */
    public $forbidden_functions = ['error_log' => null, 'print_r' => null, 'var_dump' => null];
    /**
     * If true, an error will be thrown; otherwise a warning.
     *
     * @var boolean
     */
    public $error = false;
}
//end class