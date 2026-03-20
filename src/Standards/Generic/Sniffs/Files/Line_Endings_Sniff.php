<?php

declare (strict_types=1);
/**
 * Checks that end of line characters are correct.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Generic\Sniffs\Files;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Line_Endings_Sniff implements Sniff
{
    /**
     * A list of tokenizers this sniff supports.
     *
     * @var array
     */
    public $supported_tokenizers = ['PHP', 'JS', 'CSS'];
    /**
     * The valid EOL character.
     *
     * @var string
     */
    public $eol_char = '\n';
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
     * Processes this sniff, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token in
     *                                               the stack passed in $tokens.
     *
     * @return int
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $found = $phpcs_file->eol_char;
        $found = str_replace("\n", '\n', $found);
        $found = str_replace("\r", '\r', $found);
        $phpcs_file->record_metric($stack_ptr, 'EOL char', $found);
        if ($found === $this->eol_char) {
            // Ignore the rest of the file.
            return $phpcs_file->num_tokens + 1;
        }
        // Check for single line files without an EOL. This is a very special
        // case and the EOL char is set to \n when this happens.
        if ($found === '\n') {
            $tokens = $phpcs_file->get_tokens();
            $last_token = $phpcs_file->num_tokens - 1;
            if ($tokens[$last_token]['line'] === 1 && $tokens[$last_token]['content'] !== "\n") {
                return;
            }
        }
        $error = 'End of line character is invalid; expected "%s" but found "%s"';
        $expected = $this->eol_char;
        $expected = str_replace("\n", '\n', $expected);
        $expected = str_replace("\r", '\r', $expected);
        $data = [$expected, $found];
        // Errors are always reported on line 1, no matter where the first PHP tag is.
        $fix = $phpcs_file->add_fixable_error($error, 0, 'InvalidEOLChar', $data);
        if ($fix === true) {
            $tokens = $phpcs_file->get_tokens();
            switch ($this->eol_char) {
                case '\n':
                    $eol_char = "\n";
                    break;
                case '\r':
                    $eol_char = "\r";
                    break;
                case '\r\n':
                    $eol_char = "\r\n";
                    break;
                default:
                    $eol_char = $this->eol_char;
                    break;
            }
            for ($i = 0; $i < $phpcs_file->num_tokens; $i++) {
                if (isset($tokens[$i + 1]) === true && $tokens[$i + 1]['line'] <= $tokens[$i]['line']) {
                    continue;
                }
                // Token is the last on a line.
                if (isset($tokens[$i]['orig_content']) === true) {
                    $token_content = $tokens[$i]['orig_content'];
                } else {
                    $token_content = $tokens[$i]['content'];
                }
                if ($token_content === '') {
                    // Special case for JS/CSS close tag.
                    continue;
                }
                $new_content = rtrim($token_content, "\r\n");
                $new_content .= $eol_char;
                if ($token_content !== $new_content) {
                    $phpcs_file->fixer->replace_token($i, $new_content);
                }
            }
            //end for
        }
        //end if
        // Ignore the rest of the file.
        return $phpcs_file->num_tokens + 1;
    }
    //end process()
}
//end class