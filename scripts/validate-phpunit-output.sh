#!/usr/bin/env bash

strip_ansi() {
  sed 's/\x1b\[[0-9;]*[a-zA-Z]//g; s/\x1b\][^\x07]*\x07//g'
}

validate_phpunit_output() {
  local output_file="$1"
  local stripped
  stripped="$(strip_ansi < "${output_file}")"

  if printf '%s\n' "${stripped}" | grep -Eq '(^| )Warning:'; then
    printf '%s\n' "PHPUnit output contains Warning:"
    return 1
  fi
  if printf '%s\n' "${stripped}" | grep -Eq '(^| )Deprecated:'; then
    printf '%s\n' "PHPUnit output contains Deprecated:"
    return 1
  fi
  if printf '%s\n' "${stripped}" | grep -Eq '(^| )Notice:'; then
    printf '%s\n' "PHPUnit output contains Notice:"
    return 1
  fi
  if printf '%s\n' "${stripped}" | grep -Eq '\bWarnings:'; then
    printf '%s\n' "PHPUnit summary contains Warnings:"
    return 1
  fi
  if printf '%s\n' "${stripped}" | grep -Eq '\bRisky:'; then
    printf '%s\n' "PHPUnit summary contains Risky:"
    return 1
  fi
  if printf '%s\n' "${stripped}" | grep -Eq '\bIncomplete:'; then
    printf '%s\n' "PHPUnit summary contains Incomplete:"
    return 1
  fi
  if printf '%s\n' "${stripped}" | grep -Eq '^OK \([0-9]+ tests?, [0-9]+ assertions?\)$'; then
    printf '%s\n' "${stripped}" | grep -Eo '^OK \([0-9]+ tests?, [0-9]+ assertions?\)$' | tail -n 1
    return 0
  fi
  if printf '%s\n' "${stripped}" | grep -Fq 'OK, but incomplete, skipped, or risky tests!'; then
    if ! printf '%s\n' "${stripped}" | grep -Eq '^Tests: [0-9]+, Assertions: [0-9]+, Skipped: [0-9]+'; then
      printf '%s\n' "PHPUnit output missing Tests/Assertions/Skipped summary line"
      return 1
    fi
    printf '%s\n' "${stripped}" | grep -E '^Tests: [0-9]+, Assertions: [0-9]+, Skipped: [0-9]+' | tail -n 1
    printf '%s\n' "OK, but incomplete, skipped, or risky tests!"
    return 0
  fi
  printf '%s\n' 'PHPUnit output is missing strict success summary'
  return 1
}
