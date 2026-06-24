<?php

declare(strict_types=1);

/**
 * Detect newly published ZGW OpenAPI spec versions.
 *
 * The gemma-zaken repository does not use GitHub Releases for the standard (its Releases API is
 * empty and its tags are stale), so the reliable signal for a new (pre)release is a new version
 * folder appearing under api-specificatie/{component}. This script builds the current inventory of
 * those folders via the GitHub API and diffs it against a committed snapshot.
 *
 * Modes:
 *   (default)         Print a human + machine summary of added/removed versions. No file writes.
 *   --write-snapshot  Overwrite .github/zgw-spec-snapshot.json with the current inventory.
 *   --apply           Diff, then (when there are additions) update the snapshot, scaffold the new
 *                     versions into tests/Contract/releases.json, and write a PR/issue body to
 *                     build/new-specs-report.md.
 *
 * Auth: set GITHUB_TOKEN in the environment to lift the unauthenticated API rate limit.
 */
const REPO = 'VNG-Realisatie/gemma-zaken';
const API_BASE = 'https://api.github.com/repos/'.REPO.'/contents/api-specificatie';
const RAW_BASE = 'https://raw.githubusercontent.com/'.REPO.'/master/api-specificatie';

/** Core ZGW components, mapped to the friendly name used in releases.json (null = unsupported). */
const COMPONENTS = [
    'zrc' => 'zaken',
    'ztc' => 'catalogi',
    'drc' => 'documenten',
    'brc' => 'besluiten',
    'ac' => null,
    'nrc' => null,
];

$root = dirname(__DIR__);
$snapshotFile = $root.'/.github/zgw-spec-snapshot.json';
$matrixFile = $root.'/tests/Contract/releases.json';
$reportFile = $root.'/build/new-specs-report.md';

$mode = $argv[1] ?? '';

$inventory = buildInventory();

if ($mode === '--write-snapshot') {
    writeJson($snapshotFile, ['versions' => $inventory]);
    fwrite(STDOUT, 'Wrote snapshot with '.count($inventory)." spec versions.\n");
    exit(0);
}

$snapshot = is_file($snapshotFile)
    ? (json_decode((string) file_get_contents($snapshotFile), true, 512, JSON_THROW_ON_ERROR)['versions'] ?? [])
    : [];

$added = array_values(array_diff($inventory, $snapshot));
$removed = array_values(array_diff($snapshot, $inventory));

sort($added);
sort($removed);

fwrite(STDOUT, 'Current spec versions: '.count($inventory)."\n");
fwrite(STDOUT, 'Added: '.count($added)."\n");
foreach ($added as $entry) {
    fwrite(STDOUT, "  + {$entry}\n");
}
foreach ($removed as $entry) {
    fwrite(STDOUT, "  - {$entry}\n");
}

// Machine-readable line for the GitHub Actions step to branch on.
fwrite(STDOUT, 'added_count='.count($added)."\n");

if ($mode !== '--apply' || $added === []) {
    exit(0);
}

// --apply: update the snapshot, scaffold the matrix, and write the report body.
writeJson($snapshotFile, ['versions' => $inventory]);
scaffoldMatrix($matrixFile, $added);
writeReport($reportFile, $added, $removed);

fwrite(STDOUT, "Applied: snapshot updated, matrix scaffolded, report written to {$reportFile}.\n");
exit(0);

/**
 * Build the set of available spec versions as "component/path" identifiers.
 *
 * @return list<string>
 */
function buildInventory(): array
{
    $versions = [];

    foreach (array_keys(COMPONENTS) as $component) {
        $entries = githubContents($component);

        foreach ($entries as $entry) {
            $name = (string) ($entry['name'] ?? '');
            $type = (string) ($entry['type'] ?? '');

            if ($type === 'file' && $name === 'openapi.yaml') {
                // Single top-level spec (ac, nrc).
                $versions[] = $component;

                continue;
            }

            if ($type !== 'dir') {
                continue;
            }

            if (preg_match('/^\d+\.\d+\.\d+$/', $name) === 1) {
                // Top-level patch folder (for example brc/1.0.2).
                $versions[] = "{$component}/{$name}";

                continue;
            }

            if (preg_match('/^\d+\.\d+\.x$/', $name) === 1) {
                foreach (githubContents("{$component}/{$name}") as $sub) {
                    $subName = (string) ($sub['name'] ?? '');

                    if (($sub['type'] ?? '') === 'dir' && preg_match('/^\d+\.\d+\.\d+$/', $subName) === 1) {
                        $versions[] = "{$component}/{$name}/{$subName}";
                    }

                    if (($sub['type'] ?? '') === 'file' && $subName === 'openapi.yaml') {
                        // Minor folder that holds the spec directly (for example brc/1.1.x).
                        $versions[] = "{$component}/{$name}";
                    }
                }
            }
        }
    }

    $versions = array_values(array_unique($versions));
    sort($versions);

    return $versions;
}

