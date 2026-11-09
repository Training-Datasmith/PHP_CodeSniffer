<?php

/**
 * Verify org-cluster packages match index SHAs and aliases (empty map until honest aliases exist).
 */

$expected = array();

$root = dirname(__DIR__);
$installedPhp = $root.'/vendor/composer/installed.php';
$installedJson = $root.'/vendor/composer/installed.json';

/**
 * @param array<string, array{alias?: string, reference: string}> $map
 * @param array<string, array<string, mixed>>                   $installedPhpVersions
 * @param array<string, array{reference: string}>               $installedJsonRefs
 *
 * @return bool
 */
function check_cluster_pins(array $map, array $installedPhpVersions, array $installedJsonRefs)
{
    $failed = false;

    foreach ($map as $name => $want) {
        if (!isset($installedPhpVersions[$name])) {
            fwrite(STDERR, "check-cluster-pins: {$name} is not installed (installed.php).\n");
            $failed = true;
            continue;
        }

        $pkg = $installedPhpVersions[$name];
        if (isset($want['alias'])) {
            $aliases = isset($pkg['aliases']) ? $pkg['aliases'] : array();
            if (!in_array($want['alias'], $aliases, true)) {
                fwrite(STDERR, sprintf(
                    "check-cluster-pins: %s alias mismatch (expected %s in aliases %s).\n",
                    $name,
                    $want['alias'],
                    json_encode($aliases)
                ));
                $failed = true;
            }
        }

        $reference = isset($pkg['reference']) ? $pkg['reference'] : null;
        if (!is_string($reference) || $reference === '') {
            fwrite(STDERR, "check-cluster-pins: {$name} has no reference in installed.php.\n");
            $failed = true;
        } elseif (!hash_equals(strtolower($want['reference']), strtolower($reference))) {
            fwrite(STDERR, sprintf(
                "check-cluster-pins: %s installed.php reference mismatch (expected %s, got %s).\n",
                $name,
                $want['reference'],
                $reference
            ));
            $failed = true;
        }

        if (!isset($installedJsonRefs[$name])) {
            fwrite(STDERR, "check-cluster-pins: {$name} is not installed (installed.json).\n");
            $failed = true;
            continue;
        }

        $jsonRef = $installedJsonRefs[$name]['reference'];
        if (!hash_equals(strtolower($want['reference']), strtolower($jsonRef))) {
            fwrite(STDERR, sprintf(
                "check-cluster-pins: %s installed.json source.reference mismatch (expected %s, got %s).\n",
                $name,
                $want['reference'],
                $jsonRef
            ));
            $failed = true;
        }
    }

    return $failed === false;
}

/**
 * @return array{versions: array<string, array<string, mixed>>}|null
 */
function load_installed_php_versions($path)
{
    if (!is_readable($path)) {
        return null;
    }

    /** @var array{versions?: array<string, array<string, mixed>>} $installed */
    $installed = require $path;

    return isset($installed['versions']) ? $installed['versions'] : array();
}

/**
 * @return array<string, array{reference: string}>
 */
function load_installed_json_refs($path)
{
    if (!is_readable($path)) {
        return array();
    }

    $raw = file_get_contents($path);
    if ($raw === false) {
        return array();
    }

    /** @var array{packages?: list<array<string, mixed>>, 'packages-dev'?: list<array<string, mixed>>} $data */
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return array();
    }

    $packages = array();
    if (isset($data['packages']) && is_array($data['packages'])) {
        $packages = array_merge($packages, $data['packages']);
    }
    if (isset($data['packages-dev']) && is_array($data['packages-dev'])) {
        $packages = array_merge($packages, $data['packages-dev']);
    }

    $out = array();
    foreach ($packages as $package) {
        if (!isset($package['name'], $package['source']['reference'])) {
            continue;
        }
        $out[$package['name']] = array(
            'reference' => strtolower((string) $package['source']['reference']),
        );
    }

    return $out;
}

$demoMap = array(
    'example/cluster-pin-demo' => array(
        'alias' => '1.2.3',
        'reference' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
    ),
);

$demoPhpVersions = array(
    'example/cluster-pin-demo' => array(
        'reference' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        'aliases' => array('1.2.3'),
    ),
);

$demoJsonRefs = array(
    'example/cluster-pin-demo' => array(
        'reference' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
    ),
);

if (check_cluster_pins($demoMap, $demoPhpVersions, $demoJsonRefs) !== true) {
    fwrite(STDERR, "check-cluster-pins: in-memory control demo failed.\n");
    exit(1);
}

$badPhp = $demoPhpVersions;
$badPhp['example/cluster-pin-demo']['reference'] = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
if (check_cluster_pins($demoMap, $badPhp, $demoJsonRefs) !== false) {
    fwrite(STDERR, "check-cluster-pins: wrong-SHA demo did not fail on installed.php reference.\n");
    exit(1);
}

$badJson = $demoJsonRefs;
$badJson['example/cluster-pin-demo']['reference'] = 'cccccccccccccccccccccccccccccccccccccccc';
if (check_cluster_pins($demoMap, $demoPhpVersions, $badJson) !== false) {
    fwrite(STDERR, "check-cluster-pins: wrong-SHA demo did not fail on installed.json reference.\n");
    exit(1);
}

if (count($expected) === 0) {
    fwrite(STDOUT, "check-cluster-pins: no org cluster packages configured; in-memory wrong-SHA demos passed.\n");
    exit(0);
}

if (!is_readable($installedPhp)) {
    fwrite(STDERR, "check-cluster-pins: missing {$installedPhp}; run composer update first.\n");
    exit(1);
}

if (!is_readable($installedJson)) {
    fwrite(STDERR, "check-cluster-pins: missing {$installedJson}; run composer update first.\n");
    exit(1);
}

$versions = load_installed_php_versions($installedPhp);
$jsonRefs = load_installed_json_refs($installedJson);

if (!is_array($versions)) {
    fwrite(STDERR, "check-cluster-pins: could not read versions from installed.php.\n");
    exit(1);
}

if (!check_cluster_pins($expected, $versions, $jsonRefs)) {
    exit(1);
}

fwrite(STDOUT, "check-cluster-pins: all cluster packages match expected SHAs and aliases.\n");
exit(0);
