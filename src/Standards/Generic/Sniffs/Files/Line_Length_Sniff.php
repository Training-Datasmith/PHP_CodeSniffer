<?php

declare (strict_types=1);
/**
 * Checks the length of all lines in a file.
 *
 * Checks all lines in the file, and throws warnings if they are over 80
 * characters in length and errors if they are over 100. Both these
 * figures can be changed in a ruleset.xml file.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Generic\Sniffs\Files;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Line_Length_Sniff implements Sniff
{
    /**
     * The limit that the length of a line should not exceed.
     *
     * @var integer
     */
    public $line_limit = 80;
    /**
     * The limit that the length of a line must not exceed.
     *
     * Set to zero (0) to disable.
     *
     * @var integer
     */
    public $absolute_line_limit = 100;
    /**
     * Whether or not to ignore trailing comments.
     *
     * This has the effect of also ignoring all lines
     * that only contain comments.
     *
     * @var boolean
     */
    public $ignore_comments = false;
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
     * Processes this test, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token in
     *                                               the stack passed in $tokens.
     *
     * @return int
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        for ($i = 1; $i < $phpcs_file->num_tokens; $i++) {
            if ($tokens[$i]['column'] === 1) {
                $this->check_line_length($phpcs_file, $tokens, $i);
            }
        }
        $this->check_line_length($phpcs_file, $tokens, $i);
        // Ignore the rest of the file.
        return $phpcs_file->num_tokens + 1;
    }
    //end process()
    /**
     * Checks if a line is too long.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param array                       $tokens    The token stack.
     * @param int                         $stackPtr  The first token on the next line.
     *
     * @return void
     */
    protected function check_line_length($phpcs_file, array $tokens, $stack_ptr)
    {
        // The passed token is the first on the line.
        $stack_ptr--;
        if ($tokens[$stack_ptr]['column'] === 1 && $tokens[$stack_ptr]['length'] === 0) {
            // Blank line.
            return;
        }
        if ($tokens[$stack_ptr]['column'] !== 1 && $tokens[$stack_ptr]['content'] === $phpcs_file->eol_char) {
            $stack_ptr--;
        }
        $only_comment = false;
        if (isset(Tokens::$comment_tokens[$tokens[$stack_ptr]['code']]) === true) {
            $prev_non_white_space = $phpcs_file->find_previous(Tokens::$empty_tokens, $stack_ptr - 1, null, true);
            if ($tokens[$stack_ptr]['line'] !== $tokens[$prev_non_white_space]['line']) {
                $only_comment = true;
            }
        }
        if ($only_comment === true && isset(Tokens::$phpcs_comment_tokens[$tokens[$stack_ptr]['code']]) === true) {
            // Ignore PHPCS annotation comments that are on a line by themselves.
            return;
        }
        $line_length = $tokens[$stack_ptr]['column'] + $tokens[$stack_ptr]['length'] - 1;
        if ($this->ignore_comments === true && isset(Tokens::$comment_tokens[$tokens[$stack_ptr]['code']]) === true) {
            // Trailing comments are being ignored in line length calculations.
            if ($only_comment === true) {
                // The comment is the only thing on the line, so no need to check length.
                return;
            }
            $line_length -= $tokens[$stack_ptr]['length'];
        }
        // Record metrics for common line length groupings.
        if ($line_length <= 80) {
            $phpcs_file->record_metric($stack_ptr, 'Line length', '80 or less');
        } elseif ($line_length <= 120) {
            $phpcs_file->record_metric($stack_ptr, 'Line length', '81-120');
        } elseif ($line_length <= 150) {
            $phpcs_file->record_metric($stack_ptr, 'Line length', '121-150');
        } else {
            $phpcs_file->record_metric($stack_ptr, 'Line length', '151 or more');
        }
        if ($only_comment === true) {
            // If this is a long comment, check if it can be broken up onto multiple lines.
            // Some comments contain unbreakable strings like URLs and so it makes sense
            // to ignore the line length in these cases if the URL would be longer than the max
            // line length once you indent it to the correct level.
            if ($line_length > $this->line_limit) {
                $old_length = strlen($tokens[$stack_ptr]['content']);
                $new_length = strlen(ltrim($tokens[$stack_ptr]['content'], "/#\t "));
                $indent = $tokens[$stack_ptr]['column'] - 1 + ($old_length - $new_length);
                $non_breaking_length = $tokens[$stack_ptr]['length'];
                $space = strrpos($tokens[$stack_ptr]['content'], ' ');
                if ($space !== false) {
                    $non_breaking_length -= $space + 1;
                }
                if ($non_breaking_length + $indent > $this->line_limit) {
                    return;
                }
            }
        }
        //end if
        if ($this->absolute_line_limit > 0 && $line_length > $this->absolute_line_limit) {
            $data = [$this->absolute_line_limit, $line_length];
            $error = 'Line exceeds maximum limit of %s characters; contains %s characters';
            $phpcs_file->add_error($error, $stack_ptr, 'MaxExceeded', $data);
        } elseif ($line_length > $this->line_limit) {
            $data = [$this->line_limit, $line_length];
            $warning = 'Line exceeds %s characters; contains %s characters';
            $phpcs_file->add_warning($warning, $stack_ptr, 'TooLong', $data);
        }
    }
    //end checkLineLength()
}
//end class