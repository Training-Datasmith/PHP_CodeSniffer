<?php

declare (strict_types=1);
/**
 * Checks that all uses of true, false and null are lowercase.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Generic\Sniffs\PHP;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Lower_Case_Constant_Sniff implements Sniff
{
    /**
     * A list of tokenizers this sniff supports.
     *
     * @var array
     */
    public $supported_tokenizers = ['PHP', 'JS'];
    /**
     * The tokens this sniff is targetting.
     *
     * @var array
     */
    private $targets = [T_TRUE => T_TRUE, T_FALSE => T_FALSE, T_NULL => T_NULL];
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        $targets = $this->targets;
        // Register function keywords to filter out type declarations.
        $targets[] = T_FUNCTION;
        $targets[] = T_CLOSURE;
        $targets[] = T_FN;
        return $targets;
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
        // Handle function declarations separately as they may contain the keywords in type declarations.
        if ($tokens[$stack_ptr]['code'] === T_FUNCTION || $tokens[$stack_ptr]['code'] === T_CLOSURE || $tokens[$stack_ptr]['code'] === T_FN) {
            if (isset($tokens[$stack_ptr]['parenthesis_closer']) === false) {
                return;
            }
            $end = $tokens[$stack_ptr]['parenthesis_closer'];
            if (isset($tokens[$stack_ptr]['scope_opener']) === true) {
                $end = $tokens[$stack_ptr]['scope_opener'];
            }
            // Do a quick check if any of the targets exist in the declaration.
            $found = $phpcs_file->find_next($this->targets, $tokens[$stack_ptr]['parenthesis_opener'], $end);
            if ($found === false) {
                // Skip forward, no need to examine these tokens again.
                return $end;
            }
            // Handle the whole function declaration in one go.
            $params = $phpcs_file->get_method_parameters($stack_ptr);
            foreach ($params as $param) {
                if (isset($param['default_token']) === false) {
                    continue;
                }
                $param_end = $param['comma_token'];
                if ($param['comma_token'] === false) {
                    $param_end = $tokens[$stack_ptr]['parenthesis_closer'];
                }
                for ($i = $param['default_token']; $i < $param_end; $i++) {
                    if (isset($this->targets[$tokens[$i]['code']]) === true) {
                        $this->process_constant($phpcs_file, $i);
                    }
                }
            }
            // Skip over return type declarations.
            return $end;
        }
        //end if
        // Handle property declarations separately as they may contain the keywords in type declarations.
        if (isset($tokens[$stack_ptr]['conditions']) === true) {
            $conditions = $tokens[$stack_ptr]['conditions'];
            $last_condition = end($conditions);
            if (isset(Tokens::$oo_scope_tokens[$last_condition]) === true) {
                // This can only be an OO constant or property declaration as methods are handled above.
                $equals = $phpcs_file->find_previous(T_EQUAL, $stack_ptr - 1, null, false, null, true);
                if ($equals !== false) {
                    $this->process_constant($phpcs_file, $stack_ptr);
                }
                return;
            }
        }
        // Handle everything else.
        $this->process_constant($phpcs_file, $stack_ptr);
    }
    //end process()
    /**
     * Processes a non-type declaration constant.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token in the
     *                                               stack passed in $tokens.
     *
     * @return void
     */
    protected function process_constant(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        $keyword = $tokens[$stack_ptr]['content'];
        $expected = strtolower($keyword);
        if ($keyword !== $expected) {
            if ($keyword === strtoupper($keyword)) {
                $phpcs_file->record_metric($stack_ptr, 'PHP constant case', 'upper');
            } else {
                $phpcs_file->record_metric($stack_ptr, 'PHP constant case', 'mixed');
            }
            $error = 'TRUE, FALSE and NULL must be lowercase; expected "%s" but found "%s"';
            $data = [$expected, $keyword];
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'Found', $data);
            if ($fix === true) {
                $phpcs_file->fixer->replace_token($stack_ptr, $expected);
            }
        } else {
            $phpcs_file->record_metric($stack_ptr, 'PHP constant case', 'lower');
        }
    }
    //end processConstant()
}
//end class