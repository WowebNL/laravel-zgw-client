<?php

declare(strict_types=1);

namespace Woweb\Zgw\Tests\Contract;

use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Woweb\Zgw\Facades\Zgw;
use Woweb\Zgw\Tests\Contract\Support\OperationRegistry;
use Woweb\Zgw\Tests\Contract\Support\ReleaseMatrix;

/**
 * Asserts that the response shape the client relies on matches each release's spec, and that the
 * client actually parses a spec-shaped response.
 *
 * The client (AbstractEndpoint) unwraps list responses via the `results`/`next` envelope keys and
 * derives a resource UUID from its `url`. These checks confirm those assumptions hold against the
 * real specs, and a round-trip through Http::fake confirms the parsing still works.
 */
final class ResponseContractTest extends ContractTestCase
{
    /**
     * @return iterable<string, array{release: string, component: string, method: string, path: string, kind: string, key: string}>
     */
    public static function listAndDetailProvider(): iterable
    {
        foreach (array_keys(ReleaseMatrix::releases()) as $release) {
            foreach (OperationRegistry::all() as $operation) {
                if (! in_array($operation['kind'], ['list', 'detail'], true)) {
                    continue;
                }

                if (! in_array($release, $operation['versions'], true)) {
                    continue;
                }

                $name = "{$release} {$operation['key']}";

                yield $name => [
                    'release' => $release,
                    'component' => $operation['component'],
                    'method' => $operation['method'],
                    'path' => $operation['path'],
                    'kind' => $operation['kind'],
                    'key' => $operation['key'],
                ];
            }
        }
    }

    #[DataProvider('listAndDetailProvider')]
    public function test_response_envelope_matches_spec(string $release, string $component, string $method, string $path, string $kind, string $key): void
    {
        if (ReleaseMatrix::specFile($release, $component) === null) {
            $this->markTestSkipped("Spec fixture for {$release}/{$component} is not present.");
        }

        $spec = $this->loadSpec($release, $component);

        if ($spec->matchOperation($method, $this->concretePath($path)) === null) {
            $this->markTestSkipped("Operation [{$key}] does not exist in ZGW {$release}.");
        }

        if ($kind === 'list' && OperationRegistry::isBareArrayList($component, $path)) {
            // A ZGW relation resource (e.g. zaakinformatieobjecten): the standard defines the list
            // response as a bare JSON array, not a {count,next,previous,results} envelope, and it
            // carries no `page` parameter. AbstractEndpoint::paginate() yields these items directly.
            // Assert the spec still shapes it as an array so a future move to pagination breaks here.
            $this->assertTrue(
                $spec->successResponseIsArray($path, $method),
                "Bare-array list [{$key}] is no longer a bare array in ZGW {$release}; it may now be paginated."
            );
            $this->assertNotContains('page', $spec->parameterNames($path, $method), "Bare-array list [{$key}] unexpectedly declares a `page` query parameter in ZGW {$release}.");

            return;
        }

        $properties = $spec->successResponseProperties($path, $method);

        if ($properties === null) {
            // Success schema sits behind an external $ref that cannot be resolved from the local
            // fixture; the request-contract test still covers this operation's existence.
            $this->markTestSkipped("Response schema for [{$key}] is not resolvable from the local fixture.");
        }

        if ($kind === 'list') {
            $this->assertContains('results', $properties, "List response for [{$key}] has no `results` key in ZGW {$release}.");
            $this->assertContains('next', $properties, "List response for [{$key}] has no `next` pagination key in ZGW {$release}.");
            $this->assertContains('page', $spec->parameterNames($path, $method), "List endpoint [{$key}] declares no `page` query parameter in ZGW {$release}.");
        } else {
            $this->assertContains('url', $properties, "Detail response for [{$key}] has no `url` key (used for UUID extraction) in ZGW {$release}.");
        }
    }

    /**
     * Round-trip: feed a spec-shaped paginated envelope through the client and confirm it unwraps
     * `results` and derives the UUID from `url`. One representative list endpoint per component.
     *
     * @return iterable<string, array{0: string, 1: callable}>
     */
    public static function parsingProvider(): iterable
    {
        yield 'zaken' => ['https://zaken.example.com/zaken/api/v1/zaken', static fn () => Zgw::connection('main')->zaken()->zaken()->index()];
        yield 'catalogi' => ['https://catalogi.example.com/catalogi/api/v1/zaaktypen', static fn () => Zgw::connection('main')->catalogi()->zaaktypen()->index()];
        yield 'documenten' => ['https://documenten.example.com/documenten/api/v1/enkelvoudiginformatieobjecten', static fn () => Zgw::connection('main')->documenten()->enkelvoudiginformatieobjecten()->index()];
        yield 'besluiten' => ['https://besluiten.example.com/besluiten/api/v1/besluiten', static fn () => Zgw::connection('main')->besluiten()->besluiten()->index()];
    }

    #[DataProvider('parsingProvider')]
    public function test_client_parses_spec_shaped_envelope(string $url, callable $invoke): void
    {
        $resourceUrl = $url.'/'.OperationRegistry::UUID;

        Http::fake([
            $url => Http::response([
                'count' => 1,
                'next' => null,
                'previous' => null,
                'results' => [
                    ['url' => $resourceUrl, 'identificatie' => 'TEST-001'],
                ],
            ]),
        ]);

        $result = $invoke();

        $this->assertCount(1, $result);
        $this->assertSame(OperationRegistry::UUID, $result->first()['uuid'], 'Client did not derive the UUID from the resource url.');
    }

    /**
     * Round-trip for the non-paginated relation resources: feed a spec-shaped bare JSON array
     * through the client and confirm it yields the items and still derives the UUID from `url`.
     * This exercises the `array_is_list()` branch of AbstractEndpoint::paginate() against the shape
     * the specs actually define for these endpoints.
     *
     * @return iterable<string, array{0: string, 1: callable}>
     */
    public static function bareArrayParsingProvider(): iterable
    {
        yield 'zaakinformatieobjecten' => ['https://zaken.example.com/zaken/api/v1/zaakinformatieobjecten', static fn () => Zgw::connection('main')->zaken()->zaakinformatieobjecten()->index()];
        yield 'objectinformatieobjecten' => ['https://documenten.example.com/documenten/api/v1/objectinformatieobjecten', static fn () => Zgw::connection('main')->documenten()->objectinformatieobjecten()->index()];
        yield 'gebruiksrechten' => ['https://documenten.example.com/documenten/api/v1/gebruiksrechten', static fn () => Zgw::connection('main')->documenten()->gebruiksrechten()->index()];
        yield 'besluitinformatieobjecten' => ['https://besluiten.example.com/besluiten/api/v1/besluitinformatieobjecten', static fn () => Zgw::connection('main')->besluiten()->besluitinformatieobjecten()->index()];
    }

    #[DataProvider('bareArrayParsingProvider')]
    public function test_client_parses_spec_shaped_bare_array(string $url, callable $invoke): void
    {
        $resourceUrl = $url.'/'.OperationRegistry::UUID;

        Http::fake([
            $url => Http::response([
                ['url' => $resourceUrl, 'informatieobject' => 'https://documenten.example.com/documenten/api/v1/enkelvoudiginformatieobjecten/x'],
            ]),
        ]);

        $result = $invoke();

        $this->assertCount(1, $result);
        $this->assertSame(OperationRegistry::UUID, $result->first()['uuid'], 'Client did not derive the UUID from a bare-array item url.');
    }

    private function concretePath(string $template): string
    {
        return (string) preg_replace('/\{[^}]+\}/', OperationRegistry::UUID, $template);
    }
}
