<?php

declare(strict_types=1);

namespace Woweb\Zgw\Api\Actions;

use Illuminate\Support\Collection;

trait Audittrail
{
    /**
     * Retrieve the full audit trail for a resource.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function audittrail(string $uuid): Collection
    {
        $this->assertSupported('GET', $this->itemTemplate().'/audittrail');

        $url = $this->baseUrl.$this->endpoint.'/'.$this->encodeId($uuid).'/audittrail';

        /** @var array<int, array<string, mixed>> $items */
        $items = $this->zgwResponse->validate($this->connection->request()->get($url));

        return $this->createCollection($items);
    }

    /**
     * Retrieve a single audit trail entry for a resource.
     *
     * @return array<string, mixed>
     */
    public function audittrailItem(string $uuid, string $auditUuid): array
    {
        $this->assertSupported('GET', $this->itemTemplate().'/audittrail/{uuid}');

        $url = $this->baseUrl.$this->endpoint.'/'.$this->encodeId($uuid).'/audittrail/'.$this->encodeId($auditUuid);

        return $this->zgwResponse->validate($this->connection->request()->get($url));
    }
}
