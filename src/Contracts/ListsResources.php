<?php

declare(strict_types=1);

namespace Woweb\Zgw\Contracts;

use Illuminate\Support\LazyCollection;
use Woweb\Zgw\Api\Actions\Index;

/**
 * An endpoint that can list its resources (GET collection).
 *
 * @see Index
 */
interface ListsResources
{
    /**
     * List the resources for this endpoint as a LazyCollection that paginates on demand.
     *
     * @param  array<string, mixed>  $params  Optional query parameters / filters.
     * @return LazyCollection<int, array<string, mixed>>
     */
    public function index(array $params = []): LazyCollection;
}
