<?php

declare(strict_types=1);

namespace Woweb\Zgw\Tests\Unit\Data;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use PHPUnit\Framework\TestCase;
use Woweb\Zgw\Data\Casts\DateTimeCast;
use Woweb\Zgw\Data\Casts\DiscriminatorCast;
use Woweb\Zgw\Data\Casts\DtoCast;
use Woweb\Zgw\Data\Casts\DtoCollectionCast;
use Woweb\Zgw\Data\Casts\DurationCast;
use Woweb\Zgw\Data\Casts\EnumCast;
use Woweb\Zgw\Data\Casts\GeoJsonCast;
use Woweb\Zgw\Data\Casts\ReferenceCast;
use Woweb\Zgw\Data\Generated\Zaken\Enums\Vertrouwelijkheidaanduiding;
use Woweb\Zgw\Data\Generated\Zaken\RolNatuurlijkPersoon;
use Woweb\Zgw\Data\Generated\Zaken\ZaakKenmerk;
use Woweb\Zgw\Data\Values\GeoJsonGeometry;
use Woweb\Zgw\Data\Values\Reference;

/**
 * Unit coverage for the casts, with an emphasis on the tolerant paths: a value a cast cannot make
 * sense of becomes null (or an empty list) rather than throwing, which is what keeps hydration of a
 * whole resource alive when one field is unexpected.
 */
class CastsTest extends TestCase
{
    public function test_datetime_cast(): void
    {
        $cast = new DateTimeCast;

        $this->assertInstanceOf(CarbonImmutable::class, $cast->cast('2024-01-01'));
        $this->assertSame('2024-01-01', $cast->cast('2024-01-01')->format('Y-m-d'));
        $this->assertInstanceOf(CarbonImmutable::class, $cast->cast('2024-01-01T12:30:00Z'));
        $this->assertNull($cast->cast('not-a-date'));
        $this->assertNull($cast->cast(''));
        $this->assertNull($cast->cast(12345));
        $this->assertNull($cast->cast(['x']));
    }

    public function test_duration_cast(): void
    {
        $cast = new DurationCast;

        $this->assertInstanceOf(CarbonInterval::class, $cast->cast('P30D'));
        $this->assertSame(30, $cast->cast('P30D')->d);
        $this->assertNull($cast->cast('30 days'));
        $this->assertNull($cast->cast(''));
        $this->assertNull($cast->cast(30));
    }

    public function test_reference_cast(): void
    {
        $cast = new ReferenceCast;

        $reference = $cast->cast('https://example.com/api/v1/zaken/abc');
        $this->assertInstanceOf(Reference::class, $reference);
        $this->assertSame('abc', $reference->uuid());
        $this->assertNull($cast->cast(42));
        $this->assertNull($cast->cast(['x']));
    }

    public function test_enum_cast(): void
    {
        $cast = new EnumCast(Vertrouwelijkheidaanduiding::class);

        $this->assertSame(Vertrouwelijkheidaanduiding::Openbaar, $cast->cast('openbaar'));
        $this->assertNull($cast->cast('a_value_from_a_future_release'));
        $this->assertNull($cast->cast(['x']));
        $this->assertNull($cast->cast(1.5));
    }

    public function test_dto_cast(): void
    {
        $cast = new DtoCast(ZaakKenmerk::class);

        $dto = $cast->cast(['kenmerk' => 'dossier-1', 'bron' => 'intern']);
        $this->assertInstanceOf(ZaakKenmerk::class, $dto);
        $this->assertSame('dossier-1', $dto->kenmerk);
        $this->assertNull($cast->cast('not-an-array'));
    }

    public function test_dto_collection_cast(): void
    {
        $cast = new DtoCollectionCast(ZaakKenmerk::class);

        $list = $cast->cast([
            ['kenmerk' => 'a', 'bron' => 'x'],
            'skip-this-non-array',
            ['kenmerk' => 'b', 'bron' => 'y'],
        ]);

        $this->assertCount(2, $list);
        $this->assertInstanceOf(ZaakKenmerk::class, $list[0]);
        $this->assertSame('b', $list[1]->kenmerk);
        $this->assertSame([], $cast->cast('not-an-array'));
    }

    public function test_geojson_cast(): void
    {
        $cast = new GeoJsonCast;

        $geometry = $cast->cast(['type' => 'Point', 'coordinates' => [4.9, 52.3]]);
        $this->assertInstanceOf(GeoJsonGeometry::class, $geometry);
        $this->assertSame('Point', $geometry->type());
        $this->assertNull($cast->cast('not-an-array'));
    }

    public function test_discriminator_cast_with_row_resolves_the_subtype(): void
    {
        $cast = new DiscriminatorCast('betrokkeneType', ['natuurlijk_persoon' => RolNatuurlijkPersoon::class]);

        $resolved = $cast->castWithRow(['inpBsn' => '111222333'], ['betrokkeneType' => 'natuurlijk_persoon']);
        $this->assertInstanceOf(RolNatuurlijkPersoon::class, $resolved);
        $this->assertSame('111222333', $resolved->inpBsn);

        $this->assertNull($cast->castWithRow(['inpBsn' => '1'], ['betrokkeneType' => 'unknown']));
        $this->assertNull($cast->castWithRow(['inpBsn' => '1'], []));
        $this->assertNull($cast->castWithRow('not-an-array', ['betrokkeneType' => 'natuurlijk_persoon']));
    }

    public function test_discriminator_cast_without_a_row(): void
    {
        $strict = new DiscriminatorCast('betrokkeneType', ['natuurlijk_persoon' => RolNatuurlijkPersoon::class]);
        $this->assertNull($strict->cast(['inpBsn' => '1']));

        $tolerant = new DiscriminatorCast('objectType', [], fallbackToRaw: true);
        $this->assertSame(['x' => 'y'], $tolerant->cast(['x' => 'y']));
        $this->assertNull($tolerant->cast('not-an-array'));
    }
}
