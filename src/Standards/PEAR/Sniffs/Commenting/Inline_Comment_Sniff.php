<?php

declare (strict_types=1);
/**
 * Checks that no Perl-style comments are used.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\PEAR\Sniffs\Commenting;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Inline_Comment_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_COMMENT];
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
        if ($tokens[$stack_ptr]['content'][0] === '#') {
            $phpcs_file->record_metric($stack_ptr, 'Inline comment style', '# ...');
            $error = 'Perl-style comments are not allowed. Use "// Comment."';
            $error .= ' or "/* comment */" instead.';
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'WrongStyle');
            if ($fix === true) {
                $new_comment = ltrim($tokens[$stack_ptr]['content'], '# ');
                $new_comment = '// ' . $new_comment;
                $phpcs_file->fixer->replace_token($stack_ptr, $new_comment);
            }
        } elseif ($tokens[$stack_ptr]['content'][0] === '/' && $tokens[$stack_ptr]['content'][1] === '/') {
            $phpcs_file->record_metric($stack_ptr, 'Inline comment style', '// ...');
        } elseif ($tokens[$stack_ptr]['content'][0] === '/' && $tokens[$stack_ptr]['content'][1] === '*') {
            $phpcs_file->record_metric($stack_ptr, 'Inline comment style', '/* ... */');
        }
    }
    //end process()
}
//end class