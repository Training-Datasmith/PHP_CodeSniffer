<?php

declare (strict_types=1);
/**
 * Checks to ensure that there are no comments after statements.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\Commenting;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Post_Statement_Comment_Sniff implements Sniff
{
    /**
     * A list of tokenizers this sniff supports.
     *
     * @var array
     */
    public $supported_tokenizers = ['PHP', 'JS'];
    /**
     * Exceptions to the rule.
     *
     * If post statement comments are found within the condition
     * parenthesis of these structures, leave them alone.
     *
     * @var array
     */
    private $control_structure_exceptions = [T_IF => true, T_ELSEIF => true, T_SWITCH => true, T_WHILE => true, T_FOR => true, T_FOREACH => true, T_MATCH => true];
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
     * Processes this sniff, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token in the
     *                                               stack passed in $tokens.
     *
     * @return void
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        if (substr($tokens[$stack_ptr]['content'], 0, 2) !== '//') {
            return;
        }
        $comment_line = $tokens[$stack_ptr]['line'];
        $last_content = $phpcs_file->find_previous(T_WHITESPACE, $stack_ptr - 1, null, true);
        if ($last_content === false || $tokens[$last_content]['line'] !== $comment_line || $tokens[$stack_ptr]['column'] === 1) {
            return;
        }
        if ($tokens[$last_content]['code'] === T_CLOSE_CURLY_BRACKET) {
            return;
        }
        // Special case for JS files and PHP closures.
        if ($tokens[$last_content]['code'] === T_COMMA || $tokens[$last_content]['code'] === T_SEMICOLON) {
            $last_content = $phpcs_file->find_previous(T_WHITESPACE, $last_content - 1, null, true);
            if ($last_content === false || $tokens[$last_content]['code'] === T_CLOSE_CURLY_BRACKET) {
                return;
            }
        }
        // Special case for (trailing) comments within multi-line control structures.
        if (isset($tokens[$stack_ptr]['nested_parenthesis']) === true) {
            $nested_parens = $tokens[$stack_ptr]['nested_parenthesis'];
            foreach ($nested_parens as $open => $close) {
                if (isset($tokens[$open]['parenthesis_owner']) === true && isset($this->control_structure_exceptions[$tokens[$tokens[$open]['parenthesis_owner']]['code']]) === true) {
                    return;
                }
            }
        }
        $error = 'Comments may not appear after statements';
        $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'Found');
        if ($fix === true) {
            $phpcs_file->fixer->add_newline_before($stack_ptr);
        }
    }
    //end process()
}
//end class