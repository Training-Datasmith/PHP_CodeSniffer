<?php

declare (strict_types=1);
/**
 * Parses and verifies the variable doc comment.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\Commenting;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Abstract_Variable_Sniff;
use Php_code_Sniffer\Util\Common;
class Variable_Comment_Sniff extends Abstract_Variable_Sniff
{
    /**
     * Called to process class member vars.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token
     *                                               in the stack passed in $tokens.
     *
     * @return void
     */
    public function process_member_var(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        $ignore = [T_PUBLIC => T_PUBLIC, T_PRIVATE => T_PRIVATE, T_PROTECTED => T_PROTECTED, T_VAR => T_VAR, T_STATIC => T_STATIC, T_READONLY => T_READONLY, T_WHITESPACE => T_WHITESPACE, T_STRING => T_STRING, T_NS_SEPARATOR => T_NS_SEPARATOR, T_NULLABLE => T_NULLABLE];
        for ($comment_end = $stack_ptr - 1; $comment_end >= 0; $comment_end--) {
            if (isset($ignore[$tokens[$comment_end]['code']]) === true) {
                continue;
            }
            if ($tokens[$comment_end]['code'] === T_ATTRIBUTE_END && isset($tokens[$comment_end]['attribute_opener']) === true) {
                $comment_end = $tokens[$comment_end]['attribute_opener'];
                continue;
            }
            break;
        }
        if ($comment_end === false || $tokens[$comment_end]['code'] !== T_DOC_COMMENT_CLOSE_TAG && $tokens[$comment_end]['code'] !== T_COMMENT) {
            $phpcs_file->add_error('Missing member variable doc comment', $stack_ptr, 'Missing');
            return;
        }
        if ($tokens[$comment_end]['code'] === T_COMMENT) {
            $phpcs_file->add_error('You must use "/**" style comments for a member variable comment', $stack_ptr, 'WrongStyle');
            return;
        }
        $comment_start = $tokens[$comment_end]['comment_opener'];
        $found_var = null;
        foreach ($tokens[$comment_start]['comment_tags'] as $tag) {
            if ($tokens[$tag]['content'] === '@var') {
                if ($found_var !== null) {
                    $error = 'Only one @var tag is allowed in a member variable comment';
                    $phpcs_file->add_error($error, $tag, 'DuplicateVar');
                } else {
                    $found_var = $tag;
                }
            } elseif ($tokens[$tag]['content'] === '@see') {
                // Make sure the tag isn't empty.
                $string = $phpcs_file->find_next(T_DOC_COMMENT_STRING, $tag, $comment_end);
                if ($string === false || $tokens[$string]['line'] !== $tokens[$tag]['line']) {
                    $error = 'Content missing for @see tag in member variable comment';
                    $phpcs_file->add_error($error, $tag, 'EmptySees');
                }
            } else {
                $error = '%s tag is not allowed in member variable comment';
                $data = [$tokens[$tag]['content']];
                $phpcs_file->add_warning($error, $tag, 'TagNotAllowed', $data);
            }
            //end if
        }
        //end foreach
        // The @var tag is the only one we require.
        if ($found_var === null) {
            $error = 'Missing @var tag in member variable comment';
            $phpcs_file->add_error($error, $comment_end, 'MissingVar');
            return;
        }
        $first_tag = $tokens[$comment_start]['comment_tags'][0];
        if ($found_var !== null && $tokens[$first_tag]['content'] !== '@var') {
            $error = 'The @var tag must be the first tag in a member variable comment';
            $phpcs_file->add_error($error, $found_var, 'VarOrder');
        }
        // Make sure the tag isn't empty and has the correct padding.
        $string = $phpcs_file->find_next(T_DOC_COMMENT_STRING, $found_var, $comment_end);
        if ($string === false || $tokens[$string]['line'] !== $tokens[$found_var]['line']) {
            $error = 'Content missing for @var tag in member variable comment';
            $phpcs_file->add_error($error, $found_var, 'EmptyVar');
            return;
        }
        // Support both a var type and a description.
        preg_match('`^((?:\|?(?:array\([^\)]*\)|[\\\\a-z0-9\[\]]+))*)( .*)?`i', $tokens[$found_var + 2]['content'], $var_parts);
        if (isset($var_parts[1]) === false) {
            return;
        }
        $var_type = $var_parts[1];
        // Check var type (can be multiple, separated by '|').
        $type_names = explode('|', $var_type);
        $suggested_names = [];
        foreach ($type_names as $type_name) {
            $suggested_name = Common::suggest_type($type_name);
            if (in_array($suggested_name, $suggested_names, true) === false) {
                $suggested_names[] = $suggested_name;
            }
        }
        $suggested_type = implode('|', $suggested_names);
        if ($var_type !== $suggested_type) {
            $error = 'Expected "%s" but found "%s" for @var tag in member variable comment';
            $data = [$suggested_type, $var_type];
            $fix = $phpcs_file->add_fixable_error($error, $found_var, 'IncorrectVarType', $data);
            if ($fix === true) {
                $replacement = $suggested_type;
                if (empty($var_parts[2]) === false) {
                    $replacement .= $var_parts[2];
                }
                $phpcs_file->fixer->replace_token($found_var + 2, $replacement);
                unset($replacement);
            }
        }
    }
    //end processMemberVar()
    /**
     * Called to process a normal variable.
     *
     * Not required for this sniff.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The PHP_CodeSniffer file where this token was found.
     * @param int                         $stackPtr  The position where the double quoted
     *                                               string was found.
     *
     * @return void
     */
    protected function process_variable(File $phpcs_file, $stack_ptr)
    {
    }
    //end processVariable()
    /**
     * Called to process variables found in double quoted strings.
     *
     * Not required for this sniff.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The PHP_CodeSniffer file where this token was found.
     * @param int                         $stackPtr  The position where the double quoted
     *                                               string was found.
     *
     * @return void
     */
    protected function process_variable_in_string(File $phpcs_file, $stack_ptr)
    {
    }
    //end processVariableInString()
}
//end class