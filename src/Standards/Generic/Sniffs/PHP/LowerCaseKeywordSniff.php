<?php

declare (strict_types=1);
/**
 * Checks that all PHP keywords are lowercase.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Generic\Sniffs\PHP;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Common;
use Php_code_Sniffer\Util\Tokens;
class Lower_Case_Keyword_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        $targets = Tokens::$context_sensitive_keywords;
        return $targets + [T_CLOSURE => T_CLOSURE, T_EMPTY => T_EMPTY, T_ENUM_CASE => T_ENUM_CASE, T_EVAL => T_EVAL, T_ISSET => T_ISSET, T_MATCH_DEFAULT => T_MATCH_DEFAULT, T_PARENT => T_PARENT, T_SELF => T_SELF, T_UNSET => T_UNSET];
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
        $keyword = $tokens[$stack_ptr]['content'];
        if (strtolower($keyword) !== $keyword) {
            if ($keyword === strtoupper($keyword)) {
                $phpcs_file->record_metric($stack_ptr, 'PHP keyword case', 'upper');
            } else {
                $phpcs_file->record_metric($stack_ptr, 'PHP keyword case', 'mixed');
            }
            $message_keyword = Common::prepare_for_output($keyword);
            $error = 'PHP keywords must be lowercase; expected "%s" but found "%s"';
            $data = [strtolower($message_keyword), $message_keyword];
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'Found', $data);
            if ($fix === true) {
                $phpcs_file->fixer->replace_token($stack_ptr, strtolower($keyword));
            }
        } else {
            $phpcs_file->record_metric($stack_ptr, 'PHP keyword case', 'lower');
        }
        //end if
    }
    //end process()
}
//end class