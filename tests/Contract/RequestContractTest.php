<?php

declare(strict_types=1);

namespace Woweb\Zgw\Tests\Contract;

use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Woweb\Zgw\Facades\Zgw;
use Woweb\Zgw\Tests\Contract\Support\OperationRegistry;
use Woweb\Zgw\Tests\Contract\Support\ReleaseMatrix;

/**
 * Asserts that every request the client builds (HTTP method + path) corresponds to a real
 * operation in each supported release's OpenAPI spec. This is the flagship contract check: it
 * catches an endpoint being renamed, moved or removed across ZGW versions.
 *
 * Legitimate per-release absences (endpoints added in a later release) are recorded in
 * known-gaps.json and skipped; any unlisted absence fails the test as a real incompatibility.
 */
final class RequestContractTest extends ContractTestCase
{
    /**
     * @return iterable<string, array{release: string, component: string, method: string, path: string, key: string}>
     */
    public static function operationProvider(): iterable
    {
        foreach (array_keys(ReleaseMatrix::releases()) as $release) {
            foreach (OperationRegistry::all() as $operation) {
                $name = "{$release} {$operation['key']} ({$operation['method']} {$operation['path']})";

                yield $name => [
                    'release' => $release,
                    'component' => $operation['component'],
                    'method' => $operation['method'],
                    'path' => $operation['path'],
                    'key' => $operation['key'],
                ];
            }
        }
    }

    #[DataProvider('operationProvider')]
    public function test_client_request_exists_in_spec(string $release, string $component, string $method, string $path, string $key): void
    {
        if (ReleaseMatrix::specFile($release, $component) === null) {
            $this->markTestSkipped("Spec fixture for {$release}/{$component} is not present.");
        }

        $spec = $this->loadSpec($release, $component);

        Http::fake(function ($request) {
            if ($request->method() === 'DELETE') {
                return Http::response('', 204);
            }

            return Http::response(['count' => 0, 'next' => null, 'previous' => null, 'results' => []], 200);
        });

        $connection = Zgw::connection('main');

        // The request is recorded by the HTTP fake before the client parses the response, so any
        // downstream parsing error (irrelevant to the request contract) can be safely ignored.
        try {
            (OperationRegistry::invoker($key))($connection);
        } catch (\Throwable) {
            // Intentionally ignored: we only assert on the captured request below.
        }

        $recorded = Http::recorded();
        $this->assertNotEmpty($recorded, "The client issued no HTTP request for [{$key}].");

        $request = $recorded[0][0];
        $actualMethod = strtolower($request->method());
        $relativePath = $this->relativePath($request->url());

        // The Authorization header must always be present (ZgwConnection::getHeaders()).
        $this->assertNotEmpty($request->header('Authorization'), "Request for [{$key}] carried no Authorization header.");

        $match = $spec->matchOperation($actualMethod, $relativePath);

        if ($match === null && $this->isKnownGap($release, $key)) {
            $this->markTestSkipped("Known gap: [{$key}] is not defined in ZGW {$release}.");
        }

        $this->assertNotNull(
            $match,
            "The client calls [{$actualMethod} {$relativePath}] for [{$key}], but ZGW {$release} ".
            "({$component}) defines no such operation. If this endpoint was added in a later release, ".
            'record it in tests/Contract/known-gaps.json.'
        );
    }

    private function isKnownGap(string $release, string $key): bool
    {
        $file = __DIR__.'/known-gaps.json';

        if (! is_file($file)) {
            return false;
        }

        /** @var array{releases?: array<string, list<string>>} $gaps */
        $gaps = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);

        return in_array($key, $gaps['releases'][$release] ?? [], true);
    }
}
