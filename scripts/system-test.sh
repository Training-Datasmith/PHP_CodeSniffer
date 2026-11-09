#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

USE_SUDO_DOCKER="${USE_SUDO_DOCKER:-0}"
# 7.2.34-cli-buster lints and loads autoload.php, but populate_token_listeners() fatals on
# Php_code_Sniffer\Files\File::__construct() arity (ArgumentCountError at Ruleset.php:1134).
# Lowest digest-pinned image that runs the full patched suite: PHP 8.2 + PHPUnit ^9.6.33.
PHP_IMAGE='php:8.2-cli-bookworm@sha256:26b388d187a5e592ef5e02c14d0f92dd9c761894ff0a5fe338183196471c4b4e'
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

echo "System test passed."
