<?php

declare(strict_types=1);

namespace Woweb\Zgw\Tests\Types;

use Woweb\Zgw\Api\Endpoints\Documenten\Enkelvoudiginformatieobjecten;
use Woweb\Zgw\Api\Endpoints\Zaken\Rollen;
use Woweb\Zgw\Api\Endpoints\Zaken\Zaken;
use Woweb\Zgw\Data\Typed;

use function PHPStan\Testing\assertType;

/**
 * Static-type assertions for the generated conditional return type on Typed::wrap(). PHPStan runs
 * these as part of the normal analysis (this directory is on the analysed paths) and fails if the
 * concrete DTO is no longer inferred from the endpoint type.
 */
function zaken_endpoint_resolves_to_zaak_data(Zaken $endpoint): void
{
    assertType('Woweb\Zgw\Data\Generated\Zaken\ZaakData', Typed::wrap($endpoint)->show('uuid'));
    assertType('Woweb\Zgw\Data\Generated\Zaken\ZaakData', Typed::wrap($endpoint)->store([]));
    assertType('Woweb\Zgw\Data\Generated\Zaken\ZaakData', Typed::wrap($endpoint)->patch('uuid', []));
    assertType(
        'Illuminate\Support\LazyCollection<int, Woweb\Zgw\Data\Generated\Zaken\ZaakData>',
        Typed::wrap($endpoint)->index(),
    );
}

function rollen_endpoint_resolves_to_rol_data(Rollen $endpoint): void
{
    assertType('Woweb\Zgw\Data\Generated\Zaken\RolData', Typed::wrap($endpoint)->show('uuid'));
}

function document_endpoint_resolves_to_its_data(Enkelvoudiginformatieobjecten $endpoint): void
{
    assertType(
        'Woweb\Zgw\Data\Generated\Documenten\EnkelvoudigInformatieObjectData',
        Typed::wrap($endpoint)->show('uuid'),
    );
}
