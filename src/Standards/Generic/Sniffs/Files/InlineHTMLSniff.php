<?php

declare (strict_types=1);
/**
 * Ensures the whole file is PHP only, with no whitespace or inline HTML.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Generic\Sniffs\Files;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Inline_Html_Sniff implements Sniff
{
    /**
     * List of supported BOM definitions.
     *
     * Use encoding names as keys and hex BOM representations as values.
     *
     * @var array
     */
    protected $bom_definitions = ['UTF-8' => 'efbbbf', 'UTF-16 (BE)' => 'feff', 'UTF-16 (LE)' => 'fffe'];
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_INLINE_HTML];
    }
    //end register()
    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token in
     *                                               the stack passed in $tokens.
     *
     * @return int|null
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        // Allow a byte-order mark.
        $tokens = $phpcs_file->get_tokens();
        foreach ($this->bom_definitions as $expected_bom_hex) {
            $bom_byte_length = strlen($expected_bom_hex) / 2;
            $html_bom_hex = bin2hex(substr($tokens[0]['content'], 0, $bom_byte_length));
            if ($html_bom_hex === $expected_bom_hex && strlen($tokens[0]['content']) === $bom_byte_length) {
                return;
            }
        }
        // Ignore shebang lines.
        $tokens = $phpcs_file->get_tokens();
        if (substr($tokens[$stack_ptr]['content'], 0, 2) === '#!') {
            return;
        }
        $error = 'PHP files must only contain PHP code';
        $phpcs_file->add_error($error, $stack_ptr, 'Found');
        return $phpcs_file->num_tokens;
    }
    //end process()
}
//end class