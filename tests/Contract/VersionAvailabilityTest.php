<?php

declare(strict_types=1);

namespace Woweb\Zgw\Tests\Contract;

use PHPUnit\Framework\Attributes\DataProvider;
use Woweb\Zgw\Tests\Contract\Support\OperationRegistry;
use Woweb\Zgw\Tests\Contract\Support\ReleaseMatrix;

/**
 * Verifies the other direction of the version map: an operation declared unavailable in a release
 * (via OperationAvailability) must genuinely be absent from that release's spec. Together with the
 * request-contract test (available operations exist in the spec), this pins the availability map
 * exactly to the standard, so it cannot silently over- or under-restrict.
 */
final class VersionAvailabilityTest extends ContractTestCase
{
    /**
     * @return iterable<string, array{release: string, component: string, method: string, path: string, key: string}>
     */
    public static function restrictedProvider(): iterable
    {
        $releases = array_keys(ReleaseMatrix::releases());

        foreach (OperationRegistry::all() as $operation) {
            foreach ($releases as $release) {
                if (in_array($release, $operation['versions'], true)) {
                    continue; // available here; covered by the request-contract test
                }

                yield "{$release} excludes {$operation['key']}" => [
                    'release' => $release,
                    'component' => $operation['component'],
                    'method' => $operation['method'],
                    'path' => $operation['path'],
                    'key' => $operation['key'],
                ];
            }
        }
    }

    #[DataProvider('restrictedProvider')]
    public function test_unavailable_operation_is_absent_from_spec(string $release, string $component, string $method, string $path, string $key): void
    {
        if (ReleaseMatrix::specFile($release, $component) === null) {
            $this->markTestSkipped("Spec fixture for {$release}/{$component} is not present.");
        }

        $concretePath = (string) preg_replace('/\{[^}]+\}/', OperationRegistry::UUID, $path);

        $this->assertNull(
            $this->loadSpec($release, $component)->matchOperation($method, $concretePath),
            "OperationAvailability marks [{$key}] unavailable in ZGW {$release}, but the spec defines ".
            "[{$method} {$path}]. The availability map is over-restrictive."
        );
    }
}
