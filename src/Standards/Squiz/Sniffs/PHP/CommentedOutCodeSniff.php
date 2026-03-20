<?php

declare (strict_types=1);
/**
 * Warn about commented out code.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\PHP;

use Php_code_Sniffer\Exceptions\Tokenizer_Exception;
use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Commented_Out_Code_Sniff implements Sniff
{
    /**
     * A list of tokenizers this sniff supports.
     *
     * @var array
     */
    public $supported_tokenizers = ['PHP', 'CSS'];
    /**
     * If a comment is more than $maxPercentage% code, a warning will be shown.
     *
     * @var integer
     */
    public $max_percentage = 35;
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_COMMENT];
    }
    //end register()
    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token
     *                                               in the stack passed in $tokens.
     *
     * @return int|void Integer stack pointer to skip forward or void to continue
     *                  normal file processing.
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        // Ignore comments at the end of code blocks.
        if (substr($tokens[$stack_ptr]['content'], 0, 6) === '//end ') {
            return;
        }
        $content = '';
        $last_line_seen = $tokens[$stack_ptr]['line'];
        $comment_style = 'line';
        if (strpos($tokens[$stack_ptr]['content'], '/*') === 0) {
            $comment_style = 'block';
        }
        $last_comment_block_token = $stack_ptr;
        for ($i = $stack_ptr; $i < $phpcs_file->num_tokens; $i++) {
            if (isset(Tokens::$empty_tokens[$tokens[$i]['code']]) === false) {
                break;
            }
            if ($tokens[$i]['code'] === T_WHITESPACE) {
                continue;
            }
            if (isset(Tokens::$phpcs_comment_tokens[$tokens[$i]['code']]) === true) {
                $last_line_seen = $tokens[$i]['line'];
                continue;
            }
            if ($comment_style === 'line' && $last_line_seen + 1 <= $tokens[$i]['line'] && strpos($tokens[$i]['content'], '/*') === 0) {
                // First non-whitespace token on a new line is start of a different style comment.
                break;
            }
            if ($comment_style === 'line' && $last_line_seen + 1 < $tokens[$i]['line']) {
                // Blank line breaks a '//' style comment block.
                break;
            }
            /*
                Trim as much off the comment as possible so we don't
                have additional whitespace tokens or comment tokens
            */
            $token_content = trim($tokens[$i]['content']);
            $break = false;
            if ($comment_style === 'line') {
                if (substr($token_content, 0, 2) === '//') {
                    $token_content = substr($token_content, 2);
                }
                if (substr($token_content, 0, 1) === '#') {
                    $token_content = substr($token_content, 1);
                }
            } else {
                if (substr($token_content, 0, 3) === '/**') {
                    $token_content = substr($token_content, 3);
                }
                if (substr($token_content, 0, 2) === '/*') {
                    $token_content = substr($token_content, 2);
                }
                if (substr($token_content, -2) === '*/') {
                    $token_content = substr($token_content, 0, -2);
                    $break = true;
                }
                if (substr($token_content, 0, 1) === '*') {
                    $token_content = substr($token_content, 1);
                }
            }
            //end if
            $content .= $token_content . $phpcs_file->eol_char;
            $last_line_seen = $tokens[$i]['line'];
            $last_comment_block_token = $i;
            if ($break === true) {
                // Closer of a block comment found.
                break;
            }
        }
        //end for
        // Ignore typical warning suppression annotations from other tools.
        if (preg_match('`^\s*@[A-Za-z()\._-]+\s*$`', $content) === 1) {
            return $last_comment_block_token + 1;
        }
        // Quite a few comments use multiple dashes, equals signs etc
        // to frame comments and licence headers.
        $content = preg_replace('/[-=#*]{2,}/', '-', $content);
        // Random numbers sitting inside the content can throw parse errors
        // for invalid literals in PHP7+, so strip those.
        $content = preg_replace('/\d+/', '', $content);
        $content = trim($content);
        if ($content === '') {
            return $last_comment_block_token + 1;
        }
        if ($phpcs_file->tokenizer_type === 'PHP') {
            $content = '<?php ' . $content . ' ?>';
        }
        // Because we are not really parsing code, the tokenizer can throw all sorts
        // of errors that don't mean anything, so ignore them.
        $old_errors = ini_get('error_reporting');
        ini_set('error_reporting', 0);
        try {
            $tokenizer_class = get_class($phpcs_file->tokenizer);
            $tokenizer = new $tokenizer_class($content, $phpcs_file->config, $phpcs_file->eol_char);
            $string_tokens = $tokenizer->get_tokens();
        } catch (Tokenizer_Exception $e) {
            // We couldn't check the comment, so ignore it.
            ini_set('error_reporting', $old_errors);
            return $last_comment_block_token + 1;
        }
        ini_set('error_reporting', $old_errors);
        $num_tokens = count($string_tokens);
        /*
            We know what the first two and last two tokens should be
            (because we put them there) so ignore this comment if those
            tokens were not parsed correctly. It obviously means this is not
            valid code.
        */
        // First token is always the opening tag.
        if ($string_tokens[0]['code'] !== T_OPEN_TAG) {
            return $last_comment_block_token + 1;
        }
        array_shift($string_tokens);
        --$num_tokens;
        // Last token is always the closing tag, unless something went wrong.
        if (isset($string_tokens[$num_tokens - 1]) === false || $string_tokens[$num_tokens - 1]['code'] !== T_CLOSE_TAG) {
            return $last_comment_block_token + 1;
        }
        array_pop($string_tokens);
        --$num_tokens;
        // Second last token is always whitespace or a comment, depending
        // on the code inside the comment.
        if ($phpcs_file->tokenizer_type === 'PHP') {
            if (isset(Tokens::$empty_tokens[$string_tokens[$num_tokens - 1]['code']]) === false) {
                return $last_comment_block_token + 1;
            }
            if ($string_tokens[$num_tokens - 1]['code'] === T_WHITESPACE) {
                array_pop($string_tokens);
                --$num_tokens;
            }
        }
        $empty_tokens = [T_WHITESPACE => true, T_STRING => true, T_STRING_CONCAT => true, T_ENCAPSED_AND_WHITESPACE => true, T_NONE => true, T_COMMENT => true];
        $empty_tokens += Tokens::$phpcs_comment_tokens;
        $num_comment = 0;
        $num_possible = 0;
        $num_code = 0;
        $num_non_whitespace = 0;
        for ($i = 0; $i < $num_tokens; $i++) {
            if (isset($empty_tokens[$string_tokens[$i]['code']]) === true) {
                // Looks like comment.
                $num_comment++;
            } elseif (isset(Tokens::$comparison_tokens[$string_tokens[$i]['code']]) === true || isset(Tokens::$arithmetic_tokens[$string_tokens[$i]['code']]) === true || $string_tokens[$i]['code'] === T_GOTO_LABEL) {
                // Commented out HTML/XML and other docs contain a lot of these
                // characters, so it is best to not use them directly.
                $num_possible++;
            } else {
                // Looks like code.
                $num_code++;
            }
            if ($string_tokens[$i]['code'] !== T_WHITESPACE) {
                ++$num_non_whitespace;
            }
        }
        // Ignore comments with only two or less non-whitespace tokens.
        // Sample size too small for a reliably determination.
        if ($num_non_whitespace <= 2) {
            return $last_comment_block_token + 1;
        }
        $percent_code = ceil($num_code / $num_tokens * 100);
        if ($percent_code > $this->max_percentage) {
            // Just in case.
            $percent_code = min(100, $percent_code);
            $error = 'This comment is %s%% valid code; is this commented out code?';
            $data = [$percent_code];
            $phpcs_file->add_warning($error, $stack_ptr, 'Found', $data);
        }
        return $last_comment_block_token + 1;
    }
    //end process()
}
//end class