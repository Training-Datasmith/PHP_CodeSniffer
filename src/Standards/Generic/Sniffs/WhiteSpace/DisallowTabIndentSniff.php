<?php

declare (strict_types=1);
/**
 * Throws errors if tabs are used for indentation.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Generic\Sniffs\White_Space;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Disallow_Tab_Indent_Sniff implements Sniff
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
        if ($this->tab_width === null) {
            if (isset($phpcs_file->config->tab_width) === false || $phpcs_file->config->tab_width === 0) {
                // We have no idea how wide tabs are, so assume 4 spaces for metrics.
                $this->tab_width = 4;
            } else {
                $this->tab_width = $phpcs_file->config->tab_width;
            }
        }
        $tokens = $phpcs_file->get_tokens();
        $check_tokens = [T_WHITESPACE => true, T_INLINE_HTML => true, T_DOC_COMMENT_WHITESPACE => true, T_DOC_COMMENT_STRING => true, T_COMMENT => true, T_END_HEREDOC => true, T_END_NOWDOC => true];
        for ($i = 0; $i < $phpcs_file->num_tokens; $i++) {
            if (isset($check_tokens[$tokens[$i]['code']]) === false) {
                continue;
            }
            // If tabs are being converted to spaces by the tokeniser, the
            // original content should be checked instead of the converted content.
            if (isset($tokens[$i]['orig_content']) === true) {
                $content = $tokens[$i]['orig_content'];
            } else {
                $content = $tokens[$i]['content'];
            }
            if ($content === '') {
                continue;
            }
            // If this is an inline HTML token or a subsequent line of a multi-line comment,
            // split off the indentation as that is the only part to take into account for the metrics.
            $indentation = $content;
            if (($tokens[$i]['code'] === T_INLINE_HTML || $tokens[$i]['code'] === T_COMMENT) && preg_match('`^(\s*)\S.*`s', $content, $matches) > 0) {
                if (isset($matches[1]) === true) {
                    $indentation = $matches[1];
                }
            }
            if (($tokens[$i]['code'] === T_DOC_COMMENT_WHITESPACE || $tokens[$i]['code'] === T_COMMENT) && $indentation === ' ') {
                // Ignore all non-indented comments, especially for recording metrics.
                continue;
            }
            $record_metrics = true;
            if ($content === $indentation && isset($tokens[$i + 1]) === true && $tokens[$i]['line'] < $tokens[$i + 1]['line']) {
                // Don't record metrics for empty lines.
                $record_metrics = false;
            }
            $found_tabs = substr_count($content, "\t");
            $error = 'Spaces must be used to indent lines; tabs are not allowed';
            $error_code = 'TabsUsed';
            if ($tokens[$i]['column'] === 1) {
                if ($record_metrics === true) {
                    $found_indent_spaces = substr_count($indentation, ' ');
                    $found_indent_tabs = substr_count($indentation, "\t");
                    if ($found_indent_tabs > 0 && $found_indent_spaces === 0) {
                        $phpcs_file->record_metric($i, 'Line indent', 'tabs');
                    } elseif ($found_indent_tabs === 0 && $found_indent_spaces > 0) {
                        $phpcs_file->record_metric($i, 'Line indent', 'spaces');
                    } elseif ($found_indent_tabs > 0 && $found_indent_spaces > 0) {
                        $space_position = strpos($indentation, ' ');
                        $tab_after_spaces = strpos($indentation, "\t", $space_position);
                        if ($tab_after_spaces !== false) {
                            $phpcs_file->record_metric($i, 'Line indent', 'mixed');
                        } else {
                            // Check for use of precision spaces.
                            $num_tabs = (int) floor($found_indent_spaces / $this->tab_width);
                            if ($num_tabs === 0) {
                                $phpcs_file->record_metric($i, 'Line indent', 'tabs');
                            } else {
                                $phpcs_file->record_metric($i, 'Line indent', 'mixed');
                            }
                        }
                    }
                }
                //end if
            } else if ($found_tabs > 0) {
                $error = 'Spaces must be used for alignment; tabs are not allowed';
                $error_code = 'NonIndentTabsUsed';
            }
            //end if
            if ($found_tabs === 0) {
                continue;
            }
            $fix = $phpcs_file->add_fixable_error($error, $i, $error_code);
            if ($fix === true) {
                if (isset($tokens[$i]['orig_content']) === true) {
                    // Use the replacement that PHPCS has already done.
                    $phpcs_file->fixer->replace_token($i, $tokens[$i]['content']);
                } else {
                    // Replace tabs with spaces, using an indent of tabWidth spaces.
                    // Other sniffs can then correct the indent if they need to.
                    $new_content = str_replace("\t", str_repeat(' ', $this->tab_width), $tokens[$i]['content']);
                    $phpcs_file->fixer->replace_token($i, $new_content);
                }
            }
        }
        //end for
        // Ignore the rest of the file.
        return $phpcs_file->num_tokens + 1;
    }
    //end process()
}
//end class