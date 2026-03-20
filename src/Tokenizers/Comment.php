<?php

declare (strict_types=1);
/**
 * Tokenizes doc block comments.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Tokenizers;

use Php_code_Sniffer\Util;
class Comment
{
    /**
     * Creates an array of tokens when given some PHP code.
     *
     * Starts by using token_get_all() but does a lot of extra processing
     * to insert information about the context of the token.
     *
     * @param string $string   The string to tokenize.
     * @param string $eolChar  The EOL character to use for splitting strings.
     * @param int    $stackPtr The position of the first token in the file.
     *
     * @return array
     */
    public function tokenize_string($string, $eol_char, $stack_ptr)
    {
        if (PHP_CODESNIFFER_VERBOSITY > 1) {
            echo "\t\t*** START COMMENT TOKENIZING ***" . PHP_EOL;
        }
        $tokens = [];
        $num_chars = strlen($string);
        /*
            Doc block comments start with /*, but typically contain an
            extra star when they are used for function and class comments.
        */
        $char = $num_chars - strlen(ltrim($string, '/*'));
        $open_tag = substr($string, 0, $char);
        $string = ltrim($string, '/*');
        $tokens[$stack_ptr] = ['content' => $open_tag, 'code' => T_DOC_COMMENT_OPEN_TAG, 'type' => 'T_DOC_COMMENT_OPEN_TAG', 'comment_tags' => []];
        $open_ptr = $stack_ptr;
        $stack_ptr++;
        if (PHP_CODESNIFFER_VERBOSITY > 1) {
            $content = Util\Common::prepare_for_output($open_tag);
            echo "\t\tCreate comment token: T_DOC_COMMENT_OPEN_TAG => {$content}" . PHP_EOL;
        }
        /*
            Strip off the close tag so it doesn't interfere with any
            of our comment line processing. The token will be added to the
            stack just before we return it.
        */
        $close_tag = ['content' => substr($string, strlen(rtrim($string, '/*'))), 'code' => T_DOC_COMMENT_CLOSE_TAG, 'type' => 'T_DOC_COMMENT_CLOSE_TAG', 'comment_opener' => $open_ptr];
        if ($close_tag['content'] === false) {
            $close_tag['content'] = '';
        }
        $string = rtrim($string, '/*');
        /*
            Process each line of the comment.
        */
        $lines = explode($eol_char, $string);
        $num_lines = count($lines);
        foreach ($lines as $line_num => $string) {
            if ($line_num !== $num_lines - 1) {
                $string .= $eol_char;
            }
            $char = 0;
            $num_chars = strlen($string);
            // We've started a new line, so process the indent.
            $space = $this->collect_whitespace($string, $char, $num_chars);
            if ($space !== null) {
                $tokens[$stack_ptr] = $space;
                $stack_ptr++;
                if (PHP_CODESNIFFER_VERBOSITY > 1) {
                    $content = Util\Common::prepare_for_output($space['content']);
                    echo "\t\tCreate comment token: T_DOC_COMMENT_WHITESPACE => {$content}" . PHP_EOL;
                }
                $char += strlen($space['content']);
                if ($char === $num_chars) {
                    break;
                }
            }
            if ($string === '') {
                continue;
            }
            if ($line_num > 0 && $string[$char] === '*') {
                // This is a function or class doc block line.
                $char++;
                $tokens[$stack_ptr] = ['content' => '*', 'code' => T_DOC_COMMENT_STAR, 'type' => 'T_DOC_COMMENT_STAR'];
                $stack_ptr++;
                if (PHP_CODESNIFFER_VERBOSITY > 1) {
                    echo "\t\tCreate comment token: T_DOC_COMMENT_STAR => *" . PHP_EOL;
                }
            }
            // Now we are ready to process the actual content of the line.
            $line_tokens = $this->process_line($string, $eol_char, $char, $num_chars);
            foreach ($line_tokens as $line_token) {
                $tokens[$stack_ptr] = $line_token;
                if (PHP_CODESNIFFER_VERBOSITY > 1) {
                    $content = Util\Common::prepare_for_output($line_token['content']);
                    $type = $line_token['type'];
                    echo "\t\tCreate comment token: {$type} => {$content}" . PHP_EOL;
                }
                if ($line_token['code'] === T_DOC_COMMENT_TAG) {
                    $tokens[$open_ptr]['comment_tags'][] = $stack_ptr;
                }
                $stack_ptr++;
            }
        }
        //end foreach
        $tokens[$stack_ptr] = $close_tag;
        $tokens[$open_ptr]['comment_closer'] = $stack_ptr;
        if (PHP_CODESNIFFER_VERBOSITY > 1) {
            $content = Util\Common::prepare_for_output($close_tag['content']);
            echo "\t\tCreate comment token: T_DOC_COMMENT_CLOSE_TAG => {$content}" . PHP_EOL;
            echo "\t\t*** END COMMENT TOKENIZING ***" . PHP_EOL;
        }
        return $tokens;
    }
    //end tokenizeString()
    /**
     * Process a single line of a comment.
     *
     * @param string $string  The comment string being tokenized.
     * @param string $eolChar The EOL character to use for splitting strings.
     * @param int    $start   The position in the string to start processing.
     * @param int    $end     The position in the string to end processing.
     *
     * @return array
     */
    private function process_line($string, $eol_char, $start, $end)
    {
        $tokens = [];
        // Collect content padding.
        $space = $this->collect_whitespace($string, $start, $end);
        if ($space !== null) {
            $tokens[] = $space;
            $start += strlen($space['content']);
        }
        if (isset($string[$start]) === false) {
            return $tokens;
        }
        if ($string[$start] === '@') {
            // The content up until the first whitespace is the tag name.
            $matches = [];
            preg_match('/@[^\s]+/', $string, $matches, 0, $start);
            if (isset($matches[0]) === true && substr(strtolower($matches[0]), 0, 7) !== '@phpcs:') {
                $tag_name = $matches[0];
                $start += strlen($tag_name);
                $tokens[] = ['content' => $tag_name, 'code' => T_DOC_COMMENT_TAG, 'type' => 'T_DOC_COMMENT_TAG'];
                // Then there will be some whitespace.
                $space = $this->collect_whitespace($string, $start, $end);
                if ($space !== null) {
                    $tokens[] = $space;
                    $start += strlen($space['content']);
                }
            }
        }
        //end if
        // Process the rest of the line.
        $eol = strpos($string, $eol_char, $start);
        if ($eol === false) {
            $eol = $end;
        }
        if ($eol > $start) {
            $tokens[] = ['content' => substr($string, $start, $eol - $start), 'code' => T_DOC_COMMENT_STRING, 'type' => 'T_DOC_COMMENT_STRING'];
        }
        if ($eol !== $end) {
            $tokens[] = ['content' => substr($string, $eol, strlen($eol_char)), 'code' => T_DOC_COMMENT_WHITESPACE, 'type' => 'T_DOC_COMMENT_WHITESPACE'];
        }
        return $tokens;
    }
    //end processLine()
    /**
     * Collect consecutive whitespace into a single token.
     *
     * @param string $string The comment string being tokenized.
     * @param int    $start  The position in the string to start processing.
     * @param int    $end    The position in the string to end processing.
     *
     * @return array|null
     */
    private function collect_whitespace($string, $start, $end)
    {
        $space = '';
        for ($start; $start < $end; $start++) {
            if ($string[$start] !== ' ' && $string[$start] !== "\t") {
                break;
            }
            $space .= $string[$start];
        }
        if ($space === '') {
            return null;
        }
        return ['content' => $space, 'code' => T_DOC_COMMENT_WHITESPACE, 'type' => 'T_DOC_COMMENT_WHITESPACE'];
    }
    //end collectWhitespace()
}
//end class