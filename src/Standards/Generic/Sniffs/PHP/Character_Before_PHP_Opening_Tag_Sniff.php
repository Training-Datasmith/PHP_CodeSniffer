<?php

declare (strict_types=1);
/**
 * Checks that the opening PHP tag is the first content in a file.
 *
 * @author    Andy Grunwald <andygrunwald@gmail.com>
 * @copyright 2010-2014 Andy Grunwald
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Generic\Sniffs\PHP;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Character_Before_Php_Opening_Tag_Sniff implements Sniff
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
        return [T_OPEN_TAG];
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
        $expected = 0;
        if ($stack_ptr > 0) {
            // Allow a byte-order mark.
            $tokens = $phpcs_file->get_tokens();
            foreach ($this->bom_definitions as $expected_bom_hex) {
                $bom_byte_length = strlen($expected_bom_hex) / 2;
                $html_bom_hex = bin2hex(substr($tokens[0]['content'], 0, $bom_byte_length));
                if ($html_bom_hex === $expected_bom_hex) {
                    $expected++;
                    break;
                }
            }
            // Allow a shebang line.
            if (substr($tokens[0]['content'], 0, 2) === '#!') {
                $expected++;
            }
        }
        if ($stack_ptr !== $expected) {
            $error = 'The opening PHP tag must be the first content in the file';
            $phpcs_file->add_error($error, $stack_ptr, 'Found');
        }
        // Skip the rest of the file so we don't pick up additional
        // open tags, typically embedded in HTML.
        return $phpcs_file->num_tokens;
    }
    //end process()
}
//end class