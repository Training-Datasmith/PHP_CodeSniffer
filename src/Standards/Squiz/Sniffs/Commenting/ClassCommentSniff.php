<?php

declare (strict_types=1);
/**
 * Parses and verifies the class doc comment.
 *
 * Verifies that :
 * <ul>
 *  <li>A class doc comment exists.</li>
 *  <li>The comment uses the correct docblock style.</li>
 *  <li>There are no blank lines after the class comment.</li>
 *  <li>No tags are used in the docblock.</li>
 * </ul>
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\Commenting;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Class_Comment_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_CLASS];
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
        $find = [T_ABSTRACT => T_ABSTRACT, T_FINAL => T_FINAL, T_READONLY => T_READONLY, T_WHITESPACE => T_WHITESPACE];
        $previous_content = null;
        for ($comment_end = $stack_ptr - 1; $comment_end >= 0; $comment_end--) {
            if (isset($find[$tokens[$comment_end]['code']]) === true) {
                continue;
            }
            if ($previous_content === null) {
                $previous_content = $comment_end;
            }
            if ($tokens[$comment_end]['code'] === T_ATTRIBUTE_END && isset($tokens[$comment_end]['attribute_opener']) === true) {
                $comment_end = $tokens[$comment_end]['attribute_opener'];
                continue;
            }
            break;
        }
        if ($tokens[$comment_end]['code'] !== T_DOC_COMMENT_CLOSE_TAG && $tokens[$comment_end]['code'] !== T_COMMENT) {
            $class = $phpcs_file->get_declaration_name($stack_ptr);
            $phpcs_file->add_error('Missing doc comment for class %s', $stack_ptr, 'Missing', [$class]);
            $phpcs_file->record_metric($stack_ptr, 'Class has doc comment', 'no');
            return;
        }
        $phpcs_file->record_metric($stack_ptr, 'Class has doc comment', 'yes');
        if ($tokens[$comment_end]['code'] === T_COMMENT) {
            $phpcs_file->add_error('You must use "/**" style comments for a class comment', $stack_ptr, 'WrongStyle');
            return;
        }
        if ($tokens[$previous_content]['line'] !== $tokens[$stack_ptr]['line'] - 1) {
            $error = 'There must be no blank lines after the class comment';
            $phpcs_file->add_error($error, $comment_end, 'SpacingAfter');
        }
        $comment_start = $tokens[$comment_end]['comment_opener'];
        foreach ($tokens[$comment_start]['comment_tags'] as $tag) {
            $error = '%s tag is not allowed in class comment';
            $data = [$tokens[$tag]['content']];
            $phpcs_file->add_warning($error, $tag, 'TagNotAllowed', $data);
        }
    }
    //end process()
}
//end class