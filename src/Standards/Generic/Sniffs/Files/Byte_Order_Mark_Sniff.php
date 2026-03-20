<?php

declare (strict_types=1);
/**
 * A simple sniff for detecting a BOM definition that may corrupt application work.
 *
 * @author    Piotr Karas <office@mediaself.pl>
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2010-2014 mediaSELF Sp. z o.o.
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Generic\Sniffs\Files;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Byte_Order_Mark_Sniff implements Sniff
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
     * Processes this sniff, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token in
     *                                               the stack passed in $tokens.
     *
     * @return void
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        // The BOM will be the very first token in the file.
        if ($stack_ptr !== 0) {
            return;
        }
        $tokens = $phpcs_file->get_tokens();
        foreach ($this->bom_definitions as $bom_name => $expected_bom_hex) {
            $bom_byte_length = strlen($expected_bom_hex) / 2;
            $html_bom_hex = bin2hex(substr($tokens[$stack_ptr]['content'], 0, $bom_byte_length));
            if ($html_bom_hex === $expected_bom_hex) {
                $error_data = [$bom_name];
                $error = 'File contains %s byte order mark, which may corrupt your application';
                $phpcs_file->add_error($error, $stack_ptr, 'Found', $error_data);
                $phpcs_file->record_metric($stack_ptr, 'Using byte order mark', 'yes');
                return;
            }
        }
        $phpcs_file->record_metric($stack_ptr, 'Using byte order mark', 'no');
    }
    //end process()
}
//end class