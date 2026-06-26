<?php

declare(strict_types=1);

namespace Woweb\Zgw\Data;

use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Woweb\Zgw\Api\Endpoints\AbstractEndpoint;
use Woweb\Zgw\Contracts\CreatesResource;
use Woweb\Zgw\Contracts\DeletesResource;
use Woweb\Zgw\Contracts\ListsResources;
use Woweb\Zgw\Contracts\PatchesResource;
use Woweb\Zgw\Contracts\ProvidesAuditTrail;
use Woweb\Zgw\Contracts\ReplacesResource;
use Woweb\Zgw\Contracts\SearchesResources;
use Woweb\Zgw\Contracts\ShowsResource;
use Woweb\Zgw\Data\Generated\Audittrail\AuditTrailData;

/**
 * A typed decorator over a kernel endpoint: it calls the same array-returning endpoint and
 * hydrates each result into a DTO.
 *
 * Each method narrows the wrapped endpoint to the capability interface it needs via assert(). In
 * production assert() is a no-op (zero overhead); in development a wrong call (for example show()
 * on an endpoint that cannot show) fails loudly. The kernel array API stays available through
 * endpoint(), so the bulk and forward-compatible paths are never lost.
 *
 * @template TData of Data
 */
final class TypedEndpoint
{
    /**
     * @param  class-string<TData>  $dto
     */
    public function __construct(
        private readonly AbstractEndpoint $endpoint,
        private readonly string $dto,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     * @return LazyCollection<int, TData>
     */
    public function index(array $params = []): LazyCollection
    {
        $endpoint = $this->endpoint;
        assert($endpoint instanceof ListsResources);

        return $endpoint->index($params)->map(fn (array $row): Data => ($this->dto)::from($row));
    }

    /**
     * @param  array<string, mixed>  $expand
     * @return TData
     */
    public function show(string $uuid, array $expand = []): Data
    {
        $endpoint = $this->endpoint;
        assert($endpoint instanceof ShowsResource);

        return ($this->dto)::from($endpoint->show($uuid, $expand));
    }

    /**
     * @param  array<string, mixed>  $params
     * @return TData
     */
    public function store(array $params): Data
    {
        $endpoint = $this->endpoint;
        assert($endpoint instanceof CreatesResource);

        return ($this->dto)::from($endpoint->store($params));
    }

    /**
     * @param  array<string, mixed>  $params
     * @return TData
     */
    public function patch(string $uuid, array $params): Data
    {
        $endpoint = $this->endpoint;
        assert($endpoint instanceof PatchesResource);

        return ($this->dto)::from($endpoint->patch($uuid, $params));
    }

    /**
     * @param  array<string, mixed>  $params
     * @return TData
     */
    public function put(string $uuid, array $params): Data
    {
        $endpoint = $this->endpoint;
        assert($endpoint instanceof ReplacesResource);

        return ($this->dto)::from($endpoint->put($uuid, $params));
    }

    public function delete(string $uuid): bool
    {
        $endpoint = $this->endpoint;
        assert($endpoint instanceof DeletesResource);

        return $endpoint->delete($uuid);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return LazyCollection<int, TData>
     */
    public function zoek(array $params = []): LazyCollection
    {
        $endpoint = $this->endpoint;
        assert($endpoint instanceof SearchesResources);

        return $endpoint->zoek($params)->map(fn (array $row): Data => ($this->dto)::from($row));
    }

    /**
     * The audit trail of a resource as typed AuditTrailData entries.
     *
     * The audit trail has the same shape on every resource that exposes one, so it hydrates into a
     * single shared DTO rather than the wrapped endpoint's resource DTO.
     *
     * @return Collection<int, AuditTrailData>
     */
    public function audittrail(string $uuid): Collection
    {
        $endpoint = $this->endpoint;
        assert($endpoint instanceof ProvidesAuditTrail);

        return $endpoint->audittrail($uuid)->map(fn (array $row): AuditTrailData => AuditTrailData::from($row));
    }

    /**
     * A single audit trail entry of a resource as a typed AuditTrailData.
     */
    public function audittrailItem(string $uuid, string $auditUuid): AuditTrailData
    {
        $endpoint = $this->endpoint;
        assert($endpoint instanceof ProvidesAuditTrail);

        return AuditTrailData::from($endpoint->audittrailItem($uuid, $auditUuid));
    }

    /**
     * The wrapped kernel endpoint, for the array-returning API (bulk, ETL, forward compatibility).
     */
    public function endpoint(): AbstractEndpoint
    {
        return $this->endpoint;
    }
}
