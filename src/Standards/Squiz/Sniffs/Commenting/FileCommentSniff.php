<?php

declare (strict_types=1);
/**
 * Parses and verifies the file doc comment.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\Commenting;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class File_Comment_Sniff implements Sniff
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
        return [T_OPEN_TAG];
    }
    //end register()
    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token
     *                                               in the stack passed in $tokens.
     *
     * @return int
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        $comment_start = $phpcs_file->find_next(T_WHITESPACE, $stack_ptr + 1, null, true);
        if ($tokens[$comment_start]['code'] === T_COMMENT) {
            $phpcs_file->add_error('You must use "/**" style comments for a file comment', $comment_start, 'WrongStyle');
            $phpcs_file->record_metric($stack_ptr, 'File has doc comment', 'yes');
            return $phpcs_file->num_tokens + 1;
        }
        if ($comment_start === false || $tokens[$comment_start]['code'] !== T_DOC_COMMENT_OPEN_TAG) {
            $phpcs_file->add_error('Missing file doc comment', $stack_ptr, 'Missing');
            $phpcs_file->record_metric($stack_ptr, 'File has doc comment', 'no');
            return $phpcs_file->num_tokens + 1;
        }
        if (isset($tokens[$comment_start]['comment_closer']) === false || $tokens[$tokens[$comment_start]['comment_closer']]['content'] === '' && $tokens[$comment_start]['comment_closer'] === $phpcs_file->num_tokens - 1) {
            // Don't process an unfinished file comment during live coding.
            return $phpcs_file->num_tokens + 1;
        }
        $comment_end = $tokens[$comment_start]['comment_closer'];
        for ($next_token = $comment_end + 1; $next_token < $phpcs_file->num_tokens; $next_token++) {
            if ($tokens[$next_token]['code'] === T_WHITESPACE) {
                continue;
            }
            if ($tokens[$next_token]['code'] === T_ATTRIBUTE && isset($tokens[$next_token]['attribute_closer']) === true) {
                $next_token = $tokens[$next_token]['attribute_closer'];
                continue;
            }
            break;
        }
        if ($next_token === $phpcs_file->num_tokens) {
            $next_token--;
        }
        $ignore = [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM, T_FUNCTION, T_CLOSURE, T_PUBLIC, T_PRIVATE, T_PROTECTED, T_FINAL, T_STATIC, T_ABSTRACT, T_READONLY, T_CONST, T_PROPERTY, T_INCLUDE, T_INCLUDE_ONCE, T_REQUIRE, T_REQUIRE_ONCE];
        if (in_array($tokens[$next_token]['code'], $ignore, true) === true) {
            $phpcs_file->add_error('Missing file doc comment', $stack_ptr, 'Missing');
            $phpcs_file->record_metric($stack_ptr, 'File has doc comment', 'no');
            return $phpcs_file->num_tokens + 1;
        }
        $phpcs_file->record_metric($stack_ptr, 'File has doc comment', 'yes');
        // No blank line between the open tag and the file comment.
        if ($tokens[$comment_start]['line'] > $tokens[$stack_ptr]['line'] + 1) {
            $error = 'There must be no blank lines before the file comment';
            $phpcs_file->add_error($error, $stack_ptr, 'SpacingAfterOpen');
        }
        // Exactly one blank line after the file comment.
        $next = $phpcs_file->find_next(T_WHITESPACE, $comment_end + 1, null, true);
        if ($next !== false && $tokens[$next]['line'] !== $tokens[$comment_end]['line'] + 2) {
            $error = 'There must be exactly one blank line after the file comment';
            $phpcs_file->add_error($error, $comment_end, 'SpacingAfterComment');
        }
        // Required tags in correct order.
        $required = ['@package' => true, '@subpackage' => true, '@author' => true, '@copyright' => true];
        $found_tags = [];
        foreach ($tokens[$comment_start]['comment_tags'] as $tag) {
            $name = $tokens[$tag]['content'];
            $is_required = isset($required[$name]);
            if ($is_required === true && in_array($name, $found_tags, true) === true) {
                $error = 'Only one %s tag is allowed in a file comment';
                $data = [$name];
                $phpcs_file->add_error($error, $tag, 'Duplicate' . ucfirst(substr($name, 1)) . 'Tag', $data);
            }
            $found_tags[] = $name;
            if ($is_required === false) {
                continue;
            }
            $string = $phpcs_file->find_next(T_DOC_COMMENT_STRING, $tag, $comment_end);
            if ($string === false || $tokens[$string]['line'] !== $tokens[$tag]['line']) {
                $error = 'Content missing for %s tag in file comment';
                $data = [$name];
                $phpcs_file->add_error($error, $tag, 'Empty' . ucfirst(substr($name, 1)) . 'Tag', $data);
                continue;
            }
            if ($name === '@author') {
                if ($tokens[$string]['content'] !== 'Squiz Pty Ltd <products@squiz.net>') {
                    $error = 'Expected "Squiz Pty Ltd <products@squiz.net>" for author tag';
                    $fix = $phpcs_file->add_fixable_error($error, $tag, 'IncorrectAuthor');
                    if ($fix === true) {
                        $expected = 'Squiz Pty Ltd <products@squiz.net>';
                        $phpcs_file->fixer->replace_token($string, $expected);
                    }
                }
            } elseif ($name === '@copyright') {
                if (preg_match('/^([0-9]{4})(-[0-9]{4})? (Squiz Pty Ltd \(ABN 77 084 670 600\))$/', $tokens[$string]['content']) === 0) {
                    $error = 'Expected "xxxx-xxxx Squiz Pty Ltd (ABN 77 084 670 600)" for copyright declaration';
                    $fix = $phpcs_file->add_fixable_error($error, $tag, 'IncorrectCopyright');
                    if ($fix === true) {
                        $matches = [];
                        preg_match('/^(([0-9]{4})(-[0-9]{4})?)?.*$/', $tokens[$string]['content'], $matches);
                        if (isset($matches[1]) === false) {
                            $matches[1] = date('Y');
                        }
                        $expected = $matches[1] . ' Squiz Pty Ltd (ABN 77 084 670 600)';
                        $phpcs_file->fixer->replace_token($string, $expected);
                    }
                }
            }
            //end if
        }
        //end foreach
        // Check if the tags are in the correct position.
        $pos = 0;
        foreach ($required as $tag => $true) {
            if (in_array($tag, $found_tags, true) === false) {
                $error = 'Missing %s tag in file comment';
                $data = [$tag];
                $phpcs_file->add_error($error, $comment_end, 'Missing' . ucfirst(substr($tag, 1)) . 'Tag', $data);
            }
            if (isset($found_tags[$pos]) === false) {
                break;
            }
            if ($found_tags[$pos] !== $tag) {
                $error = 'The tag in position %s should be the %s tag';
                $data = [$pos + 1, $tag];
                $phpcs_file->add_error($error, $tokens[$comment_start]['comment_tags'][$pos], ucfirst(substr($tag, 1)) . 'TagOrder', $data);
            }
            $pos++;
        }
        //end foreach
        // Ignore the rest of the file.
        return $phpcs_file->num_tokens + 1;
    }
    //end process()
}
//end class