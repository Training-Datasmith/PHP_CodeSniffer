<?php

declare (strict_types=1);
/**
 * A helper class for fixing errors.
 *
 * Provides helper functions that act upon a token array and modify the file
 * content.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Util\Common;
class Fixer
{
    /**
     * Is the fixer enabled and fixing a file?
     *
     * Sniffs should check this value to ensure they are not
     * doing extra processing to prepare for a fix when fixing is
     * not required.
     *
     * @var boolean
     */
    public $enabled = false;
    /**
     * The number of times we have looped over a file.
     *
     * @var integer
     */
    public $loops = 0;
    /**
     * The file being fixed.
     *
     * @var \PHP_CodeSniffer\Files\File
     */
    private $current_file;
    /**
     * The list of tokens that make up the file contents.
     *
     * This is a simplified list which just contains the token content and nothing
     * else. This is the array that is updated as fixes are made, not the file's
     * token array. Imploding this array will give you the file content back.
     *
     * @var array<int, string>
     */
    private $tokens = [];
    /**
     * A list of tokens that have already been fixed.
     *
     * We don't allow the same token to be fixed more than once each time
     * through a file as this can easily cause conflicts between sniffs.
     *
     * @var int[]
     */
    private $fixed_tokens = [];
    /**
     * The last value of each fixed token.
     *
     * If a token is being "fixed" back to its last value, the fix is
     * probably conflicting with another.
     *
     * @var array<int, string>
     */
    private $old_token_values = [];
    /**
     * A list of tokens that have been fixed during a changeset.
     *
     * All changes in changeset must be able to be applied, or else
     * the entire changeset is rejected.
     *
     * @var array
     */
    private $changeset = [];
    /**
     * Is there an open changeset.
     *
     * @var boolean
     */
    private $in_changeset = false;
    /**
     * Is the current fixing loop in conflict?
     *
     * @var boolean
     */
    private $in_conflict = false;
    /**
     * The number of fixes that have been performed.
     *
     * @var integer
     */
    private $num_fixes = 0;
    /**
     * Starts fixing a new file.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being fixed.
     *
     * @return void
     */
    public function start_file(File $phpcs_file)
    {
        $this->current_file = $phpcs_file;
        $this->num_fixes = 0;
        $this->fixed_tokens = [];
        $tokens = $phpcs_file->get_tokens();
        $this->tokens = [];
        foreach ($tokens as $index => $token) {
            if (isset($token['orig_content']) === true) {
                $this->tokens[$index] = $token['orig_content'];
            } else {
                $this->tokens[$index] = $token['content'];
            }
        }
    }
    //end startFile()
    /**
     * Attempt to fix the file by processing it until no fixes are made.
     *
     * @return boolean
     */
    public function fix_file()
    {
        $fixable = $this->current_file->get_fixable_count();
        if ($fixable === 0) {
            // Nothing to fix.
            return false;
        }
        $this->enabled = true;
        $this->loops = 0;
        while ($this->loops < 50) {
            ob_start();
            // Only needed once file content has changed.
            $contents = $this->get_contents();
            if (PHP_CODESNIFFER_VERBOSITY > 2) {
                @ob_end_clean();
                echo '---START FILE CONTENT---' . PHP_EOL;
                $lines = explode($this->current_file->eol_char, $contents);
                $max = strlen(count($lines));
                foreach ($lines as $line_num => $line) {
                    $line_num++;
                    echo str_pad($line_num, $max, ' ', STR_PAD_LEFT) . '|' . $line . PHP_EOL;
                }
                echo '--- END FILE CONTENT ---' . PHP_EOL;
                ob_start();
            }
            $this->in_conflict = false;
            $this->current_file->ruleset->populate_token_listeners();
            $this->current_file->set_content($contents);
            $this->current_file->process();
            ob_end_clean();
            $this->loops++;
            if (PHP_CODESNIFFER_CBF === true && PHP_CODESNIFFER_VERBOSITY > 0) {
                echo "\r" . str_repeat(' ', 80) . "\r";
                echo "\t=> Fixing file: {$this->num_fixes}/{$fixable} violations remaining [made {$this->loops} pass";
                if ($this->loops > 1) {
                    echo 'es';
                }
                echo ']... ';
                if (PHP_CODESNIFFER_VERBOSITY > 1) {
                    echo PHP_EOL;
                }
            }
            if ($this->num_fixes === 0 && $this->in_conflict === false) {
                // Nothing left to do.
                break;
            } elseif (PHP_CODESNIFFER_VERBOSITY > 1) {
                echo "\t* fixed {$this->num_fixes} violations, starting loop " . ($this->loops + 1) . ' *' . PHP_EOL;
            }
        }
        //end while
        $this->enabled = false;
        if ($this->num_fixes > 0) {
            if (PHP_CODESNIFFER_VERBOSITY > 1) {
                if (ob_get_level() > 0) {
                    ob_end_clean();
                }
                echo "\t*** Reached maximum number of loops with {$this->num_fixes} violations left unfixed ***" . PHP_EOL;
                ob_start();
            }
            return false;
        }
        return true;
    }
    //end fixFile()
    /**
     * Generates a text diff of the original file and the new content.
     *
     * @param string  $filePath Optional file path to diff the file against.
     *                          If not specified, the original version of the
     *                          file will be used.
     * @param boolean $colors   Print coloured output or not.
     *
     * @return string
     */
    public function generate_diff($file_path = null, $colors = true)
    {
        if ($file_path === null) {
            $file_path = $this->current_file->get_filename();
        }
        $cwd = getcwd() . DIRECTORY_SEPARATOR;
        if (strpos($file_path, $cwd) === 0) {
            $filename = substr($file_path, strlen($cwd));
        } else {
            $filename = $file_path;
        }
        $contents = $this->get_contents();
        $temp_name = tempnam(sys_get_temp_dir(), 'phpcs-fixer');
        $fixed_file = fopen($temp_name, 'w');
        fwrite($fixed_file, $contents);
        // We must use something like shell_exec() because whitespace at the end
        // of lines is critical to diff files.
        $filename = escapeshellarg($filename);
        $cmd = "diff -u -L{$filename} -LPHP_CodeSniffer {$filename} \"{$temp_name}\"";
        $diff = shell_exec($cmd);
        fclose($fixed_file);
        if (is_file($temp_name) === true) {
            unlink($temp_name);
        }
        if ($diff === null) {
            return '';
        }
        if ($colors === false) {
            return $diff;
        }
        $diff_lines = explode(PHP_EOL, $diff);
        if (count($diff_lines) === 1) {
            // Seems to be required for cygwin.
            $diff_lines = explode("\n", $diff);
        }
        $diff = [];
        foreach ($diff_lines as $line) {
            if (isset($line[0]) === true) {
                switch ($line[0]) {
                    case '-':
                        $diff[] = "\x1b[31m{$line}\x1b[0m";
                        break;
                    case '+':
                        $diff[] = "\x1b[32m{$line}\x1b[0m";
                        break;
                    default:
                        $diff[] = $line;
                }
            }
        }
        return implode(PHP_EOL, $diff);
    }
    //end generateDiff()
    /**
     * Get a count of fixes that have been performed on the file.
     *
     * This value is reset every time a new file is started, or an existing
     * file is restarted.
     *
     * @return int
     */
    public function get_fix_count()
    {
        return $this->num_fixes;
    }
    //end getFixCount()
    /**
     * Get the current content of the file, as a string.
     *
     * @return string
     */
    public function get_contents()
    {
        return implode('', $this->tokens);
    }
    //end getContents()
    /**
     * Get the current fixed content of a token.
     *
     * This function takes changesets into account so should be used
     * instead of directly accessing the token array.
     *
     * @param int $stackPtr The position of the token in the token stack.
     *
     * @return string
     */
    public function get_token_content($stack_ptr)
    {
        if ($this->in_changeset === true && isset($this->changeset[$stack_ptr]) === true) {
            return $this->changeset[$stack_ptr];
        }
        return $this->tokens[$stack_ptr];
    }
    //end getTokenContent()
    /**
     * Start recording actions for a changeset.
     *
     * @return void
     */
    public function begin_changeset()
    {
        if ($this->in_conflict === true) {
            return false;
        }
        if (PHP_CODESNIFFER_VERBOSITY > 1) {
            $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            if ($bt[1]['class'] === __CLASS__) {
                $sniff = 'Fixer';
            } else {
                $sniff = Util\Common::get_sniff_code($bt[1]['class']);
            }
            $line = $bt[0]['line'];
            @ob_end_clean();
            echo "\t=> Changeset started by {$sniff}:{$line}" . PHP_EOL;
            ob_start();
        }
        $this->changeset = [];
        $this->in_changeset = true;
    }
    //end beginChangeset()
    /**
     * Stop recording actions for a changeset, and apply logged changes.
     *
     * @return boolean
     */
    public function end_changeset()
    {
        if ($this->in_conflict === true) {
            return false;
        }
        $this->in_changeset = false;
        $success = true;
        $applied = [];
        foreach ($this->changeset as $stack_ptr => $content) {
            $success = $this->replace_token($stack_ptr, $content);
            if ($success === false) {
                break;
            } else {
                $applied[] = $stack_ptr;
            }
        }
        if ($success === false) {
            // Rolling back all changes.
            foreach ($applied as $stack_ptr) {
                $this->revert_token($stack_ptr);
            }
            if (PHP_CODESNIFFER_VERBOSITY > 1) {
                @ob_end_clean();
                echo "\t=> Changeset failed to apply" . PHP_EOL;
                ob_start();
            }
        } elseif (PHP_CODESNIFFER_VERBOSITY > 1) {
            $fixes = count($this->changeset);
            @ob_end_clean();
            echo "\t=> Changeset ended: {$fixes} changes applied" . PHP_EOL;
            ob_start();
        }
        $this->changeset = [];
        return true;
    }
    //end endChangeset()
    /**
     * Stop recording actions for a changeset, and discard logged changes.
     *
     * @return void
     */
    public function rollback_changeset()
    {
        $this->in_changeset = false;
        $this->in_conflict = false;
        if (empty($this->changeset) === false) {
            if (PHP_CODESNIFFER_VERBOSITY > 1) {
                $bt = debug_backtrace();
                if ($bt[1]['class'] === 'PHP_CodeSniffer\Fixer') {
                    $sniff = $bt[2]['class'];
                    $line = $bt[1]['line'];
                } else {
                    $sniff = $bt[1]['class'];
                    $line = $bt[0]['line'];
                }
                $sniff = Util\Common::get_sniff_code($sniff);
                $num_changes = count($this->changeset);
                @ob_end_clean();
                echo "\t\tR: {$sniff}:{$line} rolled back the changeset ({$num_changes} changes)" . PHP_EOL;
                echo "\t=> Changeset rolled back" . PHP_EOL;
                ob_start();
            }
            $this->changeset = [];
        }
        //end if
    }
    //end rollbackChangeset()
    /**
     * Replace the entire contents of a token.
     *
     * @param int    $stackPtr The position of the token in the token stack.
     * @param string $content  The new content of the token.
     *
     * @return bool If the change was accepted.
     */
    public function replace_token($stack_ptr, $content)
    {
        if ($this->in_conflict === true) {
            return false;
        }
        if ($this->in_changeset === false && isset($this->fixed_tokens[$stack_ptr]) === true) {
            $indent = "\t";
            if (empty($this->changeset) === false) {
                $indent .= "\t";
            }
            if (PHP_CODESNIFFER_VERBOSITY > 1) {
                @ob_end_clean();
                echo "{$indent}* token {$stack_ptr} has already been modified, skipping *" . PHP_EOL;
                ob_start();
            }
            return false;
        }
        if (PHP_CODESNIFFER_VERBOSITY > 1) {
            $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            if ($bt[1]['class'] === 'PHP_CodeSniffer\Fixer') {
                $sniff = $bt[2]['class'];
                $line = $bt[1]['line'];
            } else {
                $sniff = $bt[1]['class'];
                $line = $bt[0]['line'];
            }
            $sniff = Util\Common::get_sniff_code($sniff);
            $tokens = $this->current_file->get_tokens();
            $type = $tokens[$stack_ptr]['type'];
            $token_line = $tokens[$stack_ptr]['line'];
            $old_content = Common::prepare_for_output($this->tokens[$stack_ptr]);
            $new_content = Common::prepare_for_output($content);
            if (trim($this->tokens[$stack_ptr]) === '' && isset($this->tokens[$stack_ptr + 1]) === true) {
                // Add some context for whitespace only changes.
                $append = Common::prepare_for_output($this->tokens[$stack_ptr + 1]);
                $old_content .= $append;
                $new_content .= $append;
            }
        }
        //end if
        if ($this->in_changeset === true) {
            $this->changeset[$stack_ptr] = $content;
            if (PHP_CODESNIFFER_VERBOSITY > 1) {
                @ob_end_clean();
                echo "\t\tQ: {$sniff}:{$line} replaced token {$stack_ptr} ({$type} on line {$token_line}) \"{$old_content}\" => \"{$new_content}\"" . PHP_EOL;
                ob_start();
            }
            return true;
        }
        if (isset($this->old_token_values[$stack_ptr]) === false) {
            $this->old_token_values[$stack_ptr] = ['curr' => $content, 'prev' => $this->tokens[$stack_ptr], 'loop' => $this->loops];
        } else {
            if ($this->old_token_values[$stack_ptr]['prev'] === $content && $this->old_token_values[$stack_ptr]['loop'] === $this->loops - 1) {
                if (PHP_CODESNIFFER_VERBOSITY > 1) {
                    $indent = "\t";
                    if (empty($this->changeset) === false) {
                        $indent .= "\t";
                    }
                    $loop = $this->old_token_values[$stack_ptr]['loop'];
                    @ob_end_clean();
                    echo "{$indent}**** {$sniff}:{$line} has possible conflict with another sniff on loop {$loop}; caused by the following change ****" . PHP_EOL;
                    echo "{$indent}**** replaced token {$stack_ptr} ({$type} on line {$token_line}) \"{$old_content}\" => \"{$new_content}\" ****" . PHP_EOL;
                }
                if ($this->old_token_values[$stack_ptr]['loop'] >= $this->loops - 1) {
                    $this->in_conflict = true;
                    if (PHP_CODESNIFFER_VERBOSITY > 1) {
                        echo "{$indent}**** ignoring all changes until next loop ****" . PHP_EOL;
                    }
                }
                if (PHP_CODESNIFFER_VERBOSITY > 1) {
                    ob_start();
                }
                return false;
            }
            //end if
            $this->old_token_values[$stack_ptr]['prev'] = $this->old_token_values[$stack_ptr]['curr'];
            $this->old_token_values[$stack_ptr]['curr'] = $content;
            $this->old_token_values[$stack_ptr]['loop'] = $this->loops;
        }
        //end if
        $this->fixed_tokens[$stack_ptr] = $this->tokens[$stack_ptr];
        $this->tokens[$stack_ptr] = $content;
        $this->num_fixes++;
        if (PHP_CODESNIFFER_VERBOSITY > 1) {
            $indent = "\t";
            if (empty($this->changeset) === false) {
                $indent .= "\tA: ";
            }
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            echo "{$indent}{$sniff}:{$line} replaced token {$stack_ptr} ({$type} on line {$token_line}) \"{$old_content}\" => \"{$new_content}\"" . PHP_EOL;
            ob_start();
        }
        return true;
    }
    //end replaceToken()
    /**
     * Reverts the previous fix made to a token.
     *
     * @param int $stackPtr The position of the token in the token stack.
     *
     * @return bool If a change was reverted.
     */
    public function revert_token($stack_ptr)
    {
        if (isset($this->fixed_tokens[$stack_ptr]) === false) {
            return false;
        }
        if (PHP_CODESNIFFER_VERBOSITY > 1) {
            $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            if ($bt[1]['class'] === 'PHP_CodeSniffer\Fixer') {
                $sniff = $bt[2]['class'];
                $line = $bt[1]['line'];
            } else {
                $sniff = $bt[1]['class'];
                $line = $bt[0]['line'];
            }
            $sniff = Util\Common::get_sniff_code($sniff);
            $tokens = $this->current_file->get_tokens();
            $type = $tokens[$stack_ptr]['type'];
            $token_line = $tokens[$stack_ptr]['line'];
            $old_content = Common::prepare_for_output($this->tokens[$stack_ptr]);
            $new_content = Common::prepare_for_output($this->fixed_tokens[$stack_ptr]);
            if (trim($this->tokens[$stack_ptr]) === '' && isset($tokens[$stack_ptr + 1]) === true) {
                // Add some context for whitespace only changes.
                $append = Common::prepare_for_output($this->tokens[$stack_ptr + 1]);
                $old_content .= $append;
                $new_content .= $append;
            }
        }
        //end if
        $this->tokens[$stack_ptr] = $this->fixed_tokens[$stack_ptr];
        unset($this->fixed_tokens[$stack_ptr]);
        $this->num_fixes--;
        if (PHP_CODESNIFFER_VERBOSITY > 1) {
            $indent = "\t";
            if (empty($this->changeset) === false) {
                $indent .= "\tR: ";
            }
            @ob_end_clean();
            echo "{$indent}{$sniff}:{$line} reverted token {$stack_ptr} ({$type} on line {$token_line}) \"{$old_content}\" => \"{$new_content}\"" . PHP_EOL;
            ob_start();
        }
        return true;
    }
    //end revertToken()
    /**
     * Replace the content of a token with a part of its current content.
     *
     * @param int $stackPtr The position of the token in the token stack.
     * @param int $start    The first character to keep.
     * @param int $length   The number of characters to keep. If NULL, the content of
     *                      the token from $start to the end of the content is kept.
     *
     * @return bool If the change was accepted.
     */
    public function substr_token($stack_ptr, $start, $length = null)
    {
        $current = $this->get_token_content($stack_ptr);
        if ($length === null) {
            $new_content = substr($current, $start);
        } else {
            $new_content = substr($current, $start, $length);
        }
        return $this->replace_token($stack_ptr, $new_content);
    }
    //end substrToken()
    /**
     * Adds a newline to end of a token's content.
     *
     * @param int $stackPtr The position of the token in the token stack.
     *
     * @return bool If the change was accepted.
     */
    public function add_newline($stack_ptr)
    {
        $current = $this->get_token_content($stack_ptr);
        return $this->replace_token($stack_ptr, $current . $this->current_file->eol_char);
    }
    //end addNewline()
    /**
     * Adds a newline to the start of a token's content.
     *
     * @param int $stackPtr The position of the token in the token stack.
     *
     * @return bool If the change was accepted.
     */
    public function add_newline_before($stack_ptr)
    {
        $current = $this->get_token_content($stack_ptr);
        return $this->replace_token($stack_ptr, $this->current_file->eol_char . $current);
    }
    //end addNewlineBefore()
    /**
     * Adds content to the end of a token's current content.
     *
     * @param int    $stackPtr The position of the token in the token stack.
     * @param string $content  The content to add.
     *
     * @return bool If the change was accepted.
     */
    public function add_content($stack_ptr, $content)
    {
        $current = $this->get_token_content($stack_ptr);
        return $this->replace_token($stack_ptr, $current . $content);
    }
    //end addContent()
    /**
     * Adds content to the start of a token's current content.
     *
     * @param int    $stackPtr The position of the token in the token stack.
     * @param string $content  The content to add.
     *
     * @return bool If the change was accepted.
     */
    public function add_content_before($stack_ptr, $content)
    {
        $current = $this->get_token_content($stack_ptr);
        return $this->replace_token($stack_ptr, $content . $current);
    }
    //end addContentBefore()
    /**
     * Adjust the indent of a code block.
     *
     * @param int $start  The position of the token in the token stack
     *                    to start adjusting the indent from.
     * @param int $end    The position of the token in the token stack
     *                    to end adjusting the indent.
     * @param int $change The number of spaces to adjust the indent by
     *                    (positive or negative).
     *
     * @return void
     */
    public function change_code_block_indent($start, $end, $change)
    {
        $tokens = $this->current_file->get_tokens();
        $base_indent = '';
        if ($change > 0) {
            $base_indent = str_repeat(' ', $change);
        }
        $use_changeset = false;
        if ($this->in_changeset === false) {
            $this->begin_changeset();
            $use_changeset = true;
        }
        for ($i = $start; $i <= $end; $i++) {
            if ($tokens[$i]['column'] !== 1) {
                continue;
            }
            if ($tokens[$i + 1]['line'] !== $tokens[$i]['line']) {
                continue;
            }
            $length = 0;
            if ($tokens[$i]['code'] === T_WHITESPACE || $tokens[$i]['code'] === T_DOC_COMMENT_WHITESPACE) {
                $length = $tokens[$i]['length'];
                $padding = $length + $change;
                if ($padding > 0) {
                    $padding = str_repeat(' ', $padding);
                } else {
                    $padding = '';
                }
                $new_content = $padding . ltrim($tokens[$i]['content']);
            } else {
                $new_content = $base_indent . $tokens[$i]['content'];
            }
            $this->replace_token($i, $new_content);
        }
        //end for
        if ($use_changeset === true) {
            $this->end_changeset();
        }
    }
    //end changeCodeBlockIndent()
}
//end class