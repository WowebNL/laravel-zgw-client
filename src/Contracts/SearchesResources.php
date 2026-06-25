<?php

declare(strict_types=1);

namespace Woweb\Zgw\Contracts;

use Illuminate\Support\LazyCollection;
use Woweb\Zgw\Api\Actions\Search;

/**
 * An endpoint that can be searched via its `_zoek` endpoint (POST collection/_zoek).
 *
 * @see Search
 */
interface SearchesResources
{
    /**
     * Search this resource via its `_zoek` endpoint, returning a LazyCollection that paginates on demand.
     *
     * @param  array<string, mixed>  $params
     * @return LazyCollection<int, array<string, mixed>>
     */
    public function zoek(array $params = []): LazyCollection;
}
