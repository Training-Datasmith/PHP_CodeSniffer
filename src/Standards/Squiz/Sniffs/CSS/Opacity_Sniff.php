<?php

declare (strict_types=1);
/**
 * Ensure that opacity values start with a 0 if it is not a whole number.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\CSS;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Opacity_Sniff implements Sniff
{
    /**
     * A list of tokenizers this sniff supports.
     *
     * @var array
     */
    public $supported_tokenizers = ['CSS'];
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
        if ($tokens[$stack_ptr]['content'] !== 'opacity') {
            return;
        }
        $ignore = Tokens::$empty_tokens;
        $ignore[] = T_COLON;
        $next = $phpcs_file->find_next($ignore, $stack_ptr + 1, null, true);
        if ($next === false || $tokens[$next]['code'] !== T_DNUMBER && $tokens[$next]['code'] !== T_LNUMBER) {
            return;
        }
        $value = $tokens[$next]['content'];
        if ($tokens[$next]['code'] === T_LNUMBER) {
            if ($value !== '0' && $value !== '1') {
                $error = 'Opacity values must be between 0 and 1';
                $phpcs_file->add_error($error, $next, 'Invalid');
            }
        } else {
            if (strlen($value) > 3) {
                $error = 'Opacity values must have a single value after the decimal point';
                $phpcs_file->add_error($error, $next, 'DecimalPrecision');
            } elseif ($value === '0.0' || $value === '1.0') {
                $error = 'Opacity value does not require decimal point; use %s instead';
                $data = [$value[0]];
                $fix = $phpcs_file->add_fixable_error($error, $next, 'PointNotRequired', $data);
                if ($fix === true) {
                    $phpcs_file->fixer->replace_token($next, $value[0]);
                }
            } elseif ($value[0] === '.') {
                $error = 'Opacity values must not start with a decimal point; use 0%s instead';
                $data = [$value];
                $fix = $phpcs_file->add_fixable_error($error, $next, 'StartWithPoint', $data);
                if ($fix === true) {
                    $phpcs_file->fixer->replace_token($next, '0' . $value);
                }
            } elseif ($value[0] !== '0') {
                $error = 'Opacity values must be between 0 and 1';
                $phpcs_file->add_error($error, $next, 'Invalid');
            }
            //end if
        }
        //end if
    }
    //end process()
}
//end class