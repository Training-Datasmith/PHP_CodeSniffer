<?php

declare (strict_types=1);
/**
 * Check for duplicate class definitions that can be merged into one.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\CSS;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Duplicate_Class_Definition_Sniff implements Sniff
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
        return [T_OPEN_TAG];
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
        // Find the content of each class definition name.
        $class_names = [];
        $next = $phpcs_file->find_next(T_OPEN_CURLY_BRACKET, $stack_ptr + 1);
        if ($next === false) {
            // No class definitions in the file.
            return;
        }
        // Save the class names in a "scope",
        // to prevent false positives with @media blocks.
        $scope = 'main';
        $find = [T_CLOSE_CURLY_BRACKET, T_OPEN_CURLY_BRACKET, T_OPEN_TAG];
        while ($next !== false) {
            $prev = $phpcs_file->find_previous($find, $next - 1);
            // Check if an inner block was closed.
            $before_prev = $phpcs_file->find_previous(Tokens::$empty_tokens, $prev - 1, null, true);
            if ($before_prev !== false && $tokens[$before_prev]['code'] === T_CLOSE_CURLY_BRACKET) {
                $scope = 'main';
            }
            // Create a sorted name for the class so we can compare classes
            // even when the individual names are all over the place.
            $name = '';
            for ($i = $prev + 1; $i < $next; $i++) {
                $name .= $tokens[$i]['content'];
            }
            $name = trim($name);
            $name = str_replace("\n", ' ', $name);
            $name = preg_replace('|[\s]+|', ' ', $name);
            $name = preg_replace('|\s*/\*.*\*/\s*|', '', $name);
            $name = str_replace(', ', ',', $name);
            $names = explode(',', $name);
            sort($names);
            $name = implode(',', $names);
            if ($name[0] === '@') {
                // Media block has its own "scope".
                $scope = $name;
            } elseif (isset($class_names[$scope][$name]) === true) {
                $first = $class_names[$scope][$name];
                $error = 'Duplicate class definition found; first defined on line %s';
                $data = [$tokens[$first]['line']];
                $phpcs_file->add_error($error, $next, 'Found', $data);
            } else {
                $class_names[$scope][$name] = $next;
            }
            $next = $phpcs_file->find_next(T_OPEN_CURLY_BRACKET, $next + 1);
        }
        //end while
    }
    //end process()
}
//end class