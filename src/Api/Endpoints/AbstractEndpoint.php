<?php

declare(strict_types=1);

namespace Woweb\Zgw\Api\Endpoints;

use Generator;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\LazyCollection;
use Woweb\Zgw\Connection\ZgwConnection;
use Woweb\Zgw\Exceptions\InvalidIdentifierException;
use Woweb\Zgw\Exceptions\PaginationLimitException;
use Woweb\Zgw\Exceptions\UnsupportedOperationException;
use Woweb\Zgw\Response\ZgwResponse;
use Woweb\Zgw\Versioning\OperationAvailability;

abstract class AbstractEndpoint
{
    /** ZGW API identifier, e.g. "zaken". Used to resolve the base URL. */
    protected string $apiName = '';

    /** Endpoint path segment within the API, e.g. "zaken". */
    protected string $endpoint = '';

    /**
     * The OpenAPI path template for this endpoint's collection, e.g. "/zaken" or
     * "/zaken/{uuid}/zaakeigenschappen". Defaults to "/{endpoint}". Nested endpoints override it.
     * Used for per-version availability checks.
     */
    protected string $pathTemplate = '';

    protected string $baseUrl = '';

    protected readonly ZgwResponse $zgwResponse;

    private bool $shouldCache = false;

    private int $cacheTtl = 60;

    public function __construct(protected readonly ZgwConnection $connection)
    {
        if ($this->apiName !== '') {
            $this->baseUrl = $connection->getBaseUrl($this->apiName);
        }
        $this->zgwResponse = new ZgwResponse;
    }

    /**
     * The collection path template for this endpoint (e.g. "/zaken").
     */
    protected function collectionTemplate(): string
    {
        return $this->pathTemplate !== '' ? $this->pathTemplate : '/'.$this->endpoint;
    }

    /**
     * The single-resource path template for this endpoint (e.g. "/zaken/{uuid}").
     */
    protected function itemTemplate(): string
    {
        return $this->collectionTemplate().'/{uuid}';
    }

    /**
     * Assert that the given operation is defined in the release this connection targets.
     *
     * @throws UnsupportedOperationException
     */
    protected function assertSupported(string $method, string $pathTemplate): void
    {
        $version = $this->connection->getVersion();

        if (! OperationAvailability::isAvailable($this->apiName, $method, $pathTemplate, $version)) {
            throw new UnsupportedOperationException(
                "The operation [{$method} {$pathTemplate}] is not available in {$version->label()}. ".
                'Configure the connection for a release that supports it.'
            );
        }
    }

    /**
     * Enable caching for the next request.
     *
     * Returns the endpoint instance to allow fluent chaining:
     *   ->cache(300)->index()
     */
    public function cache(int $ttl = 60): static
    {
        $this->shouldCache = true;
        $this->cacheTtl = $ttl;

        return $this;
    }

    /**
     * Fetch a list endpoint as a LazyCollection that follows pagination on demand.
     *
     * Iterating the collection drives the HTTP requests one page at a time, so large result sets
     * are streamed rather than buffered in memory. Because work is deferred, the first request and
     * any error (a failed response, an untrusted "next" host, the page limit) surface when the
     * collection is iterated, not when this method returns. Call ->all() or ->collect() to realise
     * everything eagerly.
     *
     * When caching is enabled the pages are realised eagerly to store the result, and the returned
     * collection iterates that cached array.
     *
     * @param  array<string, mixed>  $params
     * @return LazyCollection<int, array<string, mixed>>
     */
    protected function getMany(array $params = []): LazyCollection
    {
        $url = $this->baseUrl.$this->endpoint;

        if ($this->shouldCache) {
            /** @var array<int, array<string, mixed>> $items */
            $items = Cache::store($this->connection->getCacheStore())->remember(
                $this->cacheKey($url, $params),
                $this->cacheTtl,
                fn (): array => $this->paginate($url, $params)->all(),
            );

            return LazyCollection::make($items);
        }

        return $this->paginate($url, $params);
    }

    /**
     * Lazily paginate a search (_zoek) endpoint: the first page is a POST with the search body,
     * subsequent pages follow the "next" links with GET.
     *
     * @param  array<string, mixed>  $body
     * @return LazyCollection<int, array<string, mixed>>
     */
    protected function searchPaginate(string $url, array $body): LazyCollection
    {
        return LazyCollection::make(function () use ($url, $body): Generator {
            $response = $this->zgwResponse->validate($this->connection->request()->post($url, $body));
            yield from $this->mapResults($response['results'] ?? []);

            $maxPages = $this->connection->getMaxPages();
            $page = 1;

            while (! empty($response['next'])) {
                if (++$page > $maxPages) {
                    throw new PaginationLimitException(
                        "ZGW pagination exceeded the configured maximum of [{$maxPages}] pages. ".
                        'Increase [max_pages] for this connection or narrow the query with filters.'
                    );
                }

                $this->connection->assertUrlAllowed($response['next']);

                $response = $this->zgwResponse->validate(
                    $this->connection->request()->get($response['next'])
                );

                yield from $this->mapResults($response['results'] ?? []);
            }
        });
    }

