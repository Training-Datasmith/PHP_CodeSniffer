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
  if printf '%s\n' "${stripped}" | grep -Eq '\bFailures:'; then
    printf '%s\n' "PHPUnit summary contains Failures:"
    return 1
  fi
  if printf '%s\n' "${stripped}" | grep -Eq '\bErrors:'; then
    printf '%s\n' "PHPUnit summary contains Errors:"
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

  local summary_line
  summary_line="$(printf '%s\n' "${stripped}" | grep -E '^Tests: [0-9]+, Assertions: [0-9]+, Skipped: [0-9]+\.$' | tail -n 1)"
  if [[ -z "${summary_line}" ]]; then
    printf '%s\n' "PHPUnit output is missing anchored Tests/Assertions/Skipped summary line"
    return 1
  fi

  if printf '%s\n' "${stripped}" | grep -qFx 'OK, but incomplete, skipped, or risky tests!'; then
    printf '%s\n' "${summary_line}"
    printf '%s\n' "OK, but incomplete, skipped, or risky tests!"
    return 0
  fi

  if printf '%s\n' "${stripped}" | grep -qFx 'OK, but incomplete, skipped, or useless tests!'; then
    printf '%s\n' "${summary_line}"
    printf '%s\n' "OK, but incomplete, skipped, or useless tests!"
    return 0
  fi

  local ok_line
  ok_line="$(printf '%s\n' "${stripped}" | grep -E '^OK \([0-9]+ tests?, [0-9]+ assertions?\)$' | tail -n 1)"
  if [[ -n "${ok_line}" ]]; then
    printf '%s\n' "${ok_line}"
    return 0
  fi

  printf '%s\n' 'PHPUnit output is missing strict success summary'
  return 1
}
