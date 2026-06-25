<?php

declare(strict_types=1);

namespace Woweb\Zgw\Contracts;

use Woweb\Zgw\Api\Actions\Put;

/**
 * An endpoint that can replace a resource (PUT item, full update).
 *
 * @see Put
 */
interface ReplacesResource
{
    /**
     * Replace a resource (full update).
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function put(string $uuid, array $params): array;
}
