<?php

declare(strict_types=1);

namespace Woweb\Zgw\Contracts;

use Woweb\Zgw\Api\Actions\Patch;

/**
 * An endpoint that can partially update a resource (PATCH item).
 *
 * @see Patch
 */
interface PatchesResource
{
    /**
     * Partially update a resource.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function patch(string $uuid, array $params): array;
}
