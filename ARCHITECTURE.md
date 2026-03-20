# Architecture: PHP_CodeSniffer

## Purpose

PHP_CodeSniffer (PHPCS) detects and automatically fixes coding standard violations in PHP, JavaScript, and CSS files. It ships with multiple built-in standards (PEAR, PSR-1, PSR-2, PSR-12, Squiz, Zend) and supports custom standards.

## Directory Structure

```
src/
  Config.php             CLI argument parsing and global configuration
  Runner.php             Main entry point; orchestrates file processing
  Ruleset.php            Loads and merges standard XML rulesets
  Tokenizers/            Language-specific tokenisers (PHP, JS, CSS)
  Files/
    File.php             Per-file token stream + violation collection
    DummyFile.php        In-memory file for API usage without filesystem
  Sniffs/                Interface + abstract base for sniff implementations
  Fixer.php              Applies auto-fix patches from sniff fixers
  Reports/               Output formatters (full, summary, checkstyle, JSON, diff, etc.)
  Generators/            HTML and text documentation generators for standards
  Standards/
    Generic/             Generic language-agnostic sniffs
    PEAR/                PEAR coding standard sniffs
    PSR1/ PSR2/ PSR12/   PSR standard sniffs
    Squiz/               Squiz coding standard sniffs
    Zend/                Zend coding standard sniffs
    MySource/            Internal Squiz standard (reference implementation)
bin/
  phpcs                  CLI entry point for checking
  phpcbf                 CLI entry point for fixing
```

## Key Design Decisions

- **Token stream model**: Each file is fully tokenised before sniffs run. Sniffs register interest in specific token types and receive token position + context on each match.
- **Ruleset XML**: Standards are defined as XML files that include/exclude sniffs with optional property overrides. This allows composition of multiple standards.
- **Fixer integration**: Each sniff can implement `process()` for detection and `fix()` for auto-correction. The fixer applies changes in multiple passes until no more violations are fixable.
- **Parallel processing**: PHPCS supports `--parallel` to process files across multiple worker processes.

## Extension Points

- Implement `Sniff` interface to create a custom sniff.
- Create a `ruleset.xml` to define a custom standard by including/excluding existing sniffs.
- Implement `Report` interface to add a custom output format.

## Dependency Flow

```
CLI: phpcs path/to/file --standard=PSR12
  -> Config (parse args)
  -> Ruleset (load PSR12 sniffs + rules)
  -> Runner -> File::process()
    -> Tokenizer::tokenize(source)
    -> foreach token: dispatch to registered Sniffs
    -> Sniff::process(File, stackPtr) -> addError/addWarning
  -> Reports::generateReport()
```
