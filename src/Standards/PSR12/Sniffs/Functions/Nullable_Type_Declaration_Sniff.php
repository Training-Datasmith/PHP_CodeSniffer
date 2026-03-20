<?php

declare (strict_types=1);
/**
 * Verifies that nullable typehints are lacking superfluous whitespace, e.g. ?int
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2018 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\PSR12\Sniffs\Functions;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Nullable_Type_Declaration_Sniff implements Sniff
{
    /**
     * An array of valid tokens after `T_NULLABLE` occurrences.
     *
     * @var array
     */
    private $valid_tokens = [T_STRING => true, T_NS_SEPARATOR => true, T_CALLABLE => true, T_SELF => true, T_PARENT => true, T_STATIC => true];
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_NULLABLE];
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
        $next_non_empty_ptr = $phpcs_file->find_next([T_WHITESPACE], $stack_ptr + 1, null, true);
        if ($next_non_empty_ptr === false) {
            // Parse error or live coding.
            return;
        }
        $tokens = $phpcs_file->get_tokens();
        $next_non_empty_code = $tokens[$next_non_empty_ptr]['code'];
        $valid_token_found = isset($this->valid_tokens[$next_non_empty_code]);
        if ($valid_token_found === true && $next_non_empty_ptr === $stack_ptr + 1) {
            // Valid structure.
            return;
        }
        $error = 'There must not be a space between the question mark and the type in nullable type declarations';
        if ($valid_token_found === true) {
            // No other tokens then whitespace tokens found; fixable.
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'WhitespaceFound');
            if ($fix === true) {
                for ($i = $stack_ptr + 1; $i < $next_non_empty_ptr; $i++) {
                    $phpcs_file->fixer->replace_token($i, '');
                }
            }
            return;
        }
        // Non-whitespace tokens found; trigger error but don't fix.
        $phpcs_file->add_error($error, $stack_ptr, 'UnexpectedCharactersFound');
    }
    //end process()
}
//end class