<?php

declare (strict_types=1);
/**
 * Ensures a file declares new symbols and causes no other side effects, or executes logic with side effects, but not both.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\PSR1\Sniffs\Files;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Side_Effects_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_OPEN_TAG];
    }
    //end register()
    /**
     * Processes this sniff, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token in
     *                                               the token stack.
     *
     * @return void
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        $result = $this->search_for_conflict($phpcs_file, 0, $phpcs_file->num_tokens - 1, $tokens);
        if ($result['symbol'] !== null && $result['effect'] !== null) {
            $error = 'A file should declare new symbols (classes, functions, constants, etc.) and cause no other side effects, or it should execute logic with side effects, but should not do both. The first symbol is defined on line %s and the first side effect is on line %s.';
            $data = [$tokens[$result['symbol']]['line'], $tokens[$result['effect']]['line']];
            $phpcs_file->add_warning($error, 0, 'FoundWithSymbols', $data);
            $phpcs_file->record_metric($stack_ptr, 'Declarations and side effects mixed', 'yes');
        } else {
            $phpcs_file->record_metric($stack_ptr, 'Declarations and side effects mixed', 'no');
        }
        // Ignore the rest of the file.
        return $phpcs_file->num_tokens + 1;
    }
    //end process()
    /**
     * Searches for symbol declarations and side effects.
     *
     * Returns the positions of both the first symbol declared and the first
     * side effect in the file. A NULL value for either indicates nothing was
     * found.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $start     The token to start searching from.
     * @param int                         $end       The token to search to.
     * @param array                       $tokens    The stack of tokens that make up
     *                                               the file.
     *
     * @return array
     */
    private function search_for_conflict($phpcs_file, $start, $end, array $tokens)
    {
        $symbols = [T_CLASS => T_CLASS, T_INTERFACE => T_INTERFACE, T_TRAIT => T_TRAIT, T_ENUM => T_ENUM, T_FUNCTION => T_FUNCTION];
        $conditions = [T_IF => T_IF, T_ELSE => T_ELSE, T_ELSEIF => T_ELSEIF];
        $check_annotations = $phpcs_file->config->annotations;
        $first_symbol = null;
        $first_effect = null;
        for ($i = $start; $i <= $end; $i++) {
            // Respect phpcs:disable comments.
            if ($check_annotations === true && $tokens[$i]['code'] === T_PHPCS_DISABLE && (empty($tokens[$i]['sniffCodes']) === true || isset($tokens[$i]['sniffCodes']['PSR1']) === true || isset($tokens[$i]['sniffCodes']['PSR1.Files']) === true || isset($tokens[$i]['sniffCodes']['PSR1.Files.SideEffects']) === true)) {
                do {
                    $i = $phpcs_file->find_next(T_PHPCS_ENABLE, $i + 1);
                } while ($i !== false && empty($tokens[$i]['sniffCodes']) === false && isset($tokens[$i]['sniffCodes']['PSR1']) === false && isset($tokens[$i]['sniffCodes']['PSR1.Files']) === false && isset($tokens[$i]['sniffCodes']['PSR1.Files.SideEffects']) === false);
                if ($i === false) {
                    // The entire rest of the file is disabled,
                    // so return what we have so far.
                    break;
                }
                continue;
            }
            // Ignore whitespace and comments.
            if (isset(Tokens::$empty_tokens[$tokens[$i]['code']]) === true) {
                continue;
            }
            // Ignore PHP tags.
            if ($tokens[$i]['code'] === T_OPEN_TAG) {
                continue;
            }
            if ($tokens[$i]['code'] === T_CLOSE_TAG) {
                continue;
            }
            // Ignore shebang.
            if (substr($tokens[$i]['content'], 0, 2) === '#!') {
                continue;
            }
            // Ignore logical operators.
            if (isset(Tokens::$boolean_operators[$tokens[$i]['code']]) === true) {
                continue;
            }
            // Ignore entire namespace, declare, const and use statements.
            if ($tokens[$i]['code'] === T_NAMESPACE || $tokens[$i]['code'] === T_USE || $tokens[$i]['code'] === T_DECLARE || $tokens[$i]['code'] === T_CONST) {
                if (isset($tokens[$i]['scope_opener']) === true) {
                    $i = $tokens[$i]['scope_closer'];
                    if ($tokens[$i]['code'] === T_ENDDECLARE) {
                        $semicolon = $phpcs_file->find_next(Tokens::$empty_tokens, $i + 1, null, true);
                        if ($semicolon !== false && $tokens[$semicolon]['code'] === T_SEMICOLON) {
                            $i = $semicolon;
                        }
                    }
                } else {
                    $semicolon = $phpcs_file->find_next(T_SEMICOLON, $i + 1);
                    if ($semicolon !== false) {
                        $i = $semicolon;
                    }
                }
                continue;
            }
            // Ignore function/class prefixes.
            if (isset(Tokens::$method_prefixes[$tokens[$i]['code']]) === true) {
                continue;
            }
            if ($tokens[$i]['code'] === T_READONLY) {
                continue;
            }
            // Ignore anon classes.
            if ($tokens[$i]['code'] === T_ANON_CLASS) {
                $i = $tokens[$i]['scope_closer'];
                continue;
            }
            // Ignore attributes.
            if ($tokens[$i]['code'] === T_ATTRIBUTE && isset($tokens[$i]['attribute_closer']) === true) {
                $i = $tokens[$i]['attribute_closer'];
                continue;
            }
            // Detect and skip over symbols.
            if (isset($symbols[$tokens[$i]['code']]) === true && isset($tokens[$i]['scope_closer']) === true) {
                if ($first_symbol === null) {
                    $first_symbol = $i;
                }
                $i = $tokens[$i]['scope_closer'];
                continue;
            }
            if ($tokens[$i]['code'] === T_STRING && strtolower($tokens[$i]['content']) === 'define') {
                $prev = $phpcs_file->find_previous(Tokens::$empty_tokens, $i - 1, null, true);
                if ($tokens[$prev]['code'] !== T_OBJECT_OPERATOR && $tokens[$prev]['code'] !== T_NULLSAFE_OBJECT_OPERATOR && $tokens[$prev]['code'] !== T_DOUBLE_COLON && $tokens[$prev]['code'] !== T_FUNCTION) {
                    if ($first_symbol === null) {
                        $first_symbol = $i;
                    }
                    $semicolon = $phpcs_file->find_next(T_SEMICOLON, $i + 1);
                    if ($semicolon !== false) {
                        $i = $semicolon;
                    }
                    continue;
                }
            }
            //end if
            // Special case for defined() as it can be used to see
            // if a constant (a symbol) should be defined or not and
            // doesn't need to use a full conditional block.
            if ($tokens[$i]['code'] === T_STRING && strtolower($tokens[$i]['content']) === 'defined') {
                $open_bracket = $phpcs_file->find_next(Tokens::$empty_tokens, $i + 1, null, true);
                if ($open_bracket !== false && $tokens[$open_bracket]['code'] === T_OPEN_PARENTHESIS && isset($tokens[$open_bracket]['parenthesis_closer']) === true) {
                    $prev = $phpcs_file->find_previous(Tokens::$empty_tokens, $i - 1, null, true);
                    if ($tokens[$prev]['code'] !== T_OBJECT_OPERATOR && $tokens[$prev]['code'] !== T_NULLSAFE_OBJECT_OPERATOR && $tokens[$prev]['code'] !== T_DOUBLE_COLON && $tokens[$prev]['code'] !== T_FUNCTION) {
                        $i = $tokens[$open_bracket]['parenthesis_closer'];
                        continue;
                    }
                }
            }
            //end if
            // Conditional statements are allowed in symbol files as long as the
            // contents is only a symbol definition. So don't count these as effects
            // in this case.
            if (isset($conditions[$tokens[$i]['code']]) === true) {
                if (isset($tokens[$i]['scope_opener']) === false) {
                    // Probably an "else if", so just ignore.
                    continue;
                }
                $result = $this->search_for_conflict($phpcs_file, $tokens[$i]['scope_opener'] + 1, $tokens[$i]['scope_closer'] - 1, $tokens);
                if ($result['symbol'] !== null) {
                    if ($first_symbol === null) {
                        $first_symbol = $result['symbol'];
                    }
                    if ($result['effect'] !== null) {
                        // Found a conflict.
                        $first_effect = $result['effect'];
                        break;
                    }
                }
                if ($first_effect === null) {
                    $first_effect = $result['effect'];
                }
                $i = $tokens[$i]['scope_closer'];
                continue;
            }
            //end if
            if ($first_effect === null) {
                $first_effect = $i;
            }
            if ($first_symbol !== null) {
                // We have a conflict we have to report, so no point continuing.
                break;
            }
        }
        //end for
        return ['symbol' => $first_symbol, 'effect' => $first_effect];
    }
    //end searchForConflict()
}
//end class