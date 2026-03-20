<?php

declare (strict_types=1);
/**
 * Bans the use of size-based functions in loop conditions.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\PHP;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Disallow_Size_Functions_In_Loops_Sniff implements Sniff
{
    /**
     * A list of tokenizers this sniff supports.
     *
     * @var array
     */
    public $supported_tokenizers = ['PHP', 'JS'];
    /**
     * An array of functions we don't want in the condition of loops.
     *
     * @var array
     */
    protected $forbidden_functions = ['PHP' => ['sizeof' => true, 'strlen' => true, 'count' => true], 'JS' => ['length' => true]];
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_WHILE, T_FOR];
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
        $tokens = $phpcs_file->get_tokens();
        $tokenizer = $phpcs_file->tokenizer_type;
        $open_bracket = $tokens[$stack_ptr]['parenthesis_opener'];
        $close_bracket = $tokens[$stack_ptr]['parenthesis_closer'];
        if ($tokens[$stack_ptr]['code'] === T_FOR) {
            // We only want to check the condition in FOR loops.
            $start = $phpcs_file->find_next(T_SEMICOLON, $open_bracket + 1);
            $end = $phpcs_file->find_previous(T_SEMICOLON, $close_bracket - 1);
        } else {
            $start = $open_bracket;
            $end = $close_bracket;
        }
        for ($i = $start + 1; $i < $end; $i++) {
            if ($tokens[$i]['code'] === T_STRING && isset($this->forbidden_functions[$tokenizer][$tokens[$i]['content']]) === true) {
                $function_name = $tokens[$i]['content'];
                if ($tokenizer === 'JS') {
                    // Needs to be in the form object.function to be valid.
                    $prev = $phpcs_file->find_previous(T_WHITESPACE, $i - 1, null, true);
                    if ($prev === false) {
                        continue;
                    }
                    if ($tokens[$prev]['code'] !== T_OBJECT_OPERATOR) {
                        continue;
                    }
                    $function_name = 'object.' . $function_name;
                } else {
                    // Make sure it isn't a member var.
                    if ($tokens[$i - 1]['code'] === T_OBJECT_OPERATOR) {
                        continue;
                    }
                    if ($tokens[$i - 1]['code'] === T_NULLSAFE_OBJECT_OPERATOR) {
                        continue;
                    }
                    $function_name .= '()';
                }
                $error = 'The use of %s inside a loop condition is not allowed; assign the return value to a variable and use the variable in the loop condition instead';
                $data = [$function_name];
                $phpcs_file->add_error($error, $i, 'Found', $data);
            }
            //end if
        }
        //end for
    }
    //end process()
}
//end class