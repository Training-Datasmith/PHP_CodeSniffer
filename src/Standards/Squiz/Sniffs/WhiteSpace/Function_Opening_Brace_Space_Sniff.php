<?php

declare (strict_types=1);
/**
 * Checks that there is no empty line after the opening brace of a function.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\White_Space;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Function_Opening_Brace_Space_Sniff implements Sniff
{
    /**
     * A list of tokenizers this sniff supports.
     *
     * @var array
     */
    public $supported_tokenizers = ['PHP', 'JS'];
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_FUNCTION, T_CLOSURE];
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
        if (isset($tokens[$stack_ptr]['scope_opener']) === false) {
            // Probably an interface or abstract method.
            return;
        }
        $open_brace = $tokens[$stack_ptr]['scope_opener'];
        $next_content = $phpcs_file->find_next(T_WHITESPACE, $open_brace + 1, null, true);
        if ($next_content === $tokens[$stack_ptr]['scope_closer']) {
            // The next bit of content is the closing brace, so this
            // is an empty function and should have a blank line
            // between the opening and closing braces.
            return;
        }
        $brace_line = $tokens[$open_brace]['line'];
        $next_line = $tokens[$next_content]['line'];
        $found = $next_line - $brace_line - 1;
        if ($found > 0) {
            $error = 'Expected 0 blank lines after opening function brace; %s found';
            $data = [$found];
            $fix = $phpcs_file->add_fixable_error($error, $open_brace, 'SpacingAfter', $data);
            if ($fix === true) {
                $phpcs_file->fixer->begin_changeset();
                for ($i = $open_brace + 1; $i < $next_content; $i++) {
                    if ($tokens[$i]['line'] === $next_line) {
                        break;
                    }
                    $phpcs_file->fixer->replace_token($i, '');
                }
                $phpcs_file->fixer->add_newline($open_brace);
                $phpcs_file->fixer->end_changeset();
            }
        }
    }
    //end process()
}
//end class