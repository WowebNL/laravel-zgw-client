<?php

declare(strict_types=1);

namespace Woweb\Zgw\Api\Actions;

use Illuminate\Support\Collection;

trait Index
{
    /**
     * Fetch all resources for this endpoint (auto-paginates).
     *
     * @param  array<string, mixed>  $params  Optional query parameters / filters.
     * @return Collection<int, array<string, mixed>>
     */
    public function index(array $params = []): Collection
    {
        return $this->getMany($params);
    }
}
