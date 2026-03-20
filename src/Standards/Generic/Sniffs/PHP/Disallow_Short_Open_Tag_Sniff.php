<?php

declare (strict_types=1);
/**
 * Makes sure that shorthand PHP open tags are not used.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Generic\Sniffs\PHP;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Disallow_Short_Open_Tag_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        $targets = [T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO];
        $short_open_tags = (bool) ini_get('short_open_tag');
        if ($short_open_tags === false) {
            $targets[] = T_INLINE_HTML;
        }
        return $targets;
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
        if ($token['code'] === T_OPEN_TAG && $token['content'] === '<?') {
            $error = 'Short PHP opening tag used; expected "<?php" but found "%s"';
            $data = [$token['content']];
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'Found', $data);
            if ($fix === true) {
                $correct_opening = '<?php';
                if (isset($tokens[$stack_ptr + 1]) === true && $tokens[$stack_ptr + 1]['code'] !== T_WHITESPACE) {
                    // Avoid creation of invalid open tags like <?phpecho if the original was <?echo .
                    $correct_opening .= ' ';
                }
                $phpcs_file->fixer->replace_token($stack_ptr, $correct_opening);
            }
            $phpcs_file->record_metric($stack_ptr, 'PHP short open tag used', 'yes');
        } else {
            $phpcs_file->record_metric($stack_ptr, 'PHP short open tag used', 'no');
        }
        if ($token['code'] === T_OPEN_TAG_WITH_ECHO) {
            $next_var = $tokens[$phpcs_file->find_next(Tokens::$empty_tokens, $stack_ptr + 1, null, true)];
            $error = 'Short PHP opening tag used with echo; expected "<?php echo %s ..." but found "%s %s ..."';
            $data = [$next_var['content'], $token['content'], $next_var['content']];
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'EchoFound', $data);
            if ($fix === true) {
                if ($tokens[$stack_ptr + 1]['code'] !== T_WHITESPACE) {
                    $phpcs_file->fixer->replace_token($stack_ptr, '<?php echo ');
                } else {
                    $phpcs_file->fixer->replace_token($stack_ptr, '<?php echo');
                }
            }
        }
        if ($token['code'] === T_INLINE_HTML) {
            $content = $token['content'];
            $opener_found = strpos($content, '<?');
            if ($opener_found === false) {
                return;
            }
            $closer_found = false;
            // Inspect current token and subsequent inline HTML token to find a close tag.
            for ($i = $stack_ptr; $i < $phpcs_file->num_tokens; $i++) {
                if ($tokens[$i]['code'] !== T_INLINE_HTML) {
                    break;
                }
                $closer_found = strrpos($tokens[$i]['content'], '?>');
                if ($closer_found !== false) {
                    if ($i !== $stack_ptr) {
                        break;
                    } elseif ($closer_found > $opener_found) {
                        break;
                    } else {
                        $closer_found = false;
                    }
                }
            }
            if ($closer_found !== false) {
                $error = 'Possible use of short open tags detected; found: %s';
                $snippet = $this->get_snippet($content, '<?');
                $data = ['<?' . $snippet];
                $phpcs_file->add_warning($error, $stack_ptr, 'PossibleFound', $data);
                // Skip forward to the token containing the closer.
                if ($i - 1 > $stack_ptr) {
                    return $i;
                }
            }
        }
        //end if
    }
    //end process()
    /**
     * Get a snippet from a HTML token.
     *
     * @param string $content The content of the HTML token.
     * @param string $start   Partial string to use as a starting point for the snippet.
     * @param int    $length  The target length of the snippet to get. Defaults to 40.
     *
     * @return string
     */
    protected function get_snippet($content, $start = '', $length = 40)
    {
        $start_pos = 0;
        if ($start !== '') {
            $start_pos = strpos($content, $start);
            if ($start_pos !== false) {
                $start_pos += strlen($start);
            }
        }
        $snippet = substr($content, $start_pos, $length);
        if (strlen($content) - $start_pos > $length) {
            $snippet .= '...';
        }
        return $snippet;
    }
    //end getSnippet()
}
//end class