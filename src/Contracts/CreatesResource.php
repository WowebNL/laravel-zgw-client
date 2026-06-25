<?php

declare(strict_types=1);

namespace Woweb\Zgw\Contracts;

use Woweb\Zgw\Api\Actions\Store;

/**
 * An endpoint that can create a resource (POST collection).
 *
 * @see Store
 */
interface CreatesResource
{
    /**
     * Create a new resource.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function store(array $params): array;
}
