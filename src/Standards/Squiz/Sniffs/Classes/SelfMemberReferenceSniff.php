<?php

declare (strict_types=1);
/**
 * Tests self member references.
 *
 * Verifies that :
 * - self:: is used instead of Self::
 * - self:: is used for local static member reference
 * - self:: is used instead of self ::
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\Classes;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Abstract_Scope_Sniff;
use Php_code_Sniffer\Util\Tokens;
class Self_Member_Reference_Sniff extends Abstract_Scope_Sniff
{
    /**
     * Constructs a Squiz_Sniffs_Classes_SelfMemberReferenceSniff.
     */
    public function __construct()
    {
        parent::__construct([T_CLASS], [T_DOUBLE_COLON]);
    }
    //end __construct()
    /**
     * Processes the function tokens within the class.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file where this token was found.
     * @param int                         $stackPtr  The position where the token was found.
     * @param int                         $currScope The current scope opener token.
     *
     * @return void
     */
    protected function process_token_within_scope(File $phpcs_file, $stack_ptr, $curr_scope)
    {
        $tokens = $phpcs_file->get_tokens();
        // Determine if this is a double colon which needs to be examined.
        $conditions = $tokens[$stack_ptr]['conditions'];
        $conditions = array_reverse($conditions, true);
        foreach ($conditions as $condition_token => $token_code) {
            if ($token_code === T_CLASS || $token_code === T_ANON_CLASS || $token_code === T_CLOSURE) {
                break;
            }
        }
        if ($condition_token !== $curr_scope) {
            return;
        }
        $called_class_name = $phpcs_file->find_previous(Tokens::$empty_tokens, $stack_ptr - 1, null, true);
        if ($called_class_name === false) {
            // Parse error.
            return;
        }
        if ($tokens[$called_class_name]['code'] === T_SELF) {
            if ($tokens[$called_class_name]['content'] !== 'self') {
                $error = 'Must use "self::" for local static member reference; found "%s::"';
                $data = [$tokens[$called_class_name]['content']];
                $fix = $phpcs_file->add_fixable_error($error, $called_class_name, 'IncorrectCase', $data);
                if ($fix === true) {
                    $phpcs_file->fixer->replace_token($called_class_name, 'self');
                }
                return;
            }
        } elseif ($tokens[$called_class_name]['code'] === T_STRING) {
            // If the class is called with a namespace prefix, build fully qualified
            // namespace calls for both current scope class and requested class.
            $prev_non_empty = $phpcs_file->find_previous(Tokens::$empty_tokens, $called_class_name - 1, null, true);
            if ($prev_non_empty !== false && $tokens[$prev_non_empty]['code'] === T_NS_SEPARATOR) {
                $declaration_name = $this->get_declaration_name_with_namespace($tokens, $called_class_name);
                $declaration_name = ltrim($declaration_name, '\\');
                $full_qualified_class_name = $this->get_namespace_of_scope($phpcs_file, $curr_scope);
                if ($full_qualified_class_name === '\\') {
                    $full_qualified_class_name = '';
                } else {
                    $full_qualified_class_name .= '\\';
                }
                $full_qualified_class_name .= $phpcs_file->get_declaration_name($curr_scope);
            } else {
                $declaration_name = $phpcs_file->get_declaration_name($curr_scope);
                $full_qualified_class_name = $tokens[$called_class_name]['content'];
            }
            if ($declaration_name === $full_qualified_class_name) {
                // Class name is the same as the current class, which is not allowed.
                $error = 'Must use "self::" for local static member reference';
                $fix = $phpcs_file->add_fixable_error($error, $called_class_name, 'NotUsed');
                if ($fix === true) {
                    $phpcs_file->fixer->begin_changeset();
                    $current_pointer = $stack_ptr - 1;
                    while ($tokens[$current_pointer]['code'] === T_NS_SEPARATOR || $tokens[$current_pointer]['code'] === T_STRING || isset(Tokens::$empty_tokens[$tokens[$current_pointer]['code']]) === true) {
                        if (isset(Tokens::$empty_tokens[$tokens[$current_pointer]['code']]) === true) {
                            --$current_pointer;
                            continue;
                        }
                        $phpcs_file->fixer->replace_token($current_pointer, '');
                        --$current_pointer;
                    }
                    $phpcs_file->fixer->replace_token($stack_ptr, 'self::');
                    $phpcs_file->fixer->end_changeset();
                    // Fix potential whitespace issues in the next loop.
                    return;
                }
                //end if
            }
            //end if
        }
        //end if
        if ($tokens[$stack_ptr - 1]['code'] === T_WHITESPACE) {
            $found = $tokens[$stack_ptr - 1]['length'];
            $error = 'Expected 0 spaces before double colon; %s found';
            $data = [$found];
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr - 1, 'SpaceBefore', $data);
            if ($fix === true) {
                $phpcs_file->fixer->begin_changeset();
                for ($i = $stack_ptr - 1; $tokens[$i]['code'] === T_WHITESPACE; $i--) {
                    $phpcs_file->fixer->replace_token($i, '');
                }
                $phpcs_file->fixer->end_changeset();
            }
        }
        if ($tokens[$stack_ptr + 1]['code'] === T_WHITESPACE) {
            $found = $tokens[$stack_ptr + 1]['length'];
            $error = 'Expected 0 spaces after double colon; %s found';
            $data = [$found];
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr - 1, 'SpaceAfter', $data);
            if ($fix === true) {
                $phpcs_file->fixer->begin_changeset();
                for ($i = $stack_ptr + 1; $tokens[$i]['code'] === T_WHITESPACE; $i++) {
                    $phpcs_file->fixer->replace_token($i, '');
                }
                $phpcs_file->fixer->end_changeset();
            }
        }
    }
    //end processTokenWithinScope()
    /**
     * Processes a token that is found within the scope that this test is
     * listening to.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file where this token was found.
     * @param int                         $stackPtr  The position in the stack where this
     *                                               token was found.
     *
     * @return void
     */
    protected function process_token_outside_scope(File $phpcs_file, $stack_ptr)
    {
    }
    //end processTokenOutsideScope()
    /**
     * Returns the declaration names for classes/interfaces/functions with a namespace.
     *
     * @param array $tokens   Token stack for this file
     * @param int   $stackPtr The position where the namespace building will start.
     *
     * @return string
     */
    protected function get_declaration_name_with_namespace(array $tokens, $stack_ptr)
    {
        $name_parts = [];
        $current_pointer = $stack_ptr;
        while ($tokens[$current_pointer]['code'] === T_NS_SEPARATOR || $tokens[$current_pointer]['code'] === T_STRING || isset(Tokens::$empty_tokens[$tokens[$current_pointer]['code']]) === true) {
            if (isset(Tokens::$empty_tokens[$tokens[$current_pointer]['code']]) === true) {
                --$current_pointer;
                continue;
            }
            $name_parts[] = $tokens[$current_pointer]['content'];
            --$current_pointer;
        }
        $name_parts = array_reverse($name_parts);
        return implode('', $name_parts);
    }
    //end getDeclarationNameWithNamespace()
    /**
     * Returns the namespace declaration of a file.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file where this token was found.
     * @param int                         $stackPtr  The position where the search for the
     *                                               namespace declaration will start.
     *
     * @return string
     */
    protected function get_namespace_of_scope(File $phpcs_file, $stack_ptr)
    {
        $namespace = '\\';
        $namespace_declaration = $phpcs_file->find_previous(T_NAMESPACE, $stack_ptr);
        if ($namespace_declaration !== false) {
            $end_of_namespace_declaration = $phpcs_file->find_next([T_SEMICOLON, T_OPEN_CURLY_BRACKET], $namespace_declaration);
            $namespace = $this->get_declaration_name_with_namespace($phpcs_file->get_tokens(), $end_of_namespace_declaration - 1);
        }
        return $namespace;
    }
    //end getNamespaceOfScope()
}
//end class