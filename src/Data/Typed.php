<?php

declare(strict_types=1);

namespace Woweb\Zgw\Data;

use InvalidArgumentException;
use Woweb\Zgw\Api\Endpoints\AbstractEndpoint;
use Woweb\Zgw\Data\Generated\TypedMap;

/**
 * Entry point for the typed layer: wraps a kernel endpoint in a TypedEndpoint that returns DTOs.
 *
 * The endpoint to DTO mapping lives in the generated TypedMap, so it never drifts from the DTOs.
 *
 *   $zaak  = Typed::wrap($connection->zaken()->zaken())->show($uuid);   // a ZaakData
 *   $zaken = Typed::wrap($connection->zaken()->zaken())->index();        // LazyCollection<ZaakData>
 */
final class Typed
{
    /**
     * @return TypedEndpoint<Data>
     *
     * @throws InvalidArgumentException when the endpoint has no mapped DTO.
     */
    public static function wrap(AbstractEndpoint $endpoint): TypedEndpoint
    {
        $class = $endpoint::class;
        $dto = self::dtoFor($class);

        if ($dto === null) {
            throw new InvalidArgumentException(
                "No typed DTO is mapped for endpoint [{$class}]. Annotate it with #[ZgwResource] and ".
                'run `composer dto:generate`, or use the array API on the endpoint directly.'
            );
        }

        return new TypedEndpoint($endpoint, $dto);
    }

    /**
     * Look up the DTO mapped to an endpoint class, widening the generated map's literal value type
     * to the DTO base so the wrapper is not bound to one concrete DTO.
     *
     * @param  class-string  $endpointClass
     * @return class-string<Data>|null
     */
    private static function dtoFor(string $endpointClass): ?string
    {
        return TypedMap::MAP[$endpointClass] ?? null;
    }
}
