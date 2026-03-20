<?php

declare (strict_types=1);
/**
 * Throws errors if spaces are used for indentation other than precision indentation.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Generic\Sniffs\White_Space;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Disallow_Space_Indent_Sniff implements Sniff
{
    /**
     * A list of tokenizers this sniff supports.
     *
     * @var array
     */
    public $supported_tokenizers = ['PHP', 'JS', 'CSS'];
    /**
     * The --tab-width CLI value that is being used.
     *
     * @var integer
     */
    private $tab_width;
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO];
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
        $tabs_replaced = false;
        if ($this->tab_width === null) {
            if (isset($phpcs_file->config->tab_width) === false || $phpcs_file->config->tab_width === 0) {
                // We have no idea how wide tabs are, so assume 4 spaces for fixing.
                // It shouldn't really matter because indent checks elsewhere in the
                // standard should fix things up.
                $this->tab_width = 4;
            } else {
                $this->tab_width = $phpcs_file->config->tab_width;
                $tabs_replaced = true;
            }
        }
        $check_tokens = [T_WHITESPACE => true, T_INLINE_HTML => true, T_DOC_COMMENT_WHITESPACE => true, T_COMMENT => true];
        $eol_len = strlen($phpcs_file->eol_char);
        $tokens = $phpcs_file->get_tokens();
        for ($i = 0; $i < $phpcs_file->num_tokens; $i++) {
            if ($tokens[$i]['column'] !== 1) {
                continue;
            }
            if (isset($check_tokens[$tokens[$i]['code']]) === false) {
                continue;
            }
            // If the tokenizer hasn't replaced tabs with spaces, we need to do it manually.
            $token = $tokens[$i];
            if ($tabs_replaced === false) {
                $phpcs_file->tokenizer->replace_tabs_in_token($token, ' ', ' ', $this->tab_width);
                if (strpos($token['content'], $phpcs_file->eol_char) !== false) {
                    // Newline chars are not counted in the token length.
                    $token['length'] -= $eol_len;
                }
            }
            if (isset($tokens[$i]['orig_content']) === true) {
                $content = $tokens[$i]['orig_content'];
            } else {
                $content = $tokens[$i]['content'];
            }
            $expected_indent_size = $token['length'];
            $record_metrics = true;
            // If this is an inline HTML token or a subsequent line of a multi-line comment,
            // split the content into indentation whitespace and the actual HTML/text.
            $non_whitespace = '';
            if (($tokens[$i]['code'] === T_INLINE_HTML || $tokens[$i]['code'] === T_COMMENT) && preg_match('`^(\s*)(\S.*)`s', $content, $matches) > 0) {
                if (isset($matches[1]) === true) {
                    $content = $matches[1];
                    // Tabs are not replaced in content, so the "length" is wrong.
                    $matches[1] = str_replace("\t", str_repeat(' ', $this->tab_width), $matches[1]);
                    $expected_indent_size = strlen($matches[1]);
                }
                if (isset($matches[2]) === true) {
                    $non_whitespace = $matches[2];
                }
            } elseif (isset($tokens[$i + 1]) === true && $tokens[$i]['line'] < $tokens[$i + 1]['line']) {
                // There is no content after this whitespace except for a newline.
                $content = rtrim($content, "\r\n");
                $non_whitespace = $phpcs_file->eol_char;
                // Don't record metrics for empty lines.
                $record_metrics = false;
            }
            //end if
            $found_spaces = substr_count($content, ' ');
            $found_tabs = substr_count($content, "\t");
            if ($found_spaces === 0 && $found_tabs === 0) {
                // Empty line.
                continue;
            }
            if ($found_spaces === 0 && $found_tabs > 0) {
                // All ok, nothing to do.
                if ($record_metrics === true) {
                    $phpcs_file->record_metric($i, 'Line indent', 'tabs');
                }
                continue;
            }
            if (($tokens[$i]['code'] === T_DOC_COMMENT_WHITESPACE || $tokens[$i]['code'] === T_COMMENT) && $content === ' ') {
                // Ignore all non-indented comments, especially for recording metrics.
                continue;
            }
            // OK, by now we know there will be spaces.
            // We just don't know yet whether they need to be replaced or
            // are precision indentation, nor whether they are correctly
            // placed at the end of the whitespace.
            $tab_after_spaces = strpos($content, "\t", strpos($content, ' '));
            // Calculate the expected tabs and spaces.
            $expected_tabs = (int) floor($expected_indent_size / $this->tab_width);
            $expected_spaces = $expected_indent_size % $this->tab_width;
            if ($found_tabs === 0) {
                if ($record_metrics === true) {
                    $phpcs_file->record_metric($i, 'Line indent', 'spaces');
                }
                if ($found_tabs === $expected_tabs && $found_spaces === $expected_spaces) {
                    // Ignore: precision indentation.
                    continue;
                }
            } else if ($found_tabs === $expected_tabs && $found_spaces === $expected_spaces) {
                // Precision indentation.
                if ($record_metrics === true) {
                    if ($tab_after_spaces !== false) {
                        $phpcs_file->record_metric($i, 'Line indent', 'mixed');
                    } else {
                        $phpcs_file->record_metric($i, 'Line indent', 'tabs');
                    }
                }
                if ($tab_after_spaces === false) {
                    // Ignore: precision indentation is already at the
                    // end of the whitespace.
                    continue;
                }
            } elseif ($record_metrics === true) {
                $phpcs_file->record_metric($i, 'Line indent', 'mixed');
            }
            //end if
            $error = 'Tabs must be used to indent lines; spaces are not allowed';
            $fix = $phpcs_file->add_fixable_error($error, $i, 'SpacesUsed');
            if ($fix === true) {
                $padding = str_repeat("\t", $expected_tabs);
                $padding .= str_repeat(' ', $expected_spaces);
                $phpcs_file->fixer->replace_token($i, $padding . $non_whitespace);
            }
        }
        //end for
        // Ignore the rest of the file.
        return $phpcs_file->num_tokens + 1;
    }
    //end process()
}
//end class