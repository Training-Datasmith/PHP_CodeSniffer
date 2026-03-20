<?php

declare (strict_types=1);
/**
 * Checks that control structures have the correct spacing around brackets.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\PSR2\Sniffs\Control_Structures;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Control_Structure_Spacing_Sniff implements Sniff
{
    /**
     * How many spaces should follow the opening bracket.
     *
     * @var integer
     */
    public $required_spaces_after_open = 0;
    /**
     * How many spaces should precede the closing bracket.
     *
     * @var integer
     */
    public $required_spaces_before_close = 0;
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_IF, T_WHILE, T_FOREACH, T_FOR, T_SWITCH, T_ELSE, T_ELSEIF, T_CATCH, T_MATCH];
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
        $this->required_spaces_after_open = (int) $this->required_spaces_after_open;
        $this->required_spaces_before_close = (int) $this->required_spaces_before_close;
        $tokens = $phpcs_file->get_tokens();
        if (isset($tokens[$stack_ptr]['parenthesis_opener']) === false || isset($tokens[$stack_ptr]['parenthesis_closer']) === false) {
            return;
        }
        $paren_opener = $tokens[$stack_ptr]['parenthesis_opener'];
        $paren_closer = $tokens[$stack_ptr]['parenthesis_closer'];
        $next_content = $phpcs_file->find_next(T_WHITESPACE, $paren_opener + 1, null, true);
        if (in_array($tokens[$next_content]['code'], Tokens::$comment_tokens, true) === false) {
            $space_after_open = 0;
            if ($tokens[$paren_opener + 1]['code'] === T_WHITESPACE) {
                if (strpos($tokens[$paren_opener + 1]['content'], $phpcs_file->eol_char) !== false) {
                    $space_after_open = 'newline';
                } else {
                    $space_after_open = $tokens[$paren_opener + 1]['length'];
                }
            }
            $phpcs_file->record_metric($stack_ptr, 'Spaces after control structure open parenthesis', $space_after_open);
            if ($space_after_open !== $this->required_spaces_after_open) {
                $error = 'Expected %s spaces after opening bracket; %s found';
                $data = [$this->required_spaces_after_open, $space_after_open];
                $fix = $phpcs_file->add_fixable_error($error, $paren_opener + 1, 'SpacingAfterOpenBrace', $data);
                if ($fix === true) {
                    $padding = str_repeat(' ', $this->required_spaces_after_open);
                    if ($space_after_open === 0) {
                        $phpcs_file->fixer->add_content($paren_opener, $padding);
                    } elseif ($space_after_open === 'newline') {
                        $phpcs_file->fixer->replace_token($paren_opener + 1, '');
                    } else {
                        $phpcs_file->fixer->replace_token($paren_opener + 1, $padding);
                    }
                }
            }
        }
        //end if
        $prev = $phpcs_file->find_previous(T_WHITESPACE, $paren_closer - 1, $paren_opener, true);
        if ($tokens[$prev]['line'] === $tokens[$paren_closer]['line']) {
            $space_before_close = 0;
            if ($tokens[$paren_closer - 1]['code'] === T_WHITESPACE) {
                $space_before_close = strlen(ltrim($tokens[$paren_closer - 1]['content'], $phpcs_file->eol_char));
            }
            $phpcs_file->record_metric($stack_ptr, 'Spaces before control structure close parenthesis', $space_before_close);
            if ($space_before_close !== $this->required_spaces_before_close) {
                $error = 'Expected %s spaces before closing bracket; %s found';
                $data = [$this->required_spaces_before_close, $space_before_close];
                $fix = $phpcs_file->add_fixable_error($error, $paren_closer - 1, 'SpaceBeforeCloseBrace', $data);
                if ($fix === true) {
                    $padding = str_repeat(' ', $this->required_spaces_before_close);
                    if ($space_before_close === 0) {
                        $phpcs_file->fixer->add_content_before($paren_closer, $padding);
                    } else {
                        $phpcs_file->fixer->replace_token($paren_closer - 1, $padding);
                    }
                }
            }
        }
        //end if
    }
    //end process()
}
//end class