<?php

declare(strict_types=1);

namespace Woweb\Zgw\Api\Endpoints;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Woweb\Zgw\Connection\ZgwConnection;
use Woweb\Zgw\Exceptions\InvalidIdentifierException;
use Woweb\Zgw\Exceptions\PaginationLimitException;
use Woweb\Zgw\Response\ZgwResponse;

abstract class AbstractEndpoint
{
    /** ZGW API identifier, e.g. "zaken". Used to resolve the base URL. */
    protected string $apiName = '';

    /** Endpoint path segment within the API, e.g. "zaken". */
    protected string $endpoint = '';

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
     * Fetch all pages for a list endpoint and return a flat Collection.
     *
     * @param  array<string, mixed>  $params
     * @return Collection<int, array<string, mixed>>
     */
    protected function getMany(array $params = []): Collection
    {
        $url = $this->baseUrl.$this->endpoint;

        if ($this->shouldCache) {
            return Cache::store($this->connection->getCacheStore())->remember(
                $this->cacheKey($url, $params),
                $this->cacheTtl,
                function () use ($url, $params): Collection {
                    $response = $this->connection->request()->get($url, $params);

                    return $this->getResults($this->zgwResponse->validate($response));
                }
            );
        }

        $response = $this->connection->request()->get($url, $params);

        return $this->getResults($this->zgwResponse->validate($response));
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
     * Collect all paginated results into a flat Collection by following 'next' links iteratively.
     *
     * @param  array<string, mixed>  $response
     * @return Collection<int, array<string, mixed>>
     */
    protected function getResults(array $response): Collection
    {
        $results = $response['results'] ?? [];

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

            $nextResponse = $this->connection->request()
                ->get($response['next']);

            $response = $this->zgwResponse->validate($nextResponse);
            $results = array_merge($results, $response['results'] ?? []);
        }

        return $this->createCollection($results);
    }

    /**
     * Transform a flat array of result items into a Collection.
     * When an item has no `uuid` key, the UUID is extracted from the `url` field.
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
