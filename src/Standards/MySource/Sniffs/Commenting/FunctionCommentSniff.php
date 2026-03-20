<?php

declare (strict_types=1);
/**
 * Parses and verifies the doc comments for functions.
 *
 * Same as the Squiz standard, but adds support for API tags.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\My_Source\Sniffs\Commenting;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Standards\Squiz\Sniffs\Commenting\Function_Comment_Sniff as SquizFunctionCommentSniff;
use Php_code_Sniffer\Util\Tokens;
class Function_Comment_Sniff extends Squiz_Function_Comment_Sniff
{
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
        parent::process($phpcs_file, $stack_ptr);
        $tokens = $phpcs_file->get_tokens();
        $find = Tokens::$method_prefixes;
        $find[] = T_WHITESPACE;
        $comment_end = $phpcs_file->find_previous($find, $stack_ptr - 1, null, true);
        if ($tokens[$comment_end]['code'] !== T_DOC_COMMENT_CLOSE_TAG) {
            return;
        }
        $comment_start = $tokens[$comment_end]['comment_opener'];
        $has_api_tag = false;
        foreach ($tokens[$comment_start]['comment_tags'] as $tag) {
            if ($tokens[$tag]['content'] === '@api') {
                if ($has_api_tag === true) {
                    // We've come across an API tag already, which means
                    // we were not the first tag in the API list.
                    $error = 'The @api tag must come first in the @api tag list in a function comment';
                    $phpcs_file->add_error($error, $tag, 'ApiNotFirst');
                }
                $has_api_tag = true;
                // There needs to be a blank line before the @api tag.
                $prev = $phpcs_file->find_previous([T_DOC_COMMENT_STRING, T_DOC_COMMENT_TAG], $tag - 1);
                if ($tokens[$prev]['line'] !== $tokens[$tag]['line'] - 2) {
                    $error = 'There must be one blank line before the @api tag in a function comment';
                    $phpcs_file->add_error($error, $tag, 'ApiSpacing');
                }
            } elseif (substr($tokens[$tag]['content'], 0, 5) === '@api-') {
                $has_api_tag = true;
                $prev = $phpcs_file->find_previous([T_DOC_COMMENT_STRING, T_DOC_COMMENT_TAG], $tag - 1);
                if ($tokens[$prev]['line'] !== $tokens[$tag]['line'] - 1) {
                    $error = 'There must be no blank line before the @%s tag in a function comment';
                    $data = [$tokens[$tag]['content']];
                    $phpcs_file->add_error($error, $tag, 'ApiTagSpacing', $data);
                }
            }
            //end if
        }
        //end foreach
        if ($has_api_tag === true && substr($tokens[$tag]['content'], 0, 4) !== '@api') {
            // API tags must be the last tags in a function comment.
            $error = 'The @api tags must be the last tags in a function comment';
            $phpcs_file->add_error($error, $comment_end, 'ApiNotLast');
        }
    }
    //end process()
}
//end class