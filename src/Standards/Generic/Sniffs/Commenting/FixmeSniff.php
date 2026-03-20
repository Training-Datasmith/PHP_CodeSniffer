<?php

declare (strict_types=1);
/**
 * Warns about FIXME comments.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @author    Sam Graham <php-codesniffer@illusori.co.uk>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Generic\Sniffs\Commenting;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Fixme_Sniff implements Sniff
{
    /**
     * A list of tokenizers this sniff supports.
     *
     * @var array
     */
    public $supported_tokenizers = ['PHP', 'JS'];
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return array_diff(Tokens::$comment_tokens, Tokens::$phpcs_comment_tokens);
    }
    //end register()
    /**
     * Processes this sniff, when one of its tokens is encountered.
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
        $content = $tokens[$stack_ptr]['content'];
        $matches = [];
        preg_match('/(?:\A|[^\p{L}]+)fixme([^\p{L}]+(.*)|\Z)/ui', $content, $matches);
        if (empty($matches) === false) {
            // Clear whitespace and some common characters not required at
            // the end of a fixme message to make the error more informative.
            $type = 'CommentFound';
            $fixme_message = trim($matches[1]);
            $fixme_message = trim($fixme_message, '-:[](). ');
            $error = 'Comment refers to a FIXME task';
            $data = [$fixme_message];
            if ($fixme_message !== '') {
                $type = 'TaskFound';
                $error .= ' "%s"';
            }
            $phpcs_file->add_error($error, $stack_ptr, $type, $data);
        }
    }
    //end process()
}
//end class