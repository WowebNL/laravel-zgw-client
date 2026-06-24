<?php

declare(strict_types=1);

/**
 * Download the ZGW OpenAPI specs declared in tests/Contract/releases.json into
 * tests/fixtures/specs/{release}/{component}.yaml so the Contract test suite can run offline.
 *
 * Exits non-zero on any failed or 404 download so CI fails loudly instead of testing a stale
 * or partial spec set. Run with: php scripts/fetch-specs.php [--release=1.7] [--force]
 */
$root = dirname(__DIR__);
$matrixFile = $root.'/tests/Contract/releases.json';
$specsDir = $root.'/tests/fixtures/specs';

$options = getopt('', ['release::', 'force']);
$onlyRelease = $options['release'] ?? null;
$force = array_key_exists('force', $options);

if (! is_file($matrixFile)) {
    fwrite(STDERR, "Release matrix not found: {$matrixFile}\n");
    exit(1);
}

/** @var array{releases: array<string, array{label?: string, components: array<string, array{version: string, url: string}>}>} $matrix */
$matrix = json_decode((string) file_get_contents($matrixFile), true, 512, JSON_THROW_ON_ERROR);

$failures = [];
$downloaded = 0;
$skipped = 0;

foreach ($matrix['releases'] as $release => $definition) {
    if ($onlyRelease !== null && (string) $onlyRelease !== (string) $release) {
        continue;
    }

    foreach ($definition['components'] as $component => $spec) {
        $target = "{$specsDir}/{$release}/{$component}.yaml";

        if (! $force && is_file($target) && filesize($target) > 0) {
            $skipped++;

            continue;
        }

        if (! is_dir(dirname($target))) {
            mkdir(dirname($target), 0o775, true);
        }

        [$ok, $error] = downloadTo($spec['url'], $target);

        if (! $ok) {
            $failures[] = "{$release}/{$component} ({$spec['version']}): {$error} <- {$spec['url']}";

            continue;
        }

        $downloaded++;
        fwrite(STDOUT, "fetched {$release}/{$component} ({$spec['version']})\n");
    }
}

fwrite(STDOUT, "\nDownloaded {$downloaded}, skipped {$skipped} (already present).\n");

if ($failures !== []) {
    fwrite(STDERR, "\nFailed to fetch ".count($failures)." spec(s):\n  - ".implode("\n  - ", $failures)."\n");
    exit(1);
}

/**
 * Download a URL to a file. Returns [success, errorMessage].
 *
 * @return array{0: bool, 1: string}
 */
function downloadTo(string $url, string $target): array
{
    $ch = curl_init($url);
    $fh = fopen($target, 'wb');

    if ($ch === false || $fh === false) {
        return [false, 'could not initialise download'];
    }

    curl_setopt_array($ch, [
        CURLOPT_FILE => $fh,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_FAILONERROR => true,
        CURLOPT_USERAGENT => 'laravel-zgw-client-spec-fetcher',
    ]);

    $success = curl_exec($ch) !== false;
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    fclose($fh);

    if (! $success || $status >= 400) {
        @unlink($target);

        return [false, $curlError !== '' ? $curlError : "HTTP {$status}"];
    }

    if (filesize($target) === 0) {
        @unlink($target);

        return [false, 'empty response body'];
    }

    return [true, ''];
}
