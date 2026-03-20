<?php

declare (strict_types=1);
/**
 * Bans the use of some styles, such as deprecated or browser-specific styles.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\CSS;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Forbidden_Styles_Sniff implements Sniff
{
    /**
     * A list of tokenizers this sniff supports.
     *
     * @var array
     */
    public $supported_tokenizers = ['CSS'];
    /**
     * A list of forbidden styles with their alternatives.
     *
     * The value is NULL if no alternative exists. i.e., the
     * style should just not be used.
     *
     * @var array<string, string|null>
     */
    protected $forbidden_styles = ['-moz-border-radius' => 'border-radius', '-webkit-border-radius' => 'border-radius', '-moz-border-radius-topleft' => 'border-top-left-radius', '-moz-border-radius-topright' => 'border-top-right-radius', '-moz-border-radius-bottomright' => 'border-bottom-right-radius', '-moz-border-radius-bottomleft' => 'border-bottom-left-radius', '-moz-box-shadow' => 'box-shadow', '-webkit-box-shadow' => 'box-shadow'];
    /**
     * A cache of forbidden style names, for faster lookups.
     *
     * @var string[]
     */
    protected $forbidden_style_names = [];
    /**
     * If true, forbidden styles will be considered regular expressions.
     *
     * @var boolean
     */
    protected $pattern_match = false;
    /**
     * If true, an error will be thrown; otherwise a warning.
     *
     * @var boolean
     */
    public $error = true;
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        $this->forbidden_style_names = array_keys($this->forbidden_styles);
        if ($this->pattern_match === true) {
            foreach ($this->forbidden_style_names as $i => $name) {
                $this->forbidden_style_names[$i] = '/' . $name . '/i';
            }
        }
        return [T_STYLE];
    }
    //end register()
    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token in
     *                                               the stack passed in $tokens.
     *
     * @return void
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        $style = strtolower($tokens[$stack_ptr]['content']);
        $pattern = null;
        if ($this->pattern_match === true) {
            $count = 0;
            $pattern = preg_replace($this->forbidden_style_names, $this->forbidden_style_names, $style, 1, $count);
            if ($count === 0) {
                return;
            }
            // Remove the pattern delimiters and modifier.
            $pattern = substr($pattern, 1, -2);
        } else if (in_array($style, $this->forbidden_style_names, true) === false) {
            return;
        }
        //end if
        $this->add_error($phpcs_file, $stack_ptr, $style, $pattern);
    }
    //end process()
    /**
     * Generates the error or warning for this sniff.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the forbidden style
     *                                               in the token array.
     * @param string                      $style     The name of the forbidden style.
     * @param string                      $pattern   The pattern used for the match.
     *
     * @return void
     */
    protected function add_error($phpcs_file, $stack_ptr, $style, $pattern = null)
    {
        $data = [$style];
        $error = 'The use of style %s is ';
        if ($this->error === true) {
            $type = 'Found';
            $error .= 'forbidden';
        } else {
            $type = 'Discouraged';
            $error .= 'discouraged';
        }
        if ($pattern === null) {
            $pattern = $style;
        }
        if ($this->forbidden_styles[$pattern] !== null) {
            $data[] = $this->forbidden_styles[$pattern];
            if ($this->error === true) {
                $fix = $phpcs_file->add_fixable_error($error . '; use %s instead', $stack_ptr, $type . 'WithAlternative', $data);
            } else {
                $fix = $phpcs_file->add_fixable_warning($error . '; use %s instead', $stack_ptr, $type . 'WithAlternative', $data);
            }
            if ($fix === true) {
                $phpcs_file->fixer->replace_token($stack_ptr, $this->forbidden_styles[$pattern]);
            }
        } else if ($this->error === true) {
            $phpcs_file->add_error($error, $stack_ptr, $type, $data);
        } else {
            $phpcs_file->add_warning($error, $stack_ptr, $type, $data);
        }
    }
    //end addError()
}
//end class