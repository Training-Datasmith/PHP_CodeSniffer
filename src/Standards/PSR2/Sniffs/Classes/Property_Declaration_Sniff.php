<?php

declare (strict_types=1);
/**
 * Verifies that properties are declared correctly.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\PSR2\Sniffs\Classes;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Abstract_Variable_Sniff;
use Php_code_Sniffer\Util\Tokens;
class Property_Declaration_Sniff extends Abstract_Variable_Sniff
{
    /**
     * Processes the function tokens within the class.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file where this token was found.
     * @param int                         $stackPtr  The position where the token was found.
     *
     * @return void
     */
    protected function process_member_var(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        if ($tokens[$stack_ptr]['content'][1] === '_') {
            $error = 'Property name "%s" should not be prefixed with an underscore to indicate visibility';
            $data = [$tokens[$stack_ptr]['content']];
            $phpcs_file->add_warning($error, $stack_ptr, 'Underscore', $data);
        }
        // Detect multiple properties defined at the same time. Throw an error
        // for this, but also only process the first property in the list so we don't
        // repeat errors.
        $find = Tokens::$scope_modifiers;
        $find[] = T_VARIABLE;
        $find[] = T_VAR;
        $find[] = T_READONLY;
        $find[] = T_SEMICOLON;
        $find[] = T_OPEN_CURLY_BRACKET;
        $prev = $phpcs_file->find_previous($find, $stack_ptr - 1);
        if ($tokens[$prev]['code'] === T_VARIABLE) {
            return;
        }
        if ($tokens[$prev]['code'] === T_VAR) {
            $error = 'The var keyword must not be used to declare a property';
            $phpcs_file->add_error($error, $stack_ptr, 'VarUsed');
        }
        $next = $phpcs_file->find_next([T_VARIABLE, T_SEMICOLON], $stack_ptr + 1);
        if ($next !== false && $tokens[$next]['code'] === T_VARIABLE) {
            $error = 'There must not be more than one property declared per statement';
            $phpcs_file->add_error($error, $stack_ptr, 'Multiple');
        }
        try {
            $property_info = $phpcs_file->get_member_properties($stack_ptr);
            if (empty($property_info) === true) {
                return;
            }
        } catch (\Exception $e) {
            // Turns out not to be a property after all.
            return;
        }
        if ($property_info['type'] !== '') {
            $type_token = $property_info['type_end_token'];
            $error = 'There must be 1 space after the property type declaration; %s found';
            if ($tokens[$type_token + 1]['code'] !== T_WHITESPACE) {
                $data = ['0'];
                $fix = $phpcs_file->add_fixable_error($error, $type_token, 'SpacingAfterType', $data);
                if ($fix === true) {
                    $phpcs_file->fixer->add_content($type_token, ' ');
                }
            } elseif ($tokens[$type_token + 1]['content'] !== ' ') {
                $next = $phpcs_file->find_next(T_WHITESPACE, $type_token + 1, null, true);
                if ($tokens[$next]['line'] !== $tokens[$type_token]['line']) {
                    $found = 'newline';
                } else {
                    $found = $tokens[$type_token + 1]['length'];
                }
                $data = [$found];
                $next_non_ws = $phpcs_file->find_next(Tokens::$empty_tokens, $type_token + 1, null, true);
                if ($next_non_ws !== $next) {
                    $phpcs_file->add_error($error, $type_token, 'SpacingAfterType', $data);
                } else {
                    $fix = $phpcs_file->add_fixable_error($error, $type_token, 'SpacingAfterType', $data);
                    if ($fix === true) {
                        if ($found === 'newline') {
                            $phpcs_file->fixer->begin_changeset();
                            for ($x = $type_token + 1; $x < $next; $x++) {
                                $phpcs_file->fixer->replace_token($x, '');
                            }
                            $phpcs_file->fixer->add_content($type_token, ' ');
                            $phpcs_file->fixer->end_changeset();
                        } else {
                            $phpcs_file->fixer->replace_token($type_token + 1, ' ');
                        }
                    }
                }
            }
            //end if
        }
        //end if
        if ($property_info['scope_specified'] === false) {
            $error = 'Visibility must be declared on property "%s"';
            $data = [$tokens[$stack_ptr]['content']];
            $phpcs_file->add_error($error, $stack_ptr, 'ScopeMissing', $data);
        }
        /*
         * Note: per PSR-PER section 4.6, the order should be:
         * - Inheritance modifier: `abstract` or `final`.
         * - Visibility modifier: `public`, `protected`, or `private`.
         * - Scope modifier: `static`.
         * - Mutation modifier: `readonly`.
         * - Type declaration.
         * - Name.
         *
         * Ref: https://www.php-fig.org/per/coding-style/#46-modifier-keywords
         *
         * At this time (PHP 8.2), inheritance modifiers cannot be applied to properties and
         * the `static` and `readonly` modifiers are mutually exclusive and cannot be used together.
         *
         * Based on that, the below modifier keyword order checks are sufficient (for now).
         */
        if ($property_info['scope_specified'] === true && $property_info['is_static'] === true) {
            $scope_ptr = $phpcs_file->find_previous(Tokens::$scope_modifiers, $stack_ptr - 1);
            $static_ptr = $phpcs_file->find_previous(T_STATIC, $stack_ptr - 1);
            if ($scope_ptr > $static_ptr) {
                $error = 'The static declaration must come after the visibility declaration';
                $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'StaticBeforeVisibility');
                if ($fix === true) {
                    $phpcs_file->fixer->begin_changeset();
                    for ($i = $scope_ptr + 1; $scope_ptr < $stack_ptr; $i++) {
                        if ($tokens[$i]['code'] !== T_WHITESPACE) {
                            break;
                        }
                        $phpcs_file->fixer->replace_token($i, '');
                    }
                    $phpcs_file->fixer->replace_token($scope_ptr, '');
                    $phpcs_file->fixer->add_content_before($static_ptr, $property_info['scope'] . ' ');
                    $phpcs_file->fixer->end_changeset();
                }
            }
        }
        //end if
        if ($property_info['scope_specified'] === true && $property_info['is_readonly'] === true) {
            $scope_ptr = $phpcs_file->find_previous(Tokens::$scope_modifiers, $stack_ptr - 1);
            $readonly_ptr = $phpcs_file->find_previous(T_READONLY, $stack_ptr - 1);
            if ($scope_ptr > $readonly_ptr) {
                $error = 'The readonly declaration must come after the visibility declaration';
                $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'ReadonlyBeforeVisibility');
                if ($fix === true) {
                    $phpcs_file->fixer->begin_changeset();
                    for ($i = $scope_ptr + 1; $scope_ptr < $stack_ptr; $i++) {
                        if ($tokens[$i]['code'] !== T_WHITESPACE) {
                            break;
                        }
                        $phpcs_file->fixer->replace_token($i, '');
                    }
                    $phpcs_file->fixer->replace_token($scope_ptr, '');
                    $phpcs_file->fixer->add_content_before($readonly_ptr, $property_info['scope'] . ' ');
                    $phpcs_file->fixer->end_changeset();
                }
            }
        }
        //end if
    }
    //end processMemberVar()
    /**
     * Processes normal variables.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file where this token was found.
     * @param int                         $stackPtr  The position where the token was found.
     *
     * @return void
     */
    protected function process_variable(File $phpcs_file, $stack_ptr)
    {
        /*
            We don't care about normal variables.
        */
    }
    //end processVariable()
    /**
     * Processes variables in double quoted strings.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file where this token was found.
     * @param int                         $stackPtr  The position where the token was found.
     *
     * @return void
     */
    protected function process_variable_in_string(File $phpcs_file, $stack_ptr)
    {
        /*
            We don't care about normal variables.
        */
    }
    //end processVariableInString()
}
//end class