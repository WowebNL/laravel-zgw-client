<?php

declare(strict_types=1);

namespace Woweb\Zgw\Api\Actions;

trait Show
{
    /**
     * Fetch a single resource by UUID.
     *
     * @param  array<string, mixed>  $expand  Optional expand query parameters.
     * @return array<string, mixed>
     */
    public function show(string $uuid, array $expand = []): array
    {
        return $this->getSingle($uuid, $expand);
    }
}
