<?php

declare (strict_types=1);
/**
 * Verifies that no alternative PHP tags are used.
 *
 * If alternative PHP open tags are found, this sniff can fix both the open and close tags.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Generic\Sniffs\PHP;

use Php_code_Sniffer\Config;
use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Disallow_Alternative_Php_Tags_Sniff implements Sniff
{
    /**
     * Whether ASP tags are enabled or not.
     *
     * @var boolean
     */
    private $asp_tags = false;
    /**
     * The current PHP version.
     *
     * @var integer
     */
    private $php_version;
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        if ($this->php_version === null) {
            $this->php_version = Config::get_config_data('php_version');
            if ($this->php_version === null) {
                $this->php_version = PHP_VERSION_ID;
            }
        }
        if ($this->php_version < 70000) {
            $this->asp_tags = (bool) ini_get('asp_tags');
        }
        return [T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO, T_INLINE_HTML];
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
        $open_tag = $tokens[$stack_ptr];
        $content = $open_tag['content'];
        if (trim($content) === '') {
            return;
        }
        if ($open_tag['code'] === T_OPEN_TAG) {
            if ($content === '<%') {
                $error = 'ASP style opening tag used; expected "<?php" but found "%s"';
                $closer = $this->find_closing_tag($phpcs_file, $tokens, $stack_ptr, '%>');
                $error_code = 'ASPOpenTagFound';
            } elseif (strpos($content, '<script ') !== false) {
                $error = 'Script style opening tag used; expected "<?php" but found "%s"';
                $closer = $this->find_closing_tag($phpcs_file, $tokens, $stack_ptr, '</script>');
                $error_code = 'ScriptOpenTagFound';
            }
            if (isset($error, $closer, $error_code) === true) {
                $data = [$content];
                if ($closer === false) {
                    $phpcs_file->add_error($error, $stack_ptr, $error_code, $data);
                } else {
                    $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, $error_code, $data);
                    if ($fix === true) {
                        $this->add_changeset($phpcs_file, $tokens, $stack_ptr, $closer);
                    }
                }
            }
            return;
        }
        //end if
        if ($open_tag['code'] === T_OPEN_TAG_WITH_ECHO && $content === '<%=') {
            $error = 'ASP style opening tag used with echo; expected "<?php echo %s ..." but found "%s %s ..."';
            $next_var = $phpcs_file->find_next(T_WHITESPACE, $stack_ptr + 1, null, true);
            $snippet = $this->get_snippet($tokens[$next_var]['content']);
            $data = [$snippet, $content, $snippet];
            $closer = $this->find_closing_tag($phpcs_file, $tokens, $stack_ptr, '%>');
            if ($closer === false) {
                $phpcs_file->add_error($error, $stack_ptr, 'ASPShortOpenTagFound', $data);
            } else {
                $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'ASPShortOpenTagFound', $data);
                if ($fix === true) {
                    $this->add_changeset($phpcs_file, $tokens, $stack_ptr, $closer, true);
                }
            }
            return;
        }
        //end if
        // Account for incorrect script open tags.
        if ($open_tag['code'] === T_INLINE_HTML && preg_match('`(<script (?:[^>]+)?language=[\'"]?php[\'"]?(?:[^>]+)?>)`i', $content, $match) === 1) {
            $error = 'Script style opening tag used; expected "<?php" but found "%s"';
            $snippet = $this->get_snippet($content, $match[1]);
            $data = [$match[1] . $snippet];
            $phpcs_file->add_error($error, $stack_ptr, 'ScriptOpenTagFound', $data);
            return;
        }
        if ($open_tag['code'] === T_INLINE_HTML && $this->asp_tags === false) {
            if (strpos($content, '<%=') !== false) {
                $error = 'Possible use of ASP style short opening tags detected; found: %s';
                $snippet = $this->get_snippet($content, '<%=');
                $data = ['<%=' . $snippet];
                $phpcs_file->add_warning($error, $stack_ptr, 'MaybeASPShortOpenTagFound', $data);
            } elseif (strpos($content, '<%') !== false) {
                $error = 'Possible use of ASP style opening tags detected; found: %s';
                $snippet = $this->get_snippet($content, '<%');
                $data = ['<%' . $snippet];
                $phpcs_file->add_warning($error, $stack_ptr, 'MaybeASPOpenTagFound', $data);
            }
        }
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
    /**
     * Try and find a matching PHP closing tag.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param array                       $tokens    The token stack.
     * @param int                         $stackPtr  The position of the current token
     *                                               in the stack passed in $tokens.
     * @param string                      $content   The expected content of the closing tag to match the opener.
     *
     * @return int|false Pointer to the position in the stack for the closing tag or false if not found.
     */
    protected function find_closing_tag(File $phpcs_file, array $tokens, $stack_ptr, $content)
    {
        $closer = $phpcs_file->find_next(T_CLOSE_TAG, $stack_ptr + 1);
        if ($closer !== false && $content === trim($tokens[$closer]['content'])) {
            return $closer;
        }
        return false;
    }
    //end findClosingTag()
    /**
     * Add a changeset to replace the alternative PHP tags.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile       The file being scanned.
     * @param array                       $tokens          The token stack.
     * @param int                         $openTagPointer  Stack pointer to the PHP open tag.
     * @param int                         $closeTagPointer Stack pointer to the PHP close tag.
     * @param bool                        $echo            Whether to add 'echo' or not.
     *
     * @return void
     */
    protected function add_changeset(File $phpcs_file, array $tokens, $open_tag_pointer, $close_tag_pointer, $echo = false)
    {
        // Build up the open tag replacement and make sure there's always whitespace behind it.
        $open_replacement = '<?php';
        if ($echo === true) {
            $open_replacement .= ' echo';
        }
        if ($tokens[$open_tag_pointer + 1]['code'] !== T_WHITESPACE) {
            $open_replacement .= ' ';
        }
        // Make sure we don't remove any line breaks after the closing tag.
        $regex = '`' . preg_quote(trim($tokens[$close_tag_pointer]['content'])) . '`';
        $close_replacement = preg_replace($regex, '?>', $tokens[$close_tag_pointer]['content']);
        $phpcs_file->fixer->begin_changeset();
        $phpcs_file->fixer->replace_token($open_tag_pointer, $open_replacement);
        $phpcs_file->fixer->replace_token($close_tag_pointer, $close_replacement);
        $phpcs_file->fixer->end_changeset();
    }
    //end addChangeset()
}
//end class