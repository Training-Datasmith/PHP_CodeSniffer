<?php

declare (strict_types=1);
/**
 * Ensures that values submitted via JS are not compared to NULL.
 *
 * With jQuery 1.8, the behavior of ajax requests changed so that null values are
 * submitted as null= instead of null=null.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\My_Source\Sniffs\PHP;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Ajax_Null_Comparison_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_FUNCTION];
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
        $tokens = $phpcs_file->get_tokens();
        // Make sure it is an API function. We know this by the doc comment.
        $comment_end = $phpcs_file->find_previous(T_DOC_COMMENT_CLOSE_TAG, $stack_ptr);
        $comment_start = $phpcs_file->find_previous(T_DOC_COMMENT_OPEN_TAG, $comment_end - 1);
        // If function doesn't contain any doc comments - skip it.
        if ($comment_end === false || $comment_start === false) {
            return;
        }
        $comment = $phpcs_file->get_tokens_as_string($comment_start, $comment_end - $comment_start);
        if (strpos($comment, '* @api') === false) {
            return;
        }
        // Find all the vars passed in as we are only interested in comparisons
        // to NULL for these specific variables.
        $found_vars = [];
        $open = $tokens[$stack_ptr]['parenthesis_opener'];
        $close = $tokens[$stack_ptr]['parenthesis_closer'];
        for ($i = $open + 1; $i < $close; $i++) {
            if ($tokens[$i]['code'] === T_VARIABLE) {
                $found_vars[$tokens[$i]['content']] = true;
            }
        }
        if (empty($found_vars) === true) {
            return;
        }
        $start = $tokens[$stack_ptr]['scope_opener'];
        $end = $tokens[$stack_ptr]['scope_closer'];
        for ($i = $start + 1; $i < $end; $i++) {
            if ($tokens[$i]['code'] !== T_VARIABLE) {
                continue;
            }
            if (isset($found_vars[$tokens[$i]['content']]) === false) {
                continue;
            }
            $operator = $phpcs_file->find_next(T_WHITESPACE, $i + 1, null, true);
            if ($tokens[$operator]['code'] !== T_IS_IDENTICAL && $tokens[$operator]['code'] !== T_IS_NOT_IDENTICAL) {
                continue;
            }
            $null_value = $phpcs_file->find_next(T_WHITESPACE, $operator + 1, null, true);
            if ($tokens[$null_value]['code'] !== T_NULL) {
                continue;
            }
            $error = 'Values submitted via Ajax requests should not be compared directly to NULL; use empty() instead';
            $phpcs_file->add_warning($error, $null_value, 'Found');
        }
        //end for
    }
    //end process()
}
//end class