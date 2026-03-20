<?php

declare (strict_types=1);
/**
 * Checks that the method declaration is correct.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\PSR2\Sniffs\Methods;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Abstract_Scope_Sniff;
use Php_code_Sniffer\Util\Tokens;
class Method_Declaration_Sniff extends Abstract_Scope_Sniff
{
    /**
     * Constructs a Squiz_Sniffs_Scope_MethodScopeSniff.
     */
    public function __construct()
    {
        parent::__construct(Tokens::$oo_scope_tokens, [T_FUNCTION]);
    }
    //end __construct()
    /**
     * Processes the function tokens within the class.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file where this token was found.
     * @param int                         $stackPtr  The position where the token was found.
     * @param int                         $currScope The current scope opener token.
     *
     * @return void
     */
    protected function process_token_within_scope(File $phpcs_file, $stack_ptr, $curr_scope)
    {
        $tokens = $phpcs_file->get_tokens();
        // Determine if this is a function which needs to be examined.
        $conditions = $tokens[$stack_ptr]['conditions'];
        end($conditions);
        $deepest_scope = key($conditions);
        if ($deepest_scope !== $curr_scope) {
            return;
        }
        $method_name = $phpcs_file->get_declaration_name($stack_ptr);
        if ($method_name === null) {
            // Ignore closures.
            return;
        }
        if ($method_name[0] === '_' && isset($method_name[1]) === true && $method_name[1] !== '_') {
            $error = 'Method name "%s" should not be prefixed with an underscore to indicate visibility';
            $data = [$method_name];
            $phpcs_file->add_warning($error, $stack_ptr, 'Underscore', $data);
        }
        $visibility = 0;
        $static = 0;
        $abstract = 0;
        $final = 0;
        $find = Tokens::$method_prefixes + Tokens::$empty_tokens;
        $prev = $phpcs_file->find_previous($find, $stack_ptr - 1, null, true);
        $prefix = $stack_ptr;
        while (($prefix = $phpcs_file->find_previous(Tokens::$method_prefixes, $prefix - 1, $prev)) !== false) {
            switch ($tokens[$prefix]['code']) {
                case T_STATIC:
                    $static = $prefix;
                    break;
                case T_ABSTRACT:
                    $abstract = $prefix;
                    break;
                case T_FINAL:
                    $final = $prefix;
                    break;
                default:
                    $visibility = $prefix;
                    break;
            }
        }
        $fixes = [];
        if ($visibility !== 0 && $final > $visibility) {
            $error = 'The final declaration must precede the visibility declaration';
            $fix = $phpcs_file->add_fixable_error($error, $final, 'FinalAfterVisibility');
            if ($fix === true) {
                $fixes[$final] = '';
                $fixes[$final + 1] = '';
                if (isset($fixes[$visibility]) === true) {
                    $fixes[$visibility] = 'final ' . $fixes[$visibility];
                } else {
                    $fixes[$visibility] = 'final ' . $tokens[$visibility]['content'];
                }
            }
        }
        if ($visibility !== 0 && $abstract > $visibility) {
            $error = 'The abstract declaration must precede the visibility declaration';
            $fix = $phpcs_file->add_fixable_error($error, $abstract, 'AbstractAfterVisibility');
            if ($fix === true) {
                $fixes[$abstract] = '';
                $fixes[$abstract + 1] = '';
                if (isset($fixes[$visibility]) === true) {
                    $fixes[$visibility] = 'abstract ' . $fixes[$visibility];
                } else {
                    $fixes[$visibility] = 'abstract ' . $tokens[$visibility]['content'];
                }
            }
        }
        if ($static !== 0 && $static < $visibility) {
            $error = 'The static declaration must come after the visibility declaration';
            $fix = $phpcs_file->add_fixable_error($error, $static, 'StaticBeforeVisibility');
            if ($fix === true) {
                $fixes[$static] = '';
                $fixes[$static + 1] = '';
                if (isset($fixes[$visibility]) === true) {
                    $fixes[$visibility] .= ' static';
                } else {
                    $fixes[$visibility] = $tokens[$visibility]['content'] . ' static';
                }
            }
        }
        // Batch all the fixes together to reduce the possibility of conflicts.
        if (empty($fixes) === false) {
            $phpcs_file->fixer->begin_changeset();
            foreach ($fixes as $stack_ptr => $content) {
                $phpcs_file->fixer->replace_token($stack_ptr, $content);
            }
            $phpcs_file->fixer->end_changeset();
        }
    }
    //end processTokenWithinScope()
    /**
     * Processes a token that is found within the scope that this test is
     * listening to.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file where this token was found.
     * @param int                         $stackPtr  The position in the stack where this
     *                                               token was found.
     *
     * @return void
     */
    protected function process_token_outside_scope(File $phpcs_file, $stack_ptr)
    {
    }
    //end processTokenOutsideScope()
}
//end class