    /**
     * Lazily yield every result item across all pages, following "next" links.
     *
     * @param  array<string, mixed>  $params
     * @return LazyCollection<int, array<string, mixed>>
     */
    protected function paginate(string $url, array $params): LazyCollection
    {
        return LazyCollection::make(function () use ($url, $params): Generator {
            $response = $this->zgwResponse->validate($this->connection->request()->get($url, $params));

            // Some ZGW list endpoints (e.g. zaakinformatieobjecten,
            // objectinformatieobjecten, besluitinformatieobjecten) are not
            // paginated and return a bare JSON array instead of a
            // {count,next,previous,results} envelope. Yield those items directly;
            // there is no `next` to follow.
            if (array_is_list($response)) {
                yield from $this->mapResults($response);

                return;
            }

            yield from $this->mapResults($response['results'] ?? []);

            $maxPages = $this->connection->getMaxPages();
            $page = 1;

            while (! empty($response['next'])) {
                if (++$page > $maxPages) {
                    throw new PaginationLimitException(
                        "ZGW pagination exceeded the configured maximum of [{$maxPages}] pages. ".
                        'Increase [max_pages] for this connection or narrow the query with filters.'
                    );
                }

                $this->connection->assertUrlAllowed($response['next']);

                $response = $this->zgwResponse->validate(
                    $this->connection->request()->get($response['next'])
                );

                yield from $this->mapResults($response['results'] ?? []);
            }
        });
    }

    /**
     * Normalise each result item, deriving a `uuid` from the `url` field when absent.
     *
     * @param  array<int, array<string, mixed>>  $results
     * @return Generator<int, array<string, mixed>>
     */
    private function mapResults(array $results): Generator
    {
        foreach ($results as $item) {
            if (! isset($item['uuid']) && isset($item['url'])) {
                $item['uuid'] = substr($item['url'], strrpos($item['url'], '/') + 1);
            }

            yield $item;
        }
    }

    /**
     * Fetch a single resource by UUID.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function getSingle(string $uuid, array $params = []): array
    {
        $url = $this->baseUrl.$this->endpoint.'/'.$this->encodeId($uuid);

        if ($this->shouldCache) {
            return Cache::store($this->connection->getCacheStore())->remember(
                $this->cacheKey($url, $params),
                $this->cacheTtl,
                function () use ($url, $params): array {
                    $response = $this->connection->request()->get($url, $params);

                    return $this->zgwResponse->validate($response);
                }
            );
        }

        $response = $this->connection->request()->get($url, $params);

        return $this->zgwResponse->validate($response);
    }

    /**
     * POST to an action endpoint that the ZGW spec defines without a request body (such as
     * lock and publish), while still sending the mandatory Content-Type: application/json header.
     *
     * post() cannot be used for these: it would attach its default empty-array data as a JSON
     * list ("[]"), which Open Zaak rejects with a 400 "Verwacht een dictionary, kreeg een list".
     */
    protected function postWithoutBody(string $url): Response
    {
        return $this->connection->request()
            ->withHeaders(['Content-Type' => 'application/json'])
            ->send('POST', $url);
    }

    /**
     * Transform a flat array of result items into a Collection.
     * When an item has no `uuid` key, the UUID is extracted from the `url` field.
     *
     * Used for non-paginated list responses such as audit trails.
     *
     * @param  array<int, array<string, mixed>>  $results
     * @return Collection<int, array<string, mixed>>
     */
    protected function createCollection(array $results): Collection
    {
        return collect($results)->map(function (array $item): array {
            if (! isset($item['uuid']) && isset($item['url'])) {
                $item['uuid'] = substr($item['url'], strrpos($item['url'], '/') + 1);
            }

            return $item;
        });
    }

    /**
     * Validate and encode a resource identifier before placing it in a request URL.
     *
     * Identifiers are restricted to letters, digits, hyphen and underscore. This rejects
     * values that could manipulate the path or query (slashes, dots, query/fragment
     * characters, whitespace). The validated value is URL-encoded as defence in depth.
     *
     * @throws InvalidIdentifierException
     */
    /**
     * Build a cache key for a request.
     *
     * The key is namespaced by the connection's credential (client_id) so connections with
     * different authorizations never share a cache entry, and hashed with SHA-256.
     *
     * @param  array<string, mixed>  $params
     */
    private function cacheKey(string $url, array $params): string
    {
        return 'zgw_'.hash(
            'sha256',
            $this->connection->getCacheNamespace().'|'.$url.'|'.serialize($params)
        );
    }

    protected function encodeId(string $id): string
    {
        if ($id === '' || preg_match('/^[A-Za-z0-9_-]+$/', $id) !== 1) {
            throw new InvalidIdentifierException(
                'Invalid ZGW resource identifier: only letters, digits, hyphen and underscore are allowed.'
            );
        }

        return rawurlencode($id);
    }
}
