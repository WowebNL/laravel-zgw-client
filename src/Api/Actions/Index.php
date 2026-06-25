<?php

declare(strict_types=1);

namespace Woweb\Zgw\Api\Actions;

use Illuminate\Support\LazyCollection;
use Woweb\Zgw\Contracts\ListsResources;

/**
 * @phpstan-require-implements ListsResources
 */
trait Index
{
    /**
     * List the resources for this endpoint as a LazyCollection that paginates on demand.
     *
     * Iterating the result drives the HTTP requests page by page, so large sets are streamed
     * instead of buffered. Errors and the first request surface during iteration; call ->all()
     * or ->collect() to realise everything (and trigger any error) eagerly.
     *
     * @param  array<string, mixed>  $params  Optional query parameters / filters.
     * @return LazyCollection<int, array<string, mixed>>
     */
    public function index(array $params = []): LazyCollection
    {
        $this->assertSupported('GET', $this->collectionTemplate());

        return $this->getMany($params);
    }
}
