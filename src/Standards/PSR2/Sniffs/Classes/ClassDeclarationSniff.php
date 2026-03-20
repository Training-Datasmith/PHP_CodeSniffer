<?php

declare (strict_types=1);
/**
 * Checks the declaration of the class and its inheritance is correct.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace Php_code_Sniffer\Standards\PSR2\Sniffs\Classes;

use Php_code_Sniffer\Files\File;
use Php_code_Sniffer\Standards\PEAR\Sniffs\Classes\Class_Declaration_Sniff as PEARClassDeclarationSniff;
use Php_code_Sniffer\Util\Tokens;
class Class_Declaration_Sniff extends Pear_Class_Declaration_Sniff
{
    /**
     * The number of spaces code should be indented.
     *
     * @var integer
     */
    public $indent = 4;
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
        // We want all the errors from the PEAR standard, plus some of our own.
        parent::process($phpcs_file, $stack_ptr);
        // Just in case.
        $tokens = $phpcs_file->get_tokens();
        if (isset($tokens[$stack_ptr]['scope_opener']) === false) {
            return;
        }
        $this->process_open($phpcs_file, $stack_ptr);
        $this->process_close($phpcs_file, $stack_ptr);
    }
    //end process()
    /**
     * Processes the opening section of a class declaration.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token
     *                                               in the stack passed in $tokens.
     *
     * @return void
     */
    public function process_open(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        $stack_ptr_type = strtolower($tokens[$stack_ptr]['content']);
        // Check alignment of the keyword and braces.
        if ($tokens[$stack_ptr - 1]['code'] === T_WHITESPACE) {
            $prev_content = $tokens[$stack_ptr - 1]['content'];
            if ($prev_content !== $phpcs_file->eol_char) {
                $blank_space = substr($prev_content, strpos($prev_content, $phpcs_file->eol_char));
                $spaces = strlen($blank_space);
                if (in_array($tokens[$stack_ptr - 2]['code'], [T_ABSTRACT, T_FINAL, T_READONLY], true) === true && $spaces !== 1) {
                    $prev_content = strtolower($tokens[$stack_ptr - 2]['content']);
                    $error = 'Expected 1 space between %s and %s keywords; %s found';
                    $data = [$prev_content, $stack_ptr_type, $spaces];
                    $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'SpaceBeforeKeyword', $data);
                    if ($fix === true) {
                        $phpcs_file->fixer->replace_token($stack_ptr - 1, ' ');
                    }
                }
            } elseif ($tokens[$stack_ptr - 2]['code'] === T_ABSTRACT || $tokens[$stack_ptr - 2]['code'] === T_FINAL || $tokens[$stack_ptr - 2]['code'] === T_READONLY) {
                $prev_content = strtolower($tokens[$stack_ptr - 2]['content']);
                $error = 'Expected 1 space between %s and %s keywords; newline found';
                $data = [$prev_content, $stack_ptr_type];
                $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'NewlineBeforeKeyword', $data);
                if ($fix === true) {
                    $phpcs_file->fixer->replace_token($stack_ptr - 1, ' ');
                }
            }
            //end if
        }
        //end if
        // We'll need the indent of the class/interface declaration for later.
        $class_indent = 0;
        for ($i = $stack_ptr - 1; $i > 0; $i--) {
            if ($tokens[$i]['line'] === $tokens[$stack_ptr]['line']) {
                continue;
            }
            // We changed lines.
            if ($tokens[$i + 1]['code'] === T_WHITESPACE) {
                $class_indent = $tokens[$i + 1]['length'];
            }
            break;
        }
        $class_name = null;
        $check_spacing = true;
        if ($tokens[$stack_ptr]['code'] !== T_ANON_CLASS) {
            $class_name = $phpcs_file->find_next(T_STRING, $stack_ptr);
        } else {
            // Ignore the spacing check if this is a simple anon class.
            $next = $phpcs_file->find_next(T_WHITESPACE, $stack_ptr + 1, null, true);
            if ($next === $tokens[$stack_ptr]['scope_opener'] && $tokens[$next]['line'] > $tokens[$stack_ptr]['line']) {
                $check_spacing = false;
            }
        }
        if ($check_spacing === true) {
            // Spacing of the keyword.
            if ($tokens[$stack_ptr + 1]['code'] !== T_WHITESPACE) {
                $gap = 0;
            } elseif ($tokens[$stack_ptr + 2]['line'] !== $tokens[$stack_ptr]['line']) {
                $gap = 'newline';
            } else {
                $gap = $tokens[$stack_ptr + 1]['length'];
            }
            if ($gap !== 1) {
                $error = 'Expected 1 space after %s keyword; %s found';
                $data = [$stack_ptr_type, $gap];
                $fix = $phpcs_file->add_fixable_error($error, $stack_ptr, 'SpaceAfterKeyword', $data);
                if ($fix === true) {
                    if ($gap === 0) {
                        $phpcs_file->fixer->add_content($stack_ptr, ' ');
                    } else {
                        $phpcs_file->fixer->replace_token($stack_ptr + 1, ' ');
                    }
                }
            }
        }
        //end if
        // Check after the class/interface name.
        if ($class_name !== null && $tokens[$class_name + 2]['line'] === $tokens[$class_name]['line']) {
            $gap = $tokens[$class_name + 1]['content'];
            if (strlen($gap) !== 1) {
                $found = strlen($gap);
                $error = 'Expected 1 space after %s name; %s found';
                $data = [$stack_ptr_type, $found];
                $fix = $phpcs_file->add_fixable_error($error, $class_name, 'SpaceAfterName', $data);
                if ($fix === true) {
                    $phpcs_file->fixer->replace_token($class_name + 1, ' ');
                }
            }
        }
        $opening_brace = $tokens[$stack_ptr]['scope_opener'];
        // Check positions of the extends and implements keywords.
        $compare_token = $stack_ptr;
        $compare_type = 'name';
        if ($tokens[$stack_ptr]['code'] === T_ANON_CLASS) {
            if (isset($tokens[$stack_ptr]['parenthesis_opener']) === true) {
                $compare_token = $tokens[$stack_ptr]['parenthesis_closer'];
                $compare_type = 'closing parenthesis';
            } else {
                $compare_type = 'keyword';
            }
        }
        foreach (['extends', 'implements'] as $keyword_type) {
            $keyword = $phpcs_file->find_next(constant('T_' . strtoupper($keyword_type)), $compare_token + 1, $opening_brace);
            if ($keyword !== false) {
                if ($tokens[$keyword]['line'] !== $tokens[$compare_token]['line']) {
                    $error = 'The ' . $keyword_type . ' keyword must be on the same line as the %s ' . $compare_type;
                    $data = [$stack_ptr_type];
                    $fix = $phpcs_file->add_fixable_error($error, $keyword, ucfirst($keyword_type) . 'Line', $data);
                    if ($fix === true) {
                        $phpcs_file->fixer->begin_changeset();
                        $comments = [];
                        for ($i = $compare_token + 1; $i < $keyword; ++$i) {
                            if ($tokens[$i]['code'] === T_COMMENT) {
                                $comments[] = trim($tokens[$i]['content']);
                            }
                            if ($tokens[$i]['code'] === T_WHITESPACE || $tokens[$i]['code'] === T_COMMENT) {
                                $phpcs_file->fixer->replace_token($i, ' ');
                            }
                        }
                        $phpcs_file->fixer->add_content($compare_token, ' ');
                        if (empty($comments) === false) {
                            $i = $keyword;
                            while ($tokens[$i + 1]['line'] === $tokens[$keyword]['line']) {
                                ++$i;
                            }
                            $phpcs_file->fixer->add_content_before($i, ' ' . implode(' ', $comments));
                        }
                        $phpcs_file->fixer->end_changeset();
                    }
                    //end if
                } else {
                    // Check the whitespace before. Whitespace after is checked
                    // later by looking at the whitespace before the first class name
                    // in the list.
                    $gap = $tokens[$keyword - 1]['length'];
                    if ($gap !== 1) {
                        $error = 'Expected 1 space before ' . $keyword_type . ' keyword; %s found';
                        $data = [$gap];
                        $fix = $phpcs_file->add_fixable_error($error, $keyword, 'SpaceBefore' . ucfirst($keyword_type), $data);
                        if ($fix === true) {
                            $phpcs_file->fixer->replace_token($keyword - 1, ' ');
                        }
                    }
                }
                //end if
            }
            //end if
        }
        //end foreach
        // Check each of the extends/implements class names. If the extends/implements
        // keyword is the last content on the line, it means we need to check for
        // the multi-line format, so we do not include the class names
        // from the extends/implements list in the following check.
        // Note that classes can only extend one other class, so they can't use a
        // multi-line extends format, whereas an interface can extend multiple
        // other interfaces, and so uses a multi-line extends format.
        if ($tokens[$stack_ptr]['code'] === T_INTERFACE) {
            $keyword_token_type = T_EXTENDS;
        } else {
            $keyword_token_type = T_IMPLEMENTS;
        }
        $implements = $phpcs_file->find_next($keyword_token_type, $stack_ptr + 1, $opening_brace);
        $multi_line_implements = false;
        if ($implements !== false) {
            $prev = $phpcs_file->find_previous(Tokens::$empty_tokens, $opening_brace - 1, $implements, true);
            if ($tokens[$prev]['line'] !== $tokens[$implements]['line']) {
                $multi_line_implements = true;
            }
        }
        $find = [T_STRING, $keyword_token_type];
        if ($class_name !== null) {
            $start = $class_name;
        } elseif (isset($tokens[$stack_ptr]['parenthesis_closer']) === true) {
            $start = $tokens[$stack_ptr]['parenthesis_closer'];
        } else {
            $start = $stack_ptr;
        }
        $class_names = [];
        $next_class = $phpcs_file->find_next($find, $start + 2, $opening_brace - 1);
        while ($next_class !== false) {
            $class_names[] = $next_class;
            $next_class = $phpcs_file->find_next($find, $next_class + 1, $opening_brace - 1);
        }
        $class_count = count($class_names);
        $checking_implements = false;
        $implements_token = null;
        foreach ($class_names as $n => $class_name) {
            if ($tokens[$class_name]['code'] === $keyword_token_type) {
                $checking_implements = true;
                $implements_token = $class_name;
                continue;
            }
            if ($checking_implements === true && $multi_line_implements === true && ($tokens[$class_name - 1]['code'] !== T_NS_SEPARATOR || $tokens[$class_name - 2]['code'] !== T_STRING)) {
                $prev = $phpcs_file->find_previous([T_NS_SEPARATOR, T_WHITESPACE], $class_name - 1, $implements, true);
                if ($prev === $implements_token && $tokens[$class_name]['line'] !== $tokens[$prev]['line'] + 1) {
                    if ($keyword_token_type === T_EXTENDS) {
                        $error = 'The first item in a multi-line extends list must be on the line following the extends keyword';
                        $fix = $phpcs_file->add_fixable_error($error, $class_name, 'FirstExtendsInterfaceSameLine');
                    } else {
                        $error = 'The first item in a multi-line implements list must be on the line following the implements keyword';
                        $fix = $phpcs_file->add_fixable_error($error, $class_name, 'FirstInterfaceSameLine');
                    }
                    if ($fix === true) {
                        $phpcs_file->fixer->begin_changeset();
                        for ($i = $prev + 1; $i < $class_name; $i++) {
                            if ($tokens[$i]['code'] !== T_WHITESPACE) {
                                break;
                            }
                            $phpcs_file->fixer->replace_token($i, '');
                        }
                        $phpcs_file->fixer->add_newline($prev);
                        $phpcs_file->fixer->end_changeset();
                    }
                } elseif ($tokens[$prev]['line'] !== $tokens[$class_name]['line'] - 1) {
                    if ($keyword_token_type === T_EXTENDS) {
                        $error = 'Only one interface may be specified per line in a multi-line extends declaration';
                        $fix = $phpcs_file->add_fixable_error($error, $class_name, 'ExtendsInterfaceSameLine');
                    } else {
                        $error = 'Only one interface may be specified per line in a multi-line implements declaration';
                        $fix = $phpcs_file->add_fixable_error($error, $class_name, 'InterfaceSameLine');
                    }
                    if ($fix === true) {
                        $phpcs_file->fixer->begin_changeset();
                        for ($i = $prev + 1; $i < $class_name; $i++) {
                            if ($tokens[$i]['code'] !== T_WHITESPACE) {
                                break;
                            }
                            $phpcs_file->fixer->replace_token($i, '');
                        }
                        $phpcs_file->fixer->add_newline($prev);
                        $phpcs_file->fixer->end_changeset();
                    }
                } else {
                    $prev = $phpcs_file->find_previous(T_WHITESPACE, $class_name - 1, $implements);
                    if ($tokens[$prev]['line'] !== $tokens[$class_name]['line']) {
                        $found = 0;
                    } else {
                        $found = $tokens[$prev]['length'];
                    }
                    $expected = $class_indent + $this->indent;
                    if ($found !== $expected) {
                        $error = 'Expected %s spaces before interface name; %s found';
                        $data = [$expected, $found];
                        $fix = $phpcs_file->add_fixable_error($error, $class_name, 'InterfaceWrongIndent', $data);
                        if ($fix === true) {
                            $padding = str_repeat(' ', $expected);
                            if ($found === 0) {
                                $phpcs_file->fixer->add_content($prev, $padding);
                            } else {
                                $phpcs_file->fixer->replace_token($prev, $padding);
                            }
                        }
                    }
                }
                //end if
            } elseif ($tokens[$class_name - 1]['code'] !== T_NS_SEPARATOR || $tokens[$class_name - 2]['code'] !== T_STRING) {
                // Not part of a longer fully qualified class name.
                if ($tokens[$class_name - 1]['code'] === T_COMMA || $tokens[$class_name - 1]['code'] === T_NS_SEPARATOR && $tokens[$class_name - 2]['code'] === T_COMMA) {
                    $error = 'Expected 1 space before "%s"; 0 found';
                    $data = [$tokens[$class_name]['content']];
                    $fix = $phpcs_file->add_fixable_error($error, $next_comma + 1, 'NoSpaceBeforeName', $data);
                    if ($fix === true) {
                        $phpcs_file->fixer->add_content_before($next_comma + 1, ' ');
                    }
                } else {
                    if ($tokens[$class_name - 1]['code'] === T_NS_SEPARATOR) {
                        $prev = $class_name - 2;
                    } else {
                        $prev = $class_name - 1;
                    }
                    $last = $phpcs_file->find_previous(T_WHITESPACE, $prev, null, true);
                    $content = $phpcs_file->get_tokens_as_string($last + 1, $prev - $last);
                    if ($content !== ' ') {
                        $found = strlen($content);
                        $error = 'Expected 1 space before "%s"; %s found';
                        $data = [$tokens[$class_name]['content'], $found];
                        $fix = $phpcs_file->add_fixable_error($error, $class_name, 'SpaceBeforeName', $data);
                        if ($fix === true) {
                            if ($tokens[$prev]['code'] === T_WHITESPACE) {
                                $phpcs_file->fixer->begin_changeset();
                                $phpcs_file->fixer->replace_token($prev, ' ');
                                while ($tokens[--$prev]['code'] === T_WHITESPACE) {
                                    $phpcs_file->fixer->replace_token($prev, ' ');
                                }
                                $phpcs_file->fixer->end_changeset();
                            } else {
                                $phpcs_file->fixer->add_content($prev, ' ');
                            }
                        }
                    }
                    //end if
                }
                //end if
            }
            //end if
            if ($checking_implements === true && $tokens[$class_name + 1]['code'] !== T_NS_SEPARATOR && $tokens[$class_name + 1]['code'] !== T_COMMA) {
                if ($n !== $class_count - 1) {
                    // This is not the last class name, and the comma
                    // is not where we expect it to be.
                    if ($tokens[$class_name + 2]['code'] !== $keyword_token_type) {
                        $error = 'Expected 0 spaces between "%s" and comma; %s found';
                        $data = [$tokens[$class_name]['content'], $tokens[$class_name + 1]['length']];
                        $fix = $phpcs_file->add_fixable_error($error, $class_name, 'SpaceBeforeComma', $data);
                        if ($fix === true) {
                            $phpcs_file->fixer->replace_token($class_name + 1, '');
                        }
                    }
                }
                $next_comma = $phpcs_file->find_next(T_COMMA, $class_name);
            } else {
                $next_comma = $class_name + 1;
            }
            //end if
        }
        //end foreach
    }
    //end processOpen()
    /**
     * Processes the closing section of a class declaration.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token
     *                                               in the stack passed in $tokens.
     *
     * @return void
     */
    public function process_close(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->get_tokens();
        // Check that the closing brace comes right after the code body.
        $close_brace = $tokens[$stack_ptr]['scope_closer'];
        $prev_content = $phpcs_file->find_previous(T_WHITESPACE, $close_brace - 1, null, true);
        if ($prev_content !== $tokens[$stack_ptr]['scope_opener'] && $tokens[$prev_content]['line'] !== $tokens[$close_brace]['line'] - 1) {
            $error = 'The closing brace for the %s must go on the next line after the body';
            $data = [$tokens[$stack_ptr]['content']];
            $fix = $phpcs_file->add_fixable_error($error, $close_brace, 'CloseBraceAfterBody', $data);
            if ($fix === true) {
                $phpcs_file->fixer->begin_changeset();
                for ($i = $prev_content + 1; $i < $close_brace; $i++) {
                    $phpcs_file->fixer->replace_token($i, '');
                }
                if (strpos($tokens[$prev_content]['content'], $phpcs_file->eol_char) === false) {
                    $phpcs_file->fixer->replace_token($close_brace, $phpcs_file->eol_char . $tokens[$close_brace]['content']);
                }
                $phpcs_file->fixer->end_changeset();
            }
        }
        //end if
        if ($tokens[$stack_ptr]['code'] !== T_ANON_CLASS) {
            // Check the closing brace is on it's own line, but allow
            // for comments like "//end class".
            $ignore_tokens = Tokens::$phpcs_comment_tokens;
            $ignore_tokens[] = T_WHITESPACE;
            $ignore_tokens[] = T_COMMENT;
            $ignore_tokens[] = T_SEMICOLON;
            $ignore_tokens[] = T_COMMA;
            $next_content = $phpcs_file->find_next($ignore_tokens, $close_brace + 1, null, true);
            if ($tokens[$next_content]['content'] !== $phpcs_file->eol_char && $tokens[$next_content]['line'] === $tokens[$close_brace]['line']) {
                $type = strtolower($tokens[$stack_ptr]['content']);
                $error = 'Closing %s brace must be on a line by itself';
                $data = [$type];
                $phpcs_file->add_error($error, $close_brace, 'CloseBraceSameLine', $data);
            }
        }
    }
    //end processClose()
}
//end class