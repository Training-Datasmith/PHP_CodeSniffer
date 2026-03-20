<?php

declare (strict_types=1);
/**
 * Detects variable assignments being made within conditions.
 *
 * This is a typical code smell and more often than not a comparison was intended.
 *
 * Note: this sniff does not detect variable assignments in the conditional part of ternaries!
 *
 * @author    Juliette Reinders Folmer <phpcs_nospam@adviesenzo.nl>
 * @copyright 2017 Juliette Reinders Folmer. All rights reserved.
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Generic\Sniffs\Code_Analysis;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Assignment_In_Condition_Sniff implements Sniff
{
    /**
     * Assignment tokens to trigger on.
     *
     * Set in the register() method.
     *
     * @var array
     */
    protected $assignment_tokens = [];
    /**
     * The tokens that indicate the start of a condition.
     *
     * @var array
     */
    protected $condition_start_tokens = [];
    /**
     * Registers the tokens that this sniff wants to listen for.
     *
     * @return int[]
     */
    public function register()
    {
        $this->assignment_tokens = Tokens::$assignment_tokens;
        unset($this->assignment_tokens[T_DOUBLE_ARROW]);
        $starters = Tokens::$boolean_operators;
        $starters[T_SEMICOLON] = T_SEMICOLON;
        $starters[T_OPEN_PARENTHESIS] = T_OPEN_PARENTHESIS;
        $this->condition_start_tokens = $starters;
        return [T_IF, T_ELSEIF, T_FOR, T_SWITCH, T_CASE, T_WHILE, T_MATCH];
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
        $token = $tokens[$stack_ptr];
        // Find the condition opener/closer.
        if ($token['code'] === T_FOR) {
            if (isset($token['parenthesis_opener'], $token['parenthesis_closer']) === false) {
                return;
            }
            $semicolon = $phpcs_file->find_next(T_SEMICOLON, $token['parenthesis_opener'] + 1, $token['parenthesis_closer']);
            if ($semicolon === false) {
                return;
            }
            $opener = $semicolon;
            $semicolon = $phpcs_file->find_next(T_SEMICOLON, $opener + 1, $token['parenthesis_closer']);
            if ($semicolon === false) {
                return;
            }
            $closer = $semicolon;
            unset($semicolon);
        } elseif ($token['code'] === T_CASE) {
            if (isset($token['scope_opener']) === false) {
                return;
            }
            $opener = $stack_ptr;
            $closer = $token['scope_opener'];
        } else {
            if (isset($token['parenthesis_opener'], $token['parenthesis_closer']) === false) {
                return;
            }
            $opener = $token['parenthesis_opener'];
            $closer = $token['parenthesis_closer'];
        }
        //end if
        $start_pos = $opener;
        do {
            $has_assignment = $phpcs_file->find_next($this->assignment_tokens, $start_pos + 1, $closer);
            if ($has_assignment === false) {
                return;
            }
            // Examine whether the left side is a variable.
            $has_variable = false;
            $condition_start = $start_pos;
            $alt_condition_start = $phpcs_file->find_previous($this->condition_start_tokens, $has_assignment - 1, $start_pos);
            if ($alt_condition_start !== false) {
                $condition_start = $alt_condition_start;
            }
            for ($i = $has_assignment; $i > $condition_start; $i--) {
                if (isset(Tokens::$empty_tokens[$tokens[$i]['code']]) === true) {
                    continue;
                }
                // If this is a variable or array, we've seen all we need to see.
                if ($tokens[$i]['code'] === T_VARIABLE || $tokens[$i]['code'] === T_CLOSE_SQUARE_BRACKET) {
                    $has_variable = true;
                    break;
                }
                // If this is a function call or something, we are OK.
                if ($tokens[$i]['code'] === T_CLOSE_PARENTHESIS) {
                    break;
                }
            }
            if ($has_variable === true) {
                $error_code = 'Found';
                if ($token['code'] === T_WHILE) {
                    $error_code = 'FoundInWhileCondition';
                }
                $phpcs_file->add_warning('Variable assignment found within a condition. Did you mean to do a comparison ?', $has_assignment, $error_code);
            }
            $start_pos = $has_assignment;
        } while ($start_pos < $closer);
    }
    //end process()
}
//end class