<?php

declare (strict_types=1);
/**
 * Checks the format of the file header.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2019 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\PSR12\Sniffs\Files;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Tokens;
class File_Header_Sniff implements Sniff
{
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
     * Processes this sniff when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current
     *                                               token in the stack.
     *
     * @return int|null
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        $possible_headers = [];
        $search_for = Tokens::$oo_scope_tokens;
        $search_for[T_OPEN_TAG] = T_OPEN_TAG;
        $open_tag = $stack_ptr;
        do {
            $header_lines = $this->get_header_lines($phpcs_file, $open_tag);
            if (empty($header_lines) === true && $open_tag === $stack_ptr) {
                // No content in the file.
                return;
            }
            $possible_headers[$open_tag] = $header_lines;
            if (count($header_lines) > 1) {
                break;
            }
            $next = $phpcs_file->find_next($search_for, $open_tag + 1);
            if (isset(Tokens::$oo_scope_tokens[$tokens[$next]['code']]) === true) {
                // Once we find an OO token, the file content has
                // definitely started.
                break;
            }
            $open_tag = $next;
        } while ($open_tag !== false);
        if ($open_tag === false) {
            // We never found a proper file header.
            // If the file has multiple PHP open tags, we know
            // that it must be a mix of PHP and HTML (or similar)
            // so the header rules do not apply.
            if (count($possible_headers) > 1) {
                return $phpcs_file->num_tokens;
            }
            // There is only one possible header.
            // If it is the first content in the file, it technically
            // serves as the file header, and the open tag needs to
            // have a newline after it. Otherwise, ignore it.
            if ($stack_ptr > 0) {
                return $phpcs_file->num_tokens;
            }
            $open_tag = $stack_ptr;
        } elseif (count($possible_headers) > 1) {
            // There are other PHP blocks before the file header.
            $error = 'The file header must be the first content in the file';
            $phpcs_file->add_error($error, $open_tag, 'HeaderPosition');
        } else if ($open_tag !== 0) {
            // Allow for hashbang lines.
            $hashbang = false;
            if ($tokens[$open_tag - 1]['code'] === T_INLINE_HTML) {
                $content = trim($tokens[$open_tag - 1]['content']);
                if (substr($content, 0, 2) === '#!') {
                    $hashbang = true;
                }
            }
            if ($hashbang === false) {
                $error = 'The file header must be the first content in the file';
                $phpcs_file->add_error($error, $open_tag, 'HeaderPosition');
            }
        }
        //end if
        $this->process_header_lines($phpcs_file, $possible_headers[$open_tag]);
        return $phpcs_file->num_tokens;
    }
    //end process()
    /**
     * Gather information about the statements inside a possible file header.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current
     *                                               token in the stack.
     *
     * @return array
     */
    public function get_header_lines(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        $next = $phpcs_file->find_next(T_WHITESPACE, $stack_ptr + 1, null, true);
        if ($next === false) {
            return [];
        }
        $header_lines = [];
        $header_lines[] = ['type' => 'tag', 'start' => $stack_ptr, 'end' => $stack_ptr];
        $found_docblock = false;
        $comment_openers = Tokens::$scope_openers;
        unset($comment_openers[T_NAMESPACE]);
        unset($comment_openers[T_DECLARE]);
        unset($comment_openers[T_USE]);
        unset($comment_openers[T_IF]);
        unset($comment_openers[T_WHILE]);
        unset($comment_openers[T_FOR]);
        unset($comment_openers[T_FOREACH]);
        unset($comment_openers[T_DO]);
        unset($comment_openers[T_TRY]);
        do {
            switch ($tokens[$next]['code']) {
                case T_DOC_COMMENT_OPEN_TAG:
                    if ($found_docblock === true) {
                        // Found a second docblock, so start of code.
                        break 2;
                    }
                    // Make sure this is not a code-level docblock.
                    $end = $tokens[$next]['comment_closer'];
                    for ($doc_token = $end + 1; $doc_token < $phpcs_file->num_tokens; $doc_token++) {
                        if (isset(Tokens::$empty_tokens[$tokens[$doc_token]['code']]) === true) {
                            continue;
                        }
                        if ($tokens[$doc_token]['code'] === T_ATTRIBUTE && isset($tokens[$doc_token]['attribute_closer']) === true) {
                            $doc_token = $tokens[$doc_token]['attribute_closer'];
                            continue;
                        }
                        break;
                    }
                    if ($doc_token === $phpcs_file->num_tokens) {
                        $doc_token--;
                    }
                    if (isset($comment_openers[$tokens[$doc_token]['code']]) === false && isset(Tokens::$method_prefixes[$tokens[$doc_token]['code']]) === false && $tokens[$doc_token]['code'] !== T_READONLY) {
                        // Check for an @var annotation.
                        $annotation = false;
                        for ($i = $next; $i < $end; $i++) {
                            if ($tokens[$i]['code'] === T_DOC_COMMENT_TAG && strtolower($tokens[$i]['content']) === '@var') {
                                $annotation = true;
                                break;
                            }
                        }
                        if ($annotation === false) {
                            $found_docblock = true;
                            $header_lines[] = ['type' => 'docblock', 'start' => $next, 'end' => $end];
                        }
                    }
                    //end if
                    $next = $end;
                    break;
                case T_DECLARE:
                case T_NAMESPACE:
                    if (isset($tokens[$next]['scope_opener']) === true) {
                        // If this statement is using bracketed syntax, it doesn't
                        // apply to the entire files and so is not part of header.
                        // The header has now ended and the main code block begins.
                        break 2;
                    }
                    $end = $phpcs_file->find_end_of_statement($next);
                    $header_lines[] = ['type' => substr(strtolower($tokens[$next]['type']), 2), 'start' => $next, 'end' => $end];
                    $next = $end;
                    break;
                case T_USE:
                    $type = 'use';
                    $use_type = $phpcs_file->find_next(Tokens::$empty_tokens, $next + 1, null, true);
                    if ($use_type !== false && $tokens[$use_type]['code'] === T_STRING) {
                        $content = strtolower($tokens[$use_type]['content']);
                        if ($content === 'function' || $content === 'const') {
                            $type .= ' ' . $content;
                        }
                    }
                    $end = $phpcs_file->find_end_of_statement($next);
                    $header_lines[] = ['type' => $type, 'start' => $next, 'end' => $end];
                    $next = $end;
                    break;
                default:
                    // Skip comments as PSR-12 doesn't say if these are allowed or not.
                    if (isset(Tokens::$comment_tokens[$tokens[$next]['code']]) === true) {
                        $next = $phpcs_file->find_next(Tokens::$comment_tokens, $next + 1, null, true);
                        if ($next === false) {
                            // We reached the end of the file.
                            break 2;
                        }
                        $next--;
                        break;
                    }
                    // We found the start of the main code block.
                    break 2;
            }
            //end switch
            $next = $phpcs_file->find_next(T_WHITESPACE, $next + 1, null, true);
        } while ($next !== false);
        return $header_lines;
    }
    //end getHeaderLines()
    /**
     * Check the spacing and grouping of the statements inside each header block.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile   The file being scanned.
     * @param array                       $headerLines Header information, as sourced
     *                                                 from getHeaderLines().
     *
     * @return int|null
     */
    public function process_header_lines(File $phpcs_file, array $header_lines)
    {
        $tokens = $phpcs_file->get_tokens();
        $found = [];
        foreach ($header_lines as $i => $line) {
            if (isset($header_lines[$i + 1]) === false || $header_lines[$i + 1]['type'] !== $line['type']) {
                // We're at the end of the current header block.
                // Make sure there is a single blank line after
                // this block.
                $next = $phpcs_file->find_next(T_WHITESPACE, $line['end'] + 1, null, true);
                if ($next !== false && $tokens[$next]['line'] !== $tokens[$line['end']]['line'] + 2) {
                    $error = 'Header blocks must be separated by a single blank line';
                    $fix = $phpcs_file->add_fixable_error($error, $line['end'], 'SpacingAfterBlock');
                    if ($fix === true) {
                        if ($tokens[$next]['line'] === $tokens[$line['end']]['line']) {
                            $phpcs_file->fixer->add_content_before($next, $phpcs_file->eol_char . $phpcs_file->eol_char);
                        } elseif ($tokens[$next]['line'] === $tokens[$line['end']]['line'] + 1) {
                            $phpcs_file->fixer->add_newline($line['end']);
                        } else {
                            $phpcs_file->fixer->begin_changeset();
                            for ($i = $line['end'] + 1; $i < $next; $i++) {
                                if ($tokens[$i]['line'] === $tokens[$line['end']]['line'] + 2) {
                                    break;
                                }
                                $phpcs_file->fixer->replace_token($i, '');
                            }
                            $phpcs_file->fixer->end_changeset();
                        }
                    }
                    //end if
                }
                //end if
                // Make sure we haven't seen this next block before.
                if (isset($header_lines[$i + 1]) === true && isset($found[$header_lines[$i + 1]['type']]) === true) {
                    $error = 'Similar statements must be grouped together inside header blocks; ';
                    $error .= 'the first "%s" statement was found on line %s';
                    $data = [$header_lines[$i + 1]['type'], $tokens[$found[$header_lines[$i + 1]['type']]['start']]['line']];
                    $phpcs_file->add_error($error, $header_lines[$i + 1]['start'], 'IncorrectGrouping', $data);
                }
            } elseif ($header_lines[$i + 1]['type'] === $line['type']) {
                // Still in the same block, so make sure there is no
                // blank line after this statement.
                $next = $phpcs_file->find_next(T_WHITESPACE, $line['end'] + 1, null, true);
                if ($tokens[$next]['line'] > $tokens[$line['end']]['line'] + 1) {
                    $error = 'Header blocks must not contain blank lines';
                    $fix = $phpcs_file->add_fixable_error($error, $line['end'], 'SpacingInsideBlock');
                    if ($fix === true) {
                        $phpcs_file->fixer->begin_changeset();
                        for ($i = $line['end'] + 1; $i < $next; $i++) {
                            if ($tokens[$i]['line'] === $tokens[$line['end']]['line']) {
                                continue;
                            }
                            if ($tokens[$i]['line'] === $tokens[$next]['line']) {
                                break;
                            }
                            $phpcs_file->fixer->replace_token($i, '');
                        }
                        $phpcs_file->fixer->end_changeset();
                    }
                }
            }
            //end if
            if (isset($found[$line['type']]) === false) {
                $found[$line['type']] = $line;
            }
        }
        //end foreach
        /*
            Next, check that the order of the header blocks
            is correct:
                Opening php tag.
                File-level docblock.
                One or more declare statements.
                The namespace declaration of the file.
                One or more class-based use import statements.
                One or more function-based use import statements.
                One or more constant-based use import statements.
        */
        $block_order = ['tag' => 'opening PHP tag', 'docblock' => 'file-level docblock', 'declare' => 'declare statements', 'namespace' => 'namespace declaration', 'use' => 'class-based use imports', 'use function' => 'function-based use imports', 'use const' => 'constant-based use imports'];
        foreach (array_keys($found) as $type) {
            if ($type === 'tag') {
                // The opening tag is always in the correct spot.
                continue;
            }
            do {
                $ordered_type = next($block_order);
            } while ($ordered_type !== false && key($block_order) !== $type);
            if ($ordered_type === false) {
                // We didn't find the block type in the rest of the
                // ordered array, so it is out of place.
                // Error and reset the array to the correct position
                // so we can check the next block.
                reset($block_order);
                $prev_valid_type = 'tag';
                do {
                    $ordered_type = next($block_order);
                    if (isset($found[key($block_order)]) === true && key($block_order) !== $type) {
                        $prev_valid_type = key($block_order);
                    }
                } while ($ordered_type !== false && key($block_order) !== $type);
                $error = 'The %s must follow the %s in the file header';
                $data = [$block_order[$type], $block_order[$prev_valid_type]];
                $phpcs_file->add_error($error, $found[$type]['start'], 'IncorrectOrder', $data);
            }
            //end if
        }
        //end foreach
    }
    //end processHeaderLines()
}
//end class