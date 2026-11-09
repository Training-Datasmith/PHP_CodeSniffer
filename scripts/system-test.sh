#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

USE_SUDO_DOCKER="${USE_SUDO_DOCKER:-0}"
# Lowest digest-pinned image probed from PHP 7.2-cli-buster upward (see PHP_IMAGE_REASON).
PHP_IMAGE='php:7.3-cli-buster@sha256:36ddae37e12040100b77ed3054b6a43e4c68e3149553983d6025b5824e7c3c62'
PHP_IMAGE_REASON='PHP 7.2-cli-buster finishes with Warnings: 148 and Skipped: 11 (suite gate requires Skipped: 10 and no summary warnings). PHP 7.3+ runs the full 1847-test suite green with Commented_Out_Code_Sniff ini_set error_reporting passed as strings.'
COMPOSER_URL='https://github.com/composer/composer/releases/download/2.11.1/composer.phar'
COMPOSER_SHA256='6f1b891e1d4927d62740276c540fb490e47689e19e102c49e8da849794882f8c'
LOG_FILE="${ROOT}/.system-test/phpunit.out"

docker_cmd() {
  if [[ "${USE_SUDO_DOCKER}" == "1" ]]; then
    sudo docker "$@"
  else
    docker "$@"
  fi
}

mkdir -p "${ROOT}/.system-test"

read -r -d '' INNER <<'EOF' || true
set -euo pipefail
export DEBIAN_FRONTEND=noninteractive
export COMPOSER_ALLOW_SUPERUSER=1
export COMPOSER_NO_INTERACTION=1

configure_apt_https() {
  if [[ -f /etc/apt/sources.list.d/debian.sources ]]; then
    sed -i \
      -e 's|http://deb.debian.org|https://deb.debian.org|g' \
      -e 's|http://security.debian.org|https://security.debian.org|g' \
      /etc/apt/sources.list.d/debian.sources
  fi
  if [[ -f /etc/apt/sources.list ]]; then
    sed -i \
      -e 's|http://deb.debian.org|https://deb.debian.org|g' \
      -e 's|http://security.debian.org|https://security.debian.org|g' \
      /etc/apt/sources.list
  fi
  if [[ -f /etc/os-release ]]; then
    # shellcheck source=/dev/null
    . /etc/os-release
    if [[ "${VERSION_ID:-}" == "10" ]]; then
      cat >/etc/apt/sources.list <<'SOURCES'
deb https://archive.debian.org/debian buster main
deb https://archive.debian.org/debian-security buster/updates main
SOURCES
    fi
  fi
}

configure_apt_https
apt-get update
apt-get install -y --no-install-recommends git unzip ca-certificates curl
rm -rf /var/lib/apt/lists/*

cd /tmp
curl -fsSL -o composer.phar "${COMPOSER_URL}"
echo "${COMPOSER_SHA256}  composer.phar" | sha256sum -c -
install -m 0755 composer.phar /usr/local/bin/composer
composer --version

cd /workspace
git config --global --add safe.directory /workspace

php scripts/check-cluster-pins.php
php scripts/check-rename-consistency.php

composer update --no-interaction --prefer-dist --no-progress
composer audit --no-interaction

vendor/bin/phpunit -c phpunit.xml.dist --colors=never --verbose tests/All_Tests.php 2>&1 | tee /workspace/.system-test/phpunit.out
EOF

# shellcheck disable=SC2016
INNER="${INNER//\$\{COMPOSER_URL\}/${COMPOSER_URL}}"
INNER="${INNER//\$\{COMPOSER_SHA256\}/${COMPOSER_SHA256}}"

docker_cmd pull "${PHP_IMAGE}"

docker_cmd run --rm \
  -v "${ROOT}:/workspace" \
  -w /workspace \
  "${PHP_IMAGE}" \
  bash -lc "${INNER}"

# shellcheck source=scripts/validate-phpunit-output.sh
source "${ROOT}/scripts/validate-phpunit-output.sh"

if ! validate_phpunit_output "${LOG_FILE}"; then
  exit 1
fi

docker_cmd run --rm \
  -v "${ROOT}:/workspace" \
  -w /workspace \
  "${PHP_IMAGE}" \
  php /workspace/scripts/check-phpunit-gate.php /workspace/.system-test/phpunit.out

echo "System test passed. PHP image: ${PHP_IMAGE}"
echo "Reason: ${PHP_IMAGE_REASON}"
