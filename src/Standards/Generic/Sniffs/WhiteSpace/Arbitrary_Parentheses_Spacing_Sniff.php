<?php

declare (strict_types=1);
/**
 * Check & fix whitespace on the inside of arbitrary parentheses.
 *
 * Arbitrary parentheses are those which are not owned by a function (call), array or control structure.
 * Spacing on the outside is not checked on purpose as this would too easily conflict with other spacing rules.
 *
 * @author    Juliette Reinders Folmer <phpcs_nospam@adviesenzo.nl>
 * @copyright 2017 Juliette Reinders Folmer. All rights reserved.
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Generic\Sniffs\White_Space;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Arbitrary_Parentheses_Spacing_Sniff implements Sniff
{
    /**
     * The number of spaces desired on the inside of the parentheses.
     *
     * @var integer
     */
    public $spacing = 0;
    /**
     * Allow newlines instead of spaces.
     *
     * @var boolean
     */
    public $ignore_newlines = false;
    /**
     * Tokens which when they precede an open parenthesis indicate
     * that this is a type of structure this sniff should ignore.
     *
     * @var array
     */
    private $ignore_tokens = [];
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        $this->ignore_tokens = Tokens::$function_name_tokens;
        $this->ignore_tokens[T_VARIABLE] = T_VARIABLE;
        $this->ignore_tokens[T_CLOSE_PARENTHESIS] = T_CLOSE_PARENTHESIS;
        $this->ignore_tokens[T_CLOSE_CURLY_BRACKET] = T_CLOSE_CURLY_BRACKET;
        $this->ignore_tokens[T_CLOSE_SQUARE_BRACKET] = T_CLOSE_SQUARE_BRACKET;
        $this->ignore_tokens[T_CLOSE_SHORT_ARRAY] = T_CLOSE_SHORT_ARRAY;
        $this->ignore_tokens[T_USE] = T_USE;
        $this->ignore_tokens[T_THROW] = T_THROW;
        $this->ignore_tokens[T_YIELD] = T_YIELD;
        $this->ignore_tokens[T_YIELD_FROM] = T_YIELD_FROM;
        $this->ignore_tokens[T_CLONE] = T_CLONE;
        return [T_OPEN_PARENTHESIS, T_CLOSE_PARENTHESIS];
    }
    //end register()
    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile All the tokens found in the document.
     * @param int                         $stackPtr  The position of the current token in
     *                                               the stack passed in $tokens.
     *
     * @return void
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        if (isset($tokens[$stack_ptr]['parenthesis_owner']) === true) {
            // This parenthesis is owned by a function/control structure etc.
            return;
        }
        // More checking for the type of parenthesis we *don't* want to handle.
        $opener = $stack_ptr;
        if ($tokens[$stack_ptr]['code'] === T_CLOSE_PARENTHESIS) {
            if (isset($tokens[$stack_ptr]['parenthesis_opener']) === false) {
                // Parse error.
                return;
            }
            $opener = $tokens[$stack_ptr]['parenthesis_opener'];
        }
        $pre_opener = $phpcs_file->find_previous(Tokens::$empty_tokens, $opener - 1, null, true);
        if ($pre_opener !== false && isset($this->ignore_tokens[$tokens[$pre_opener]['code']]) === true && isset($tokens[$pre_opener]['scope_condition']) === false) {
            // Function or language construct call.
            return;
        }
        // Check for empty parentheses.
        if ($tokens[$stack_ptr]['code'] === T_OPEN_PARENTHESIS && isset($tokens[$stack_ptr]['parenthesis_closer']) === true) {
            $next_non_empty = $phpcs_file->find_next(T_WHITESPACE, $stack_ptr + 1, null, true);
            if ($next_non_empty === $tokens[$stack_ptr]['parenthesis_closer']) {
                $phpcs_file->add_warning('Empty set of arbitrary parentheses found.', $stack_ptr, 'FoundEmpty');
                return $tokens[$stack_ptr]['parenthesis_closer'] + 1;
            }
        }
        // Check the spacing on the inside of the parentheses.
        $this->spacing = (int) $this->spacing;
        if ($tokens[$stack_ptr]['code'] === T_OPEN_PARENTHESIS && isset($tokens[$stack_ptr + 1], $tokens[$stack_ptr + 2]) === true) {
            $next_token = $tokens[$stack_ptr + 1];
            if ($next_token['code'] !== T_WHITESPACE) {
                $inside = 0;
            } else if ($tokens[$stack_ptr + 2]['line'] !== $tokens[$stack_ptr]['line']) {
                $inside = 'newline';
            } else {
                $inside = $next_token['length'];
            }
            if ($this->spacing !== $inside && ($inside !== 'newline' || $this->ignore_newlines === false)) {
                $error = 'Expected %s space after open parenthesis; %s found';
                $data = [$this->spacing, $inside];
                $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'SpaceAfterOpen', $data);
                if ($fix === true) {
                    $expected = '';
                    if ($this->spacing > 0) {
                        $expected = str_repeat(' ', $this->spacing);
                    }
                    if ($inside === 0) {
                        if ($expected !== '') {
                            $phpcs_file->fixer->add_content($stack_ptr, $expected);
                        }
                    } elseif ($inside === 'newline') {
                        $phpcs_file->fixer->begin_changeset();
                        for ($i = $stack_ptr + 2; $i < $phpcs_file->num_tokens; $i++) {
                            if ($tokens[$i]['code'] !== T_WHITESPACE) {
                                break;
                            }
                            $phpcs_file->fixer->replace_token($i, '');
                        }
                        $phpcs_file->fixer->replace_token($stack_ptr + 1, $expected);
                        $phpcs_file->fixer->end_changeset();
                    } else {
                        $phpcs_file->fixer->replace_token($stack_ptr + 1, $expected);
                    }
                }
                //end if
            }
            //end if
        }
        //end if
        if ($tokens[$stack_ptr]['code'] === T_CLOSE_PARENTHESIS && isset($tokens[$stack_ptr - 1], $tokens[$stack_ptr - 2]) === true) {
            $prev_token = $tokens[$stack_ptr - 1];
            if ($prev_token['code'] !== T_WHITESPACE) {
                $inside = 0;
            } else if ($tokens[$stack_ptr - 2]['line'] !== $tokens[$stack_ptr]['line']) {
                $inside = 'newline';
            } else {
                $inside = $prev_token['length'];
            }
            if ($this->spacing !== $inside && ($inside !== 'newline' || $this->ignore_newlines === false)) {
                $error = 'Expected %s space before close parenthesis; %s found';
                $data = [$this->spacing, $inside];
                $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'SpaceBeforeClose', $data);
                if ($fix === true) {
                    $expected = '';
                    if ($this->spacing > 0) {
                        $expected = str_repeat(' ', $this->spacing);
                    }
                    if ($inside === 0) {
                        if ($expected !== '') {
                            $phpcs_file->fixer->add_content_before($stack_ptr, $expected);
                        }
                    } elseif ($inside === 'newline') {
                        $phpcs_file->fixer->begin_changeset();
                        for ($i = $stack_ptr - 2; $i > 0; $i--) {
                            if ($tokens[$i]['code'] !== T_WHITESPACE) {
                                break;
                            }
                            $phpcs_file->fixer->replace_token($i, '');
                        }
                        $phpcs_file->fixer->replace_token($stack_ptr - 1, $expected);
                        $phpcs_file->fixer->end_changeset();
                    } else {
                        $phpcs_file->fixer->replace_token($stack_ptr - 1, $expected);
                    }
                }
                //end if
            }
            //end if
        }
        //end if
    }
    //end process()
}
//end class