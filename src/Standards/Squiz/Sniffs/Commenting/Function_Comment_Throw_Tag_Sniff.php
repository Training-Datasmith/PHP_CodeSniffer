<?php

declare (strict_types=1);
/**
 * Verifies that a @throws tag exists for each exception type a function throws.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Squiz\Sniffs\Commenting;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class Function_Comment_Throw_Tag_Sniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_FUNCTION];
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
        if (isset($tokens[$stack_ptr]['scope_closer']) === false) {
            // Abstract or incomplete.
            return;
        }
        $find = Tokens::$method_prefixes;
        $find[] = T_WHITESPACE;
        $comment_end = $phpcs_file->find_previous($find, $stack_ptr - 1, null, true);
        if ($tokens[$comment_end]['code'] !== T_DOC_COMMENT_CLOSE_TAG) {
            // Function doesn't have a doc comment or is using the wrong type of comment.
            return;
        }
        $stack_ptr_end = $tokens[$stack_ptr]['scope_closer'];
        // Find all the exception type tokens within the current scope.
        $thrown_exceptions = [];
        $curr_pos = $stack_ptr;
        $found_throws = false;
        $unknown_count = 0;
        do {
            $curr_pos = $phpcs_file->find_next([T_THROW, T_ANON_CLASS, T_CLOSURE], $curr_pos + 1, $stack_ptr_end);
            if ($curr_pos === false) {
                break;
            }
            if ($tokens[$curr_pos]['code'] !== T_THROW) {
                $curr_pos = $tokens[$curr_pos]['scope_closer'];
                continue;
            }
            $found_throws = true;
            /*
                If we can't find a NEW, we are probably throwing
                a variable or calling a method.
            
                If we're throwing a variable, and it's the same variable as the
                exception container from the nearest 'catch' block, we take that exception
                as it is likely to be a re-throw.
            
                If we can't find a matching catch block, or the variable name
                is different, it's probably a different variable, so we ignore it,
                but they still need to provide at least one @throws tag, even through we
                don't know the exception class.
            */
            $next_token = $phpcs_file->find_next(T_WHITESPACE, $curr_pos + 1, null, true);
            if ($tokens[$next_token]['code'] === T_NEW || $tokens[$next_token]['code'] === T_NS_SEPARATOR || $tokens[$next_token]['code'] === T_STRING) {
                if ($tokens[$next_token]['code'] === T_NEW) {
                    $curr_exception = $phpcs_file->find_next([T_NS_SEPARATOR, T_STRING], $curr_pos, $stack_ptr_end, false, null, true);
                } else {
                    $curr_exception = $next_token;
                }
                if ($curr_exception !== false) {
                    $end_exception = $phpcs_file->find_next([T_NS_SEPARATOR, T_STRING], $curr_exception + 1, $stack_ptr_end, true, null, true);
                    if ($end_exception === false) {
                        $thrown_exceptions[] = $tokens[$curr_exception]['content'];
                    } else {
                        $thrown_exceptions[] = $phpcs_file->get_tokens_as_string($curr_exception, $end_exception - $curr_exception);
                    }
                }
                //end if
            } elseif ($tokens[$next_token]['code'] === T_VARIABLE) {
                // Find the nearest catch block in this scope and, if the caught var
                // matches our re-thrown var, use the exception types being caught as
                // exception types that are being thrown as well.
                $catch = $phpcs_file->find_previous(T_CATCH, $curr_pos, $tokens[$stack_ptr]['scope_opener'], false, null, false);
                if ($catch !== false) {
                    $thrown_var = $phpcs_file->find_previous(T_VARIABLE, $tokens[$catch]['parenthesis_closer'] - 1, $tokens[$catch]['parenthesis_opener']);
                    if ($tokens[$thrown_var]['content'] === $tokens[$next_token]['content']) {
                        $exceptions = explode('|', $phpcs_file->get_tokens_as_string($tokens[$catch]['parenthesis_opener'] + 1, $thrown_var - $tokens[$catch]['parenthesis_opener'] - 1));
                        foreach ($exceptions as $exception) {
                            $thrown_exceptions[] = trim($exception);
                        }
                    }
                }
            } else {
                ++$unknown_count;
            }
            //end if
        } while ($curr_pos < $stack_ptr_end && $curr_pos !== false);
        if ($found_throws === false) {
            return;
        }
        // Only need one @throws tag for each type of exception thrown.
        $thrown_exceptions = array_unique($thrown_exceptions);
        $throw_tags = [];
        $comment_start = $tokens[$comment_end]['comment_opener'];
        foreach ($tokens[$comment_start]['comment_tags'] as $tag) {
            if ($tokens[$tag]['content'] !== '@throws') {
                continue;
            }
            if ($tokens[$tag + 2]['code'] === T_DOC_COMMENT_STRING) {
                $exception = $tokens[$tag + 2]['content'];
                $space = strpos($exception, ' ');
                if ($space !== false) {
                    $exception = substr($exception, 0, $space);
                }
                $throw_tags[$exception] = true;
            }
        }
        if (empty($throw_tags) === true) {
            $error = 'Missing @throws tag in function comment';
            $phpcs_file->add_error($error, $comment_end, 'Missing');
            return;
        }
        if (empty($thrown_exceptions) === true) {
            // If token count is zero, it means that only variables are being
            // thrown, so we need at least one @throws tag (checked above).
            // Nothing more to do.
            return;
        }
        // Make sure @throws tag count matches thrown count.
        $thrown_count = count($thrown_exceptions) + $unknown_count;
        $tag_count = count($throw_tags);
        if ($thrown_count !== $tag_count) {
            $error = 'Expected %s @throws tag(s) in function comment; %s found';
            $data = [$thrown_count, $tag_count];
            $phpcs_file->add_error($error, $comment_end, 'WrongNumber', $data);
            return;
        }
        foreach ($thrown_exceptions as $throw) {
            if (isset($throw_tags[$throw]) === true) {
                continue;
            }
            foreach ($throw_tags as $tag => $ignore) {
                if (strrpos($tag, $throw) === strlen($tag) - strlen($throw)) {
                    continue 2;
                }
            }
            $error = 'Missing @throws tag for "%s" exception';
            $data = [$throw];
            $phpcs_file->add_error($error, $comment_end, 'Missing', $data);
        }
    }
    //end process()
}
//end class