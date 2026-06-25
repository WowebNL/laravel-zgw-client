<?php

declare(strict_types=1);

namespace Woweb\Zgw\Contracts;

use Woweb\Zgw\Api\Actions\Show;

/**
 * An endpoint that can fetch a single resource by UUID (GET item).
 *
 * @see Show
 */
interface ShowsResource
{
    /**
     * Fetch a single resource by UUID.
     *
     * @param  array<string, mixed>  $expand  Optional expand query parameters.
     * @return array<string, mixed>
     */
    public function show(string $uuid, array $expand = []): array;
}
