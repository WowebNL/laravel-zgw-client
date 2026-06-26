<?php

declare(strict_types=1);

namespace Woweb\Zgw\Api\Actions;

use Woweb\Zgw\Contracts\ShowsResource;

/**
 * @phpstan-require-implements ShowsResource
 */
trait Show
{
    /**
     * Fetch a single resource by UUID.
     *
     * The array is sent as the request's query parameters. Despite its name it is not limited to
     * `expand`: it carries any query parameter the operation supports, for example `versie` and
     * `registratieOp` on a document (a specific or point-in-time version) or `datumGeldigheid` on a
     * zaaktype. So `show($uuid, ['versie' => 2])` and `show($uuid, ['expand' => 'zaaktype'])` are both valid.
     *
     * @param  array<string, mixed>  $expand  Query parameters, for example `['expand' => 'zaaktype']` or `['versie' => 2]`.
     * @return array<string, mixed>
     */
    public function show(string $uuid, array $expand = []): array
    {
        $this->assertSupported('GET', $this->itemTemplate());

        return $this->getSingle($uuid, $expand);
    }
}
