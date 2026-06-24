<?php

declare(strict_types=1);

namespace Woweb\Zgw\Api\Actions;

use Illuminate\Support\LazyCollection;

trait Search
{
    /**
     * Search this resource via its `_zoek` endpoint, returning a LazyCollection that paginates on
     * demand. Iterating drives the requests (POST for the first page, GET for subsequent pages).
     *
     * @param  array<string, mixed>  $params
     * @return LazyCollection<int, array<string, mixed>>
     */
    public function zoek(array $params = []): LazyCollection
    {
        $this->assertSupported('POST', $this->collectionTemplate().'/_zoek');

        return $this->searchPaginate($this->baseUrl.$this->endpoint.'/_zoek', $params);
    }
}
