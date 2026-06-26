<?php

declare(strict_types=1);

namespace Woweb\Zgw\Data;

use InvalidArgumentException;
use Woweb\Zgw\Api\Endpoints\AbstractEndpoint;
use Woweb\Zgw\Api\Endpoints\Autorisaties\Applicaties;
use Woweb\Zgw\Api\Endpoints\Besluiten\Besluiten;
use Woweb\Zgw\Api\Endpoints\Besluiten\Besluitinformatieobjecten;
use Woweb\Zgw\Api\Endpoints\Catalogi\Besluittypen;
use Woweb\Zgw\Api\Endpoints\Catalogi\Catalogussen;
use Woweb\Zgw\Api\Endpoints\Catalogi\Eigenschappen;
use Woweb\Zgw\Api\Endpoints\Catalogi\Informatieobjecttypen;
use Woweb\Zgw\Api\Endpoints\Catalogi\Resultaattypen;
use Woweb\Zgw\Api\Endpoints\Catalogi\Roltypen;
use Woweb\Zgw\Api\Endpoints\Catalogi\Statustypen;
use Woweb\Zgw\Api\Endpoints\Catalogi\Zaakobjecttypen;
use Woweb\Zgw\Api\Endpoints\Catalogi\ZaaktypeInformatieobjecttypen;
use Woweb\Zgw\Api\Endpoints\Catalogi\Zaaktypen;
use Woweb\Zgw\Api\Endpoints\Documenten\Enkelvoudiginformatieobjecten;
use Woweb\Zgw\Api\Endpoints\Documenten\Gebruiksrechten;
use Woweb\Zgw\Api\Endpoints\Documenten\Objectinformatieobjecten;
use Woweb\Zgw\Api\Endpoints\Documenten\Verzendingen;
use Woweb\Zgw\Api\Endpoints\Notificaties\Abonnementen;
use Woweb\Zgw\Api\Endpoints\Notificaties\Kanalen;
use Woweb\Zgw\Api\Endpoints\Zaken\Klantcontacten;
use Woweb\Zgw\Api\Endpoints\Zaken\Nested\ZaakBesluiten;
use Woweb\Zgw\Api\Endpoints\Zaken\Nested\Zaakeigenschappen;
use Woweb\Zgw\Api\Endpoints\Zaken\Resultaten;
use Woweb\Zgw\Api\Endpoints\Zaken\Rollen;
use Woweb\Zgw\Api\Endpoints\Zaken\Statussen;
use Woweb\Zgw\Api\Endpoints\Zaken\Zaakcontactmomenten;
use Woweb\Zgw\Api\Endpoints\Zaken\Zaakinformatieobjecten;
use Woweb\Zgw\Api\Endpoints\Zaken\Zaaknotities;
use Woweb\Zgw\Api\Endpoints\Zaken\Zaakobjecten;
use Woweb\Zgw\Api\Endpoints\Zaken\Zaakverzoeken;
use Woweb\Zgw\Api\Endpoints\Zaken\Zaken;
use Woweb\Zgw\Data\Generated\Autorisaties\ApplicatieData;
use Woweb\Zgw\Data\Generated\Besluiten\BesluitData;
use Woweb\Zgw\Data\Generated\Besluiten\BesluitInformatieObjectData;
use Woweb\Zgw\Data\Generated\Catalogi\BesluitTypeData;
use Woweb\Zgw\Data\Generated\Catalogi\CatalogusData;
use Woweb\Zgw\Data\Generated\Catalogi\EigenschapData;
use Woweb\Zgw\Data\Generated\Catalogi\InformatieObjectTypeData;
use Woweb\Zgw\Data\Generated\Catalogi\ResultaatTypeData;
use Woweb\Zgw\Data\Generated\Catalogi\RolTypeData;
use Woweb\Zgw\Data\Generated\Catalogi\StatusTypeData;
use Woweb\Zgw\Data\Generated\Catalogi\ZaakObjectTypeData;
use Woweb\Zgw\Data\Generated\Catalogi\ZaakTypeData;
use Woweb\Zgw\Data\Generated\Catalogi\ZaakTypeInformatieObjectTypeData;
use Woweb\Zgw\Data\Generated\Documenten\EnkelvoudigInformatieObjectData;
use Woweb\Zgw\Data\Generated\Documenten\GebruiksrechtenData;
use Woweb\Zgw\Data\Generated\Documenten\ObjectInformatieObjectData;
use Woweb\Zgw\Data\Generated\Documenten\VerzendingData;
use Woweb\Zgw\Data\Generated\Notificaties\AbonnementData;
use Woweb\Zgw\Data\Generated\Notificaties\KanaalData;
use Woweb\Zgw\Data\Generated\TypedMap;
use Woweb\Zgw\Data\Generated\Zaken\KlantContactData;
use Woweb\Zgw\Data\Generated\Zaken\ResultaatData;
use Woweb\Zgw\Data\Generated\Zaken\RolData;
use Woweb\Zgw\Data\Generated\Zaken\StatusData;
use Woweb\Zgw\Data\Generated\Zaken\ZaakBesluitData;
use Woweb\Zgw\Data\Generated\Zaken\ZaakContactMomentData;
use Woweb\Zgw\Data\Generated\Zaken\ZaakData;
use Woweb\Zgw\Data\Generated\Zaken\ZaakEigenschapData;
use Woweb\Zgw\Data\Generated\Zaken\ZaakInformatieObjectData;
use Woweb\Zgw\Data\Generated\Zaken\ZaakNotitieData;
use Woweb\Zgw\Data\Generated\Zaken\ZaakObjectData;
use Woweb\Zgw\Data\Generated\Zaken\ZaakVerzoekData;

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
     * Wrap a kernel endpoint in a TypedEndpoint whose methods return the DTO mapped to that endpoint.
     *
     * The concrete DTO is inferred statically from the endpoint's type through the conditional return
     * type below, so `Typed::wrap($conn->zaken()->zaken())->show($uuid)` resolves to a ZaakData rather
     * than the base Data. That conditional type is generated from TypedMap by `composer dto:generate`,
     * so it can never drift from the DTOs; do not edit it between the markers. An endpoint with no
     * mapped DTO falls back to TypedEndpoint<Data> for static analysis and throws at runtime.
     *
     * @return TypedEndpoint<Data>
     *
     * @generated-wrap-return-start `composer dto:generate` owns the @phpstan-return below
     *
     * @phpstan-return ($endpoint is Applicaties ? TypedEndpoint<ApplicatieData> : ($endpoint is Besluiten ? TypedEndpoint<BesluitData> : ($endpoint is Besluitinformatieobjecten ? TypedEndpoint<BesluitInformatieObjectData> : ($endpoint is Besluittypen ? TypedEndpoint<BesluitTypeData> : ($endpoint is Catalogussen ? TypedEndpoint<CatalogusData> : ($endpoint is Eigenschappen ? TypedEndpoint<EigenschapData> : ($endpoint is Informatieobjecttypen ? TypedEndpoint<InformatieObjectTypeData> : ($endpoint is Resultaattypen ? TypedEndpoint<ResultaatTypeData> : ($endpoint is Roltypen ? TypedEndpoint<RolTypeData> : ($endpoint is Statustypen ? TypedEndpoint<StatusTypeData> : ($endpoint is Zaakobjecttypen ? TypedEndpoint<ZaakObjectTypeData> : ($endpoint is ZaaktypeInformatieobjecttypen ? TypedEndpoint<ZaakTypeInformatieObjectTypeData> : ($endpoint is Zaaktypen ? TypedEndpoint<ZaakTypeData> : ($endpoint is Enkelvoudiginformatieobjecten ? TypedEndpoint<EnkelvoudigInformatieObjectData> : ($endpoint is Gebruiksrechten ? TypedEndpoint<GebruiksrechtenData> : ($endpoint is Objectinformatieobjecten ? TypedEndpoint<ObjectInformatieObjectData> : ($endpoint is Verzendingen ? TypedEndpoint<VerzendingData> : ($endpoint is Abonnementen ? TypedEndpoint<AbonnementData> : ($endpoint is Kanalen ? TypedEndpoint<KanaalData> : ($endpoint is Klantcontacten ? TypedEndpoint<KlantContactData> : ($endpoint is ZaakBesluiten ? TypedEndpoint<ZaakBesluitData> : ($endpoint is Zaakeigenschappen ? TypedEndpoint<ZaakEigenschapData> : ($endpoint is Resultaten ? TypedEndpoint<ResultaatData> : ($endpoint is Rollen ? TypedEndpoint<RolData> : ($endpoint is Statussen ? TypedEndpoint<StatusData> : ($endpoint is Zaakcontactmomenten ? TypedEndpoint<ZaakContactMomentData> : ($endpoint is Zaakinformatieobjecten ? TypedEndpoint<ZaakInformatieObjectData> : ($endpoint is Zaaknotities ? TypedEndpoint<ZaakNotitieData> : ($endpoint is Zaakobjecten ? TypedEndpoint<ZaakObjectData> : ($endpoint is Zaakverzoeken ? TypedEndpoint<ZaakVerzoekData> : ($endpoint is Zaken ? TypedEndpoint<ZaakData> : TypedEndpoint<Data>)))))))))))))))))))))))))))))))
     *
     * @generated-wrap-return-end
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
