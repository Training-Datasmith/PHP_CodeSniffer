<?php

declare (strict_types=1);
/**
 * Parses and verifies the doc comments for classes.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\PEAR\Sniffs\Commenting;

use Php_code_Sniffer\Files\File;
class Class_Comment_Sniff extends File_Comment_Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM];
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
        $type = strtolower($tokens[$stack_ptr]['content']);
        $error_data = [$type];
        $find = [T_ABSTRACT => T_ABSTRACT, T_FINAL => T_FINAL, T_READONLY => T_READONLY, T_WHITESPACE => T_WHITESPACE];
        for ($comment_end = $stack_ptr - 1; $comment_end >= 0; $comment_end--) {
            if (isset($find[$tokens[$comment_end]['code']]) === true) {
                continue;
            }
            if ($tokens[$comment_end]['code'] === T_ATTRIBUTE_END && isset($tokens[$comment_end]['attribute_opener']) === true) {
                $comment_end = $tokens[$comment_end]['attribute_opener'];
                continue;
            }
            break;
        }
        if ($tokens[$comment_end]['code'] !== T_DOC_COMMENT_CLOSE_TAG && $tokens[$comment_end]['code'] !== T_COMMENT) {
            $error_data[] = $phpcs_file->get_declaration_name($stack_ptr);
            $phpcs_file->add_error('Missing doc comment for %s %s', $stack_ptr, 'Missing', $error_data);
            $phpcs_file->record_metric($stack_ptr, 'Class has doc comment', 'no');
            return;
        }
        $phpcs_file->record_metric($stack_ptr, 'Class has doc comment', 'yes');
        if ($tokens[$comment_end]['code'] === T_COMMENT) {
            $phpcs_file->add_error('You must use "/**" style comments for a %s comment', $stack_ptr, 'WrongStyle', $error_data);
            return;
        }
        // Check each tag.
        $this->process_tags($phpcs_file, $stack_ptr, $tokens[$comment_end]['comment_opener']);
    }
    //end process()
    /**
     * Process the version tag.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param array                       $tags      The tokens for these tags.
     *
     * @return void
     */
    protected function process_version($phpcs_file, array $tags)
    {
        $tokens = $phpcs_file->get_tokens();
        foreach ($tags as $tag) {
            if ($tokens[$tag + 2]['code'] !== T_DOC_COMMENT_STRING) {
                // No content.
                continue;
            }
            $content = $tokens[$tag + 2]['content'];
            if (strstr($content, 'Release:') === false) {
                $error = 'Invalid version "%s" in doc comment; consider "Release: <package_version>" instead';
                $data = [$content];
                $phpcs_file->add_warning($error, $tag, 'InvalidVersion', $data);
            }
        }
    }
    //end processVersion()
}
//end class