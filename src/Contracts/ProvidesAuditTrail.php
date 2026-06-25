<?php

declare(strict_types=1);

namespace Woweb\Zgw\Contracts;

use Illuminate\Support\Collection;
use Woweb\Zgw\Api\Actions\Audittrail;

/**
 * An endpoint that exposes an audit trail (GET item/audittrail[/{uuid}]).
 *
 * @see Audittrail
 */
interface ProvidesAuditTrail
{
    /**
     * Retrieve the full audit trail for a resource.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function audittrail(string $uuid): Collection;

    /**
     * Retrieve a single audit trail entry for a resource.
     *
     * @return array<string, mixed>
     */
    public function audittrailItem(string $uuid, string $auditUuid): array;
}
