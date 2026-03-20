<?php

declare (strict_types=1);
/**
 * Ensures doc blocks follow basic formatting.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\Generic\Sniffs\Commenting;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
class Doc_Comment_Sniff implements Sniff
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
        return [T_DOC_COMMENT_OPEN_TAG];
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
        if (isset($tokens[$stack_ptr]['comment_closer']) === false || $tokens[$tokens[$stack_ptr]['comment_closer']]['content'] === '' && $tokens[$stack_ptr]['comment_closer'] === $phpcs_file->num_tokens - 1) {
            // Don't process an unfinished comment during live coding.
            return;
        }
        $comment_start = $stack_ptr;
        $comment_end = $tokens[$stack_ptr]['comment_closer'];
        $empty = [T_DOC_COMMENT_WHITESPACE, T_DOC_COMMENT_STAR];
        $short = $phpcs_file->find_next($empty, $stack_ptr + 1, $comment_end, true);
        if ($short === false) {
            // No content at all.
            $error = 'Doc comment is empty';
            $phpcs_file->add_error($error, $stack_ptr, 'Empty');
            return;
        }
        // The first line of the comment should just be the /** code.
        if ($tokens[$short]['line'] === $tokens[$stack_ptr]['line']) {
            $error = 'The open comment tag must be the only content on the line';
            $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'ContentAfterOpen');
            if ($fix === true) {
                $phpcs_file->fixer->begin_changeset();
                $phpcs_file->fixer->add_newline($stack_ptr);
                $phpcs_file->fixer->add_content_before($short, '* ');
                $phpcs_file->fixer->end_changeset();
            }
        }
        // The last line of the comment should just be the */ code.
        $prev = $phpcs_file->find_previous($empty, $comment_end - 1, $stack_ptr, true);
        if ($tokens[$prev]['line'] === $tokens[$comment_end]['line']) {
            $error = 'The close comment tag must be the only content on the line';
            $fix = $phpcs_file->add_fixable_error($error, $comment_end, 'ContentBeforeClose');
            if ($fix === true) {
                $phpcs_file->fixer->add_newline_before($comment_end);
            }
        }
        // Check for additional blank lines at the end of the comment.
        if ($tokens[$prev]['line'] < $tokens[$comment_end]['line'] - 1) {
            $error = 'Additional blank lines found at end of doc comment';
            $fix = $phpcs_file->add_fixable_error($error, $comment_end, 'SpacingAfter');
            if ($fix === true) {
                $phpcs_file->fixer->begin_changeset();
                for ($i = $prev + 1; $i < $comment_end; $i++) {
                    if ($tokens[$i + 1]['line'] === $tokens[$comment_end]['line']) {
                        break;
                    }
                    $phpcs_file->fixer->replace_token($i, '');
                }
                $phpcs_file->fixer->end_changeset();
            }
        }
        // Check for a comment description.
        if ($tokens[$short]['code'] !== T_DOC_COMMENT_STRING) {
            $error = 'Missing short description in doc comment';
            $phpcs_file->add_error($error, $stack_ptr, 'MissingShort');
        } else {
            // No extra newline before short description.
            if ($tokens[$short]['line'] !== $tokens[$stack_ptr]['line'] + 1) {
                $error = 'Doc comment short description must be on the first line';
                $fix = $phpcs_file->add_fixable_error($error, $short, 'SpacingBeforeShort');
                if ($fix === true) {
                    $phpcs_file->fixer->begin_changeset();
                    for ($i = $stack_ptr; $i < $short; $i++) {
                        if ($tokens[$i]['line'] === $tokens[$stack_ptr]['line']) {
                            continue;
                        }
                        if ($tokens[$i]['line'] === $tokens[$short]['line']) {
                            break;
                        }
                        $phpcs_file->fixer->replace_token($i, '');
                    }
                    $phpcs_file->fixer->end_changeset();
                }
            }
            // Account for the fact that a short description might cover
            // multiple lines.
            $short_content = $tokens[$short]['content'];
            $short_end = $short;
            for ($i = $short + 1; $i < $comment_end; $i++) {
                if ($tokens[$i]['code'] === T_DOC_COMMENT_STRING) {
                    if ($tokens[$i]['line'] === $tokens[$short_end]['line'] + 1) {
                        $short_content .= $tokens[$i]['content'];
                        $short_end = $i;
                    } else {
                        break;
                    }
                }
            }
            if (preg_match('/^\p{Ll}/u', $short_content) === 1) {
                $error = 'Doc comment short description must start with a capital letter';
                $phpcs_file->add_error($error, $short, 'ShortNotCapital');
            }
            $long = $phpcs_file->find_next($empty, $short_end + 1, $comment_end - 1, true);
            if ($long !== false && $tokens[$long]['code'] === T_DOC_COMMENT_STRING) {
                if ($tokens[$long]['line'] !== $tokens[$short_end]['line'] + 2) {
                    $error = 'There must be exactly one blank line between descriptions in a doc comment';
                    $fix = $phpcs_file->add_fixable_error($error, $long, 'SpacingBetween');
                    if ($fix === true) {
                        $phpcs_file->fixer->begin_changeset();
                        for ($i = $short_end + 1; $i < $long; $i++) {
                            if ($tokens[$i]['line'] === $tokens[$short_end]['line']) {
                                continue;
                            }
                            if ($tokens[$i]['line'] === $tokens[$long]['line'] - 1) {
                                break;
                            }
                            $phpcs_file->fixer->replace_token($i, '');
                        }
                        $phpcs_file->fixer->end_changeset();
                    }
                }
                if (preg_match('/^\p{Ll}/u', $tokens[$long]['content']) === 1) {
                    $error = 'Doc comment long description must start with a capital letter';
                    $phpcs_file->add_error($error, $long, 'LongNotCapital');
                }
            }
            //end if
        }
        //end if
        if (empty($tokens[$comment_start]['comment_tags']) === true) {
            // No tags in the comment.
            return;
        }
        $first_tag = $tokens[$comment_start]['comment_tags'][0];
        $prev = $phpcs_file->find_previous($empty, $first_tag - 1, $stack_ptr, true);
        if ($tokens[$first_tag]['line'] !== $tokens[$prev]['line'] + 2 && $tokens[$prev]['code'] !== T_DOC_COMMENT_OPEN_TAG) {
            $error = 'There must be exactly one blank line before the tags in a doc comment';
            $fix = $phpcs_file->add_fixable_error($error, $first_tag, 'SpacingBeforeTags');
            if ($fix === true) {
                $phpcs_file->fixer->begin_changeset();
                for ($i = $prev + 1; $i < $first_tag; $i++) {
                    if ($tokens[$i]['line'] === $tokens[$first_tag]['line']) {
                        break;
                    }
                    $phpcs_file->fixer->replace_token($i, '');
                }
                $indent = str_repeat(' ', $tokens[$stack_ptr]['column']);
                $phpcs_file->fixer->add_content($prev, $phpcs_file->eol_char . $indent . '*' . $phpcs_file->eol_char);
                $phpcs_file->fixer->end_changeset();
            }
        }
        // Break out the tags into groups and check alignment within each.
        // A tag group is one where there are no blank lines between tags.
        // The param tag group is special as it requires all @param tags to be inside.
        $tag_groups = [];
        $groupid = 0;
        $param_groupid = null;
        foreach ($tokens[$comment_start]['comment_tags'] as $pos => $tag) {
            if ($pos > 0) {
                $prev = $phpcs_file->find_previous(T_DOC_COMMENT_STRING, $tag - 1, $tokens[$comment_start]['comment_tags'][$pos - 1]);
                if ($prev === false) {
                    $prev = $tokens[$comment_start]['comment_tags'][$pos - 1];
                }
                if ($tokens[$prev]['line'] !== $tokens[$tag]['line'] - 1) {
                    $groupid++;
                }
            }
            if ($tokens[$tag]['content'] === '@param') {
                if ($param_groupid !== null && $param_groupid !== $groupid) {
                    $error = 'Parameter tags must be grouped together in a doc comment';
                    $phpcs_file->add_error($error, $tag, 'ParamGroup');
                }
                if ($param_groupid === null) {
                    $param_groupid = $groupid;
                }
            }
            //end if
            $tag_groups[$groupid][] = $tag;
        }
        //end foreach
        foreach ($tag_groups as $groupid => $group) {
            $max_length = 0;
            $paddings = [];
            foreach ($group as $pos => $tag) {
                if ($param_groupid === $groupid && $tokens[$tag]['content'] !== '@param') {
                    $error = 'Tag %s cannot be grouped with parameter tags in a doc comment';
                    $data = [$tokens[$tag]['content']];
                    $phpcs_file->add_error($error, $tag, 'NonParamGroup', $data);
                }
                $tag_length = $tokens[$tag]['length'];
                if ($tag_length > $max_length) {
                    $max_length = $tag_length;
                }
                // Check for a value. No value means no padding needed.
                $string = $phpcs_file->find_next(T_DOC_COMMENT_STRING, $tag, $comment_end);
                if ($string !== false && $tokens[$string]['line'] === $tokens[$tag]['line']) {
                    $paddings[$tag] = $tokens[$tag + 1]['length'];
                }
            }
            // Check that there was single blank line after the tag block
            // but account for a multi-line tag comments.
            $last_tag = $group[$pos];
            $next = $phpcs_file->find_next(T_DOC_COMMENT_TAG, $last_tag + 3, $comment_end);
            if ($next !== false) {
                $prev = $phpcs_file->find_previous([T_DOC_COMMENT_TAG, T_DOC_COMMENT_STRING], $next - 1, $comment_start);
                if ($tokens[$next]['line'] !== $tokens[$prev]['line'] + 2) {
                    $error = 'There must be a single blank line after a tag group';
                    $fix = $phpcs_file->add_fixable_error($error, $last_tag, 'SpacingAfterTagGroup');
                    if ($fix === true) {
                        $phpcs_file->fixer->begin_changeset();
                        for ($i = $prev + 1; $i < $next; $i++) {
                            if ($tokens[$i]['line'] === $tokens[$next]['line']) {
                                break;
                            }
                            $phpcs_file->fixer->replace_token($i, '');
                        }
                        $indent = str_repeat(' ', $tokens[$stack_ptr]['column']);
                        $phpcs_file->fixer->add_content($prev, $phpcs_file->eol_char . $indent . '*' . $phpcs_file->eol_char);
                        $phpcs_file->fixer->end_changeset();
                    }
                }
            }
            //end if
            // Now check paddings.
            foreach ($paddings as $tag => $padding) {
                $required = $max_length - $tokens[$tag]['length'] + 1;
                if ($padding !== $required) {
                    $error = 'Tag value for %s tag indented incorrectly; expected %s spaces but found %s';
                    $data = [$tokens[$tag]['content'], $required, $padding];
                    $fix = $phpcs_file->add_fixable_error($error, $tag + 1, 'TagValueIndent', $data);
                    if ($fix === true) {
                        $phpcs_file->fixer->replace_token($tag + 1, str_repeat(' ', $required));
                    }
                }
            }
        }
        //end foreach
        // If there is a param group, it needs to be first.
        if ($param_groupid !== null && $param_groupid !== 0) {
            $error = 'Parameter tags must be defined first in a doc comment';
            $phpcs_file->add_error($error, $tag_groups[$param_groupid][0], 'ParamNotFirst');
        }
        $found_tags = [];
        foreach ($tokens[$stack_ptr]['comment_tags'] as $pos => $tag) {
            $tag_name = $tokens[$tag]['content'];
            if (isset($found_tags[$tag_name]) === true) {
                $last_tag = $tokens[$stack_ptr]['comment_tags'][$pos - 1];
                if ($tokens[$last_tag]['content'] !== $tag_name) {
                    $error = 'Tags must be grouped together in a doc comment';
                    $phpcs_file->add_error($error, $tag, 'TagsNotGrouped');
                }
                continue;
            }
            $found_tags[$tag_name] = true;
        }
    }
    //end process()
}
//end class