/**
 * @return list<array<string, mixed>>
 */
function githubContents(string $path): array
{
    $headers = [
        'User-Agent: laravel-zgw-client-spec-watch',
        'Accept: application/vnd.github+json',
    ];

    $token = getenv('GITHUB_TOKEN');
    if (is_string($token) && $token !== '') {
        $headers[] = "Authorization: Bearer {$token}";
    }

    $context = stream_context_create(['http' => [
        'method' => 'GET',
        'header' => implode("\r\n", $headers),
        'ignore_errors' => true,
        'timeout' => 30,
    ]]);

    $body = @file_get_contents(API_BASE.'/'.$path, false, $context);
    $status = httpStatus($http_response_header ?? []);

    // Abort rather than risk writing a partial inventory (which would later surface phantom
    // "added" / "removed" diffs). A 404 on a component path is a genuine empty listing.
    if ($body === false || ($status !== 200 && $status !== 404)) {
        throw new RuntimeException("GitHub API listing for [{$path}] failed (HTTP {$status}). ".
            'Set GITHUB_TOKEN to avoid rate limiting.');
    }

    $decoded = json_decode((string) $body, true);

    return is_array($decoded) && array_is_list($decoded) ? $decoded : [];
}

/**
 * @param  list<string>  $responseHeaders
 */
function httpStatus(array $responseHeaders): int
{
    foreach ($responseHeaders as $header) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $m) === 1) {
            return (int) $m[1];
        }
    }

    return 0;
}

/**
 * Add newly detected supported-component versions to releases.json under a "latest" tracking
 * bucket, pointing at the (guaranteed-present) raw api-specificatie URL. A human reorganizes the
 * bucket into a proper umbrella release when finalizing the draft PR.
 *
 * @param  list<string>  $added
 */
function scaffoldMatrix(string $matrixFile, array $added): void
{
    $matrix = json_decode((string) file_get_contents($matrixFile), true, 512, JSON_THROW_ON_ERROR);
    $bucket = $matrix['releases']['latest']['components'] ?? [];

    foreach ($added as $entry) {
        $component = explode('/', $entry)[0];
        $friendly = COMPONENTS[$component] ?? null;

        if ($friendly === null) {
            continue; // Unsupported component (ac/nrc); reported but not contract-tested.
        }

        $version = versionFromEntry($entry);
        $url = RAW_BASE.'/'.$entry.'/openapi.yaml';

        // Keep the highest version seen per component in the bucket.
        if (! isset($bucket[$friendly]) || version_compare($version, $bucket[$friendly]['version'], '>')) {
            $bucket[$friendly] = ['version' => $version, 'url' => $url];
        }
    }

    if ($bucket === []) {
        return;
    }

    $matrix['releases']['latest'] = [
        'label' => 'Latest specs (auto-detected, verify before release)',
        'components' => $bucket,
    ];

    writeJson($matrixFile, $matrix);
}

function versionFromEntry(string $entry): string
{
    if (preg_match('/(\d+\.\d+\.\d+)/', $entry, $m) === 1) {
        return $m[1];
    }

    if (preg_match('/(\d+\.\d+)\.x/', $entry, $m) === 1) {
        return $m[1].'.0';
    }

    return '0.0.0';
}

/**
 * @param  list<string>  $added
 * @param  list<string>  $removed
 */
function writeReport(string $reportFile, array $added, array $removed): void
{
    $lines = [
        '## New ZGW spec versions detected',
        '',
        'The VNG published spec versions that are not yet in the test matrix.',
        '',
        '### Added',
        '',
    ];

    foreach ($added as $entry) {
        $lines[] = "- `{$entry}` ({$entry}/openapi.yaml)";
    }

    if ($removed !== []) {
        $lines[] = '';
        $lines[] = '### Removed';
        $lines[] = '';
        foreach ($removed as $entry) {
            $lines[] = "- `{$entry}`";
        }
    }

    $lines[] = '';
    $lines[] = '### Next steps';
    $lines[] = '';
    $lines[] = '- Review the scaffolded `latest` bucket in `tests/Contract/releases.json` and move the';
    $lines[] = '  versions into the correct umbrella release (1.5 / 1.6 / 1.7 / new).';
    $lines[] = '- Verify each spec URL resolves (newer releases may publish via the standard page only).';
    $lines[] = '- Re-run `composer contract` and update `tests/Contract/known-gaps.json` if endpoints changed.';
    $lines[] = '';

    $dir = dirname($reportFile);
    if (! is_dir($dir)) {
        mkdir($dir, 0o775, true);
    }

    file_put_contents($reportFile, implode("\n", $lines)."\n");
}

/**
 * @param  array<string, mixed>  $data
 */
function writeJson(string $file, array $data): void
{
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).
        "\n");
}
