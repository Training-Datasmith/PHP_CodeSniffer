<?php

declare (strict_types=1);
/**
 * Ensure sizes are defined using shorthand notation where possible.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\CSS;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Shorthand_Size_Sniff implements Sniff
{
    /**
     * A list of tokenizers this sniff supports.
     *
     * @var array
     */
    public $supported_tokenizers = ['CSS'];
    /**
     * A list of styles that we shouldn't check.
     *
     * These have values that looks like sizes, but are not.
     *
     * @var array
     */
    protected $exclude_styles = ['background-position' => 'background-position', 'box-shadow' => 'box-shadow', 'transform-origin' => 'transform-origin', '-webkit-transform-origin' => '-webkit-transform-origin', '-ms-transform-origin' => '-ms-transform-origin'];
    /**
     * Returns the token types that this sniff is interested in.
     *
     * @return int[]
     */
    public function register()
    {
        return [T_STYLE];
    }
    //end register()
    /**
     * Processes the tokens that this sniff is interested in.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file where the token was found.
     * @param int                         $stackPtr  The position in the stack where
     *                                               the token was found.
     *
     * @return void
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        // Some styles look like shorthand but are not actually a set of 4 sizes.
        $style = strtolower($tokens[$stack_ptr]['content']);
        if (isset($this->exclude_styles[$style]) === true) {
            return;
        }
        $end = $phpcs_file->find_next(T_SEMICOLON, $stack_ptr + 1);
        if ($end === false) {
            // Live coding or parse error.
            return;
        }
        // Get the whole style content.
        $orig_content = $phpcs_file->get_tokens_as_string($stack_ptr + 1, $end - $stack_ptr - 1);
        $orig_content = trim($orig_content, ':');
        $orig_content = trim($orig_content);
        // Account for a !important annotation.
        $content = $orig_content;
        if (substr($content, -10) === '!important') {
            $content = substr($content, 0, -10);
            $content = trim($content);
        }
        // Check if this style value is a set of numbers with optional prefixes.
        $content = preg_replace('/\s+/', ' ', $content);
        $values = [];
        $num = preg_match_all('/(?:[0-9]+)(?:[a-zA-Z]{2}\s+|%\s+|\s+)/', $content . ' ', $values, PREG_SET_ORDER);
        // Only interested in styles that have multiple sizes defined.
        if ($num < 2) {
            return;
        }
        // Rebuild the content we matched to ensure we got everything.
        $matched = '';
        foreach ($values as $value) {
            $matched .= $value[0];
        }
        if ($content !== trim($matched)) {
            return;
        }
        if ($num === 3) {
            $expected = trim($content . ' ' . $values[1][0]);
            $error = 'Shorthand syntax not allowed here; use %s instead';
            $data = [$expected];
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'NotAllowed', $data);
            if ($fix === true) {
                $phpcs_file->fixer->begin_changeset();
                if (substr($orig_content, -10) === '!important') {
                    $expected .= ' !important';
                }
                $next = $phpcs_file->find_next(T_WHITESPACE, $stack_ptr + 2, null, true);
                $phpcs_file->fixer->replace_token($next, $expected);
                for ($next++; $next < $end; $next++) {
                    $phpcs_file->fixer->replace_token($next, '');
                }
                $phpcs_file->fixer->end_changeset();
            }
            return;
        }
        //end if
        if ($num === 2) {
            if ($values[0][0] !== $values[1][0]) {
                // Both values are different, so it is already shorthand.
                return;
            }
        } elseif ($values[0][0] !== $values[2][0] || $values[1][0] !== $values[3][0]) {
            // Can't shorthand this.
            return;
        }
        if ($values[0][0] === $values[1][0]) {
            // All values are the same.
            $expected = trim($values[0][0]);
        } else {
            $expected = trim($values[0][0]) . ' ' . trim($values[1][0]);
        }
        $error = 'Size definitions must use shorthand if available; expected "%s" but found "%s"';
        $data = [$expected, $content];
        $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'NotUsed', $data);
        if ($fix === true) {
            $phpcs_file->fixer->begin_changeset();
            if (substr($orig_content, -10) === '!important') {
                $expected .= ' !important';
            }
            $next = $phpcs_file->find_next(T_COLON, $stack_ptr + 1);
            $phpcs_file->fixer->add_content($next, ' ' . $expected);
            for ($next++; $next < $end; $next++) {
                $phpcs_file->fixer->replace_token($next, '');
            }
            $phpcs_file->fixer->end_changeset();
        }
    }
    //end process()
}
//end class