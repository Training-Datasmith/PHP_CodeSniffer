<?php

declare (strict_types=1);
/**
 * Parses and verifies the doc comments for files.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\PEAR\Sniffs\Commenting;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Sniffs\Sniff;
use Php_code_Sniffer\Util\Common;
class File_Comment_Sniff implements Sniff
{
    /**
     * Tags in correct order and related info.
     *
     * @var array
     */
    protected $tags = ['@category' => ['required' => true, 'allow_multiple' => false], '@package' => ['required' => true, 'allow_multiple' => false], '@subpackage' => ['required' => false, 'allow_multiple' => false], '@author' => ['required' => true, 'allow_multiple' => true], '@copyright' => ['required' => false, 'allow_multiple' => true], '@license' => ['required' => true, 'allow_multiple' => false], '@version' => ['required' => false, 'allow_multiple' => false], '@link' => ['required' => true, 'allow_multiple' => true], '@see' => ['required' => false, 'allow_multiple' => true], '@since' => ['required' => false, 'allow_multiple' => false], '@deprecated' => ['required' => false, 'allow_multiple' => false]];
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
     * @param int                         $stackPtr  The position of the current token
     *                                               in the stack passed in $tokens.
     *
     * @return int
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        // Find the next non whitespace token.
        $comment_start = $phpcs_file->find_next(T_WHITESPACE, $stack_ptr + 1, null, true);
        // Allow declare() statements at the top of the file.
        if ($tokens[$comment_start]['code'] === T_DECLARE) {
            $semicolon = $phpcs_file->find_next(T_SEMICOLON, $comment_start + 1);
            $comment_start = $phpcs_file->find_next(T_WHITESPACE, $semicolon + 1, null, true);
        }
        // Ignore vim header.
        if ($tokens[$comment_start]['code'] === T_COMMENT) {
            if (strstr($tokens[$comment_start]['content'], 'vim:') !== false) {
                $comment_start = $phpcs_file->find_next(T_WHITESPACE, $comment_start + 1, null, true);
            }
        }
        $error_token = $stack_ptr + 1;
        if (isset($tokens[$error_token]) === false) {
            $error_token--;
        }
        if ($tokens[$comment_start]['code'] === T_CLOSE_TAG) {
            // We are only interested if this is the first open tag.
            return $phpcs_file->num_tokens + 1;
        }
        if ($tokens[$comment_start]['code'] === T_COMMENT) {
            $error = 'You must use "/**" style comments for a file comment';
            $phpcs_file->add_error($error, $error_token, 'WrongStyle');
            $phpcs_file->record_metric($stack_ptr, 'File has doc comment', 'yes');
            return $phpcs_file->num_tokens + 1;
        }
        if ($comment_start === false || $tokens[$comment_start]['code'] !== T_DOC_COMMENT_OPEN_TAG) {
            $phpcs_file->add_error('Missing file doc comment', $error_token, 'Missing');
            $phpcs_file->record_metric($stack_ptr, 'File has doc comment', 'no');
            return $phpcs_file->num_tokens + 1;
        }
        $comment_end = $tokens[$comment_start]['comment_closer'];
        for ($next_token = $comment_end + 1; $next_token < $phpcs_file->num_tokens; $next_token++) {
            if ($tokens[$next_token]['code'] === T_WHITESPACE) {
                continue;
            }
            if ($tokens[$next_token]['code'] === T_ATTRIBUTE && isset($tokens[$next_token]['attribute_closer']) === true) {
                $next_token = $tokens[$next_token]['attribute_closer'];
                continue;
            }
            break;
        }
        if ($next_token === $phpcs_file->num_tokens) {
            $next_token--;
        }
        $ignore = [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM, T_FUNCTION, T_CLOSURE, T_PUBLIC, T_PRIVATE, T_PROTECTED, T_FINAL, T_STATIC, T_ABSTRACT, T_READONLY, T_CONST, T_PROPERTY];
        if (in_array($tokens[$next_token]['code'], $ignore, true) === true) {
            $phpcs_file->add_error('Missing file doc comment', $stack_ptr, 'Missing');
            $phpcs_file->record_metric($stack_ptr, 'File has doc comment', 'no');
            return $phpcs_file->num_tokens + 1;
        }
        $phpcs_file->record_metric($stack_ptr, 'File has doc comment', 'yes');
        // Check the PHP Version, which should be in some text before the first tag.
        $found = false;
        for ($i = $comment_start + 1; $i < $comment_end; $i++) {
            if ($tokens[$i]['code'] === T_DOC_COMMENT_TAG) {
                break;
            } elseif ($tokens[$i]['code'] === T_DOC_COMMENT_STRING && strstr(strtolower($tokens[$i]['content']), 'php version') !== false) {
                $found = true;
                break;
            }
        }
        if ($found === false) {
            $error = 'PHP version not specified';
            $phpcs_file->add_warning($error, $comment_end, 'MissingVersion');
        }
        // Check each tag.
        $this->process_tags($phpcs_file, $stack_ptr, $comment_start);
        // Ignore the rest of the file.
        return $phpcs_file->num_tokens + 1;
    }
    //end process()
    /**
     * Processes each required or optional tag.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile    The file being scanned.
     * @param int                         $stackPtr     The position of the current token
     *                                                  in the stack passed in $tokens.
     * @param int                         $commentStart Position in the stack where the comment started.
     *
     * @return void
     */
    protected function process_tags($phpcs_file, $stack_ptr, $comment_start)
    {
        $tokens = $phpcs_file->get_tokens();
        if (get_class($this) === 'PHP_CodeSniffer\Standards\PEAR\Sniffs\Commenting\FileCommentSniff') {
            $doc_block = 'file';
        } else {
            $doc_block = 'class';
        }
        $comment_end = $tokens[$comment_start]['comment_closer'];
        $found_tags = [];
        $tag_tokens = [];
        foreach ($tokens[$comment_start]['comment_tags'] as $tag) {
            $name = $tokens[$tag]['content'];
            if (isset($this->tags[$name]) === false) {
                continue;
            }
            if ($this->tags[$name]['allow_multiple'] === false && isset($tag_tokens[$name]) === true) {
                $error = 'Only one %s tag is allowed in a %s comment';
                $data = [$name, $doc_block];
                $phpcs_file->add_error($error, $tag, 'Duplicate' . ucfirst(substr($name, 1)) . 'Tag', $data);
            }
            $found_tags[] = $name;
            $tag_tokens[$name][] = $tag;
            $string = $phpcs_file->find_next(T_DOC_COMMENT_STRING, $tag, $comment_end);
            if ($string === false || $tokens[$string]['line'] !== $tokens[$tag]['line']) {
                $error = 'Content missing for %s tag in %s comment';
                $data = [$name, $doc_block];
                $phpcs_file->add_error($error, $tag, 'Empty' . ucfirst(substr($name, 1)) . 'Tag', $data);
                continue;
            }
        }
        //end foreach
        // Check if the tags are in the correct position.
        $pos = 0;
        foreach ($this->tags as $tag => $tag_data) {
            if (isset($tag_tokens[$tag]) === false) {
                if ($tag_data['required'] === true) {
                    $error = 'Missing %s tag in %s comment';
                    $data = [$tag, $doc_block];
                    $phpcs_file->add_error($error, $comment_end, 'Missing' . ucfirst(substr($tag, 1)) . 'Tag', $data);
                }
                continue;
            }
            $method = 'process' . substr($tag, 1);
            if (method_exists($this, $method) === true) {
                // Process each tag if a method is defined.
                call_user_func([$this, $method], $phpcs_file, $tag_tokens[$tag]);
            }
            if (isset($found_tags[$pos]) === false) {
                break;
            }
            if ($found_tags[$pos] !== $tag) {
                $error = 'The tag in position %s should be the %s tag';
                $data = [$pos + 1, $tag];
                $phpcs_file->add_error($error, $tokens[$comment_start]['comment_tags'][$pos], ucfirst(substr($tag, 1)) . 'TagOrder', $data);
            }
            // Account for multiple tags.
            $pos++;
            while (isset($found_tags[$pos]) === true && $found_tags[$pos] === $tag) {
                $pos++;
            }
        }
        //end foreach
    }
    //end processTags()
    /**
     * Process the category tag.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param array                       $tags      The tokens for these tags.
     *
     * @return void
     */
    protected function process_category($phpcs_file, array $tags)
    {
        $tokens = $phpcs_file->get_tokens();
        foreach ($tags as $tag) {
            if ($tokens[$tag + 2]['code'] !== T_DOC_COMMENT_STRING) {
                // No content.
                continue;
            }
            $content = $tokens[$tag + 2]['content'];
            if (Common::is_underscore_name($content) !== true) {
                $new_content = str_replace(' ', '_', $content);
                $name_bits = explode('_', $new_content);
                $first_bit = array_shift($name_bits);
                $new_name = ucfirst($first_bit) . '_';
                foreach ($name_bits as $bit) {
                    if ($bit !== '') {
                        $new_name .= ucfirst($bit) . '_';
                    }
                }
                $error = 'Category name "%s" is not valid; consider "%s" instead';
                $valid_name = trim($new_name, '_');
                $data = [$content, $valid_name];
                $phpcs_file->add_error($error, $tag, 'InvalidCategory', $data);
            }
        }
        //end foreach
    }
    //end processCategory()
    /**
     * Process the package tag.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param array                       $tags      The tokens for these tags.
     *
     * @return void
     */
    protected function process_package($phpcs_file, array $tags)
    {
        $tokens = $phpcs_file->get_tokens();
        foreach ($tags as $tag) {
            if ($tokens[$tag + 2]['code'] !== T_DOC_COMMENT_STRING) {
                // No content.
                continue;
            }
            $content = $tokens[$tag + 2]['content'];
            if (Common::is_underscore_name($content) === true) {
                continue;
            }
            $new_content = str_replace(' ', '_', $content);
            $new_content = trim($new_content, '_');
            $new_content = preg_replace('/[^A-Za-z_]/', '', $new_content);
            if ($new_content === '') {
                $error = 'Package name "%s" is not valid';
                $data = [$content];
                $phpcs_file->add_error($error, $tag, 'InvalidPackageValue', $data);
            } else {
                $name_bits = explode('_', $new_content);
                $first_bit = array_shift($name_bits);
                $new_name = strtoupper($first_bit[0]) . substr($first_bit, 1) . '_';
                foreach ($name_bits as $bit) {
                    if ($bit !== '') {
                        $new_name .= strtoupper($bit[0]) . substr($bit, 1) . '_';
                    }
                }
                $error = 'Package name "%s" is not valid; consider "%s" instead';
                $valid_name = trim($new_name, '_');
                $data = [$content, $valid_name];
                $phpcs_file->add_error($error, $tag, 'InvalidPackage', $data);
            }
            //end if
        }
        //end foreach
    }
    //end processPackage()
    /**
     * Process the subpackage tag.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param array                       $tags      The tokens for these tags.
     *
     * @return void
     */
    protected function process_subpackage($phpcs_file, array $tags)
    {
        $tokens = $phpcs_file->get_tokens();
        foreach ($tags as $tag) {
            if ($tokens[$tag + 2]['code'] !== T_DOC_COMMENT_STRING) {
                // No content.
                continue;
            }
            $content = $tokens[$tag + 2]['content'];
            if (Common::is_underscore_name($content) === true) {
                continue;
            }
            $new_content = str_replace(' ', '_', $content);
            $name_bits = explode('_', $new_content);
            $first_bit = array_shift($name_bits);
            $new_name = strtoupper($first_bit[0]) . substr($first_bit, 1) . '_';
            foreach ($name_bits as $bit) {
                if ($bit !== '') {
                    $new_name .= strtoupper($bit[0]) . substr($bit, 1) . '_';
                }
            }
            $error = 'Subpackage name "%s" is not valid; consider "%s" instead';
            $valid_name = trim($new_name, '_');
            $data = [$content, $valid_name];
            $phpcs_file->add_error($error, $tag, 'InvalidSubpackage', $data);
        }
        //end foreach
    }
    //end processSubpackage()
    /**
     * Process the author tag(s) that this header comment has.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param array                       $tags      The tokens for these tags.
     *
     * @return void
     */
    protected function process_author($phpcs_file, array $tags)
    {
        $tokens = $phpcs_file->get_tokens();
        foreach ($tags as $tag) {
            if ($tokens[$tag + 2]['code'] !== T_DOC_COMMENT_STRING) {
                // No content.
                continue;
            }
            $content = $tokens[$tag + 2]['content'];
            $local = '\da-zA-Z-_+';
            // Dot character cannot be the first or last character in the local-part.
            $local_middle = $local . '.\w';
            if (preg_match('/^([^<]*)\s+<([' . $local . ']([' . $local_middle . ']*[' . $local . '])*@[\da-zA-Z][-.\w]*[\da-zA-Z]\.[a-zA-Z]{2,})>$/', $content) === 0) {
                $error = 'Content of the @author tag must be in the form "Display Name <username@example.com>"';
                $phpcs_file->add_error($error, $tag, 'InvalidAuthors');
            }
        }
    }
    //end processAuthor()
    /**
     * Process the copyright tags.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param array                       $tags      The tokens for these tags.
     *
     * @return void
     */
    protected function process_copyright($phpcs_file, array $tags)
    {
        $tokens = $phpcs_file->get_tokens();
        foreach ($tags as $tag) {
            if ($tokens[$tag + 2]['code'] !== T_DOC_COMMENT_STRING) {
                // No content.
                continue;
            }
            $content = $tokens[$tag + 2]['content'];
            $matches = [];
            if (preg_match('/^([0-9]{4})((.{1})([0-9]{4}))? (.+)$/', $content, $matches) !== 0) {
                // Check earliest-latest year order.
                if ($matches[3] !== '' && $matches[3] !== null) {
                    if ($matches[3] !== '-') {
                        $error = 'A hyphen must be used between the earliest and latest year';
                        $phpcs_file->add_error($error, $tag, 'CopyrightHyphen');
                    }
                    if ($matches[4] !== '' && $matches[4] !== null && $matches[4] < $matches[1]) {
                        $error = "Invalid year span \"{$matches[1]}{$matches[3]}{$matches[4]}\" found; consider \"{$matches[4]}-{$matches[1]}\" instead";
                        $phpcs_file->add_warning($error, $tag, 'InvalidCopyright');
                    }
                }
            } else {
                $error = '@copyright tag must contain a year and the name of the copyright holder';
                $phpcs_file->add_error($error, $tag, 'IncompleteCopyright');
            }
        }
        //end foreach
    }
    //end processCopyright()
    /**
     * Process the license tag.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param array                       $tags      The tokens for these tags.
     *
     * @return void
     */
    protected function process_license($phpcs_file, array $tags)
    {
        $tokens = $phpcs_file->get_tokens();
        foreach ($tags as $tag) {
            if ($tokens[$tag + 2]['code'] !== T_DOC_COMMENT_STRING) {
                // No content.
                continue;
            }
            $content = $tokens[$tag + 2]['content'];
            $matches = [];
            preg_match('/^([^\s]+)\s+(.*)/', $content, $matches);
            if (count($matches) !== 3) {
                $error = '@license tag must contain a URL and a license name';
                $phpcs_file->add_error($error, $tag, 'IncompleteLicense');
            }
        }
    }
    //end processLicense()
    /**
     * Process the version tag.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param array                       $tags      The tokens for these tags.
     *
     * @return void
     */
    protected function process_version($phpcs_file, array $tags)
    {
        $tokens = $phpcs_file->get_tokens();
        foreach ($tags as $tag) {
            if ($tokens[$tag + 2]['code'] !== T_DOC_COMMENT_STRING) {
                // No content.
                continue;
            }
            $content = $tokens[$tag + 2]['content'];
            if (strstr($content, 'CVS:') === false && strstr($content, 'SVN:') === false && strstr($content, 'GIT:') === false && strstr($content, 'HG:') === false) {
                $error = 'Invalid version "%s" in file comment; consider "CVS: <cvs_id>" or "SVN: <svn_id>" or "GIT: <git_id>" or "HG: <hg_id>" instead';
                $data = [$content];
                $phpcs_file->add_warning($error, $tag, 'InvalidVersion', $data);
            }
        }
    }
    //end processVersion()
}
//end class