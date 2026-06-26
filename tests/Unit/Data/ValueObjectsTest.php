<?php

declare(strict_types=1);

namespace Woweb\Zgw\Tests\Unit\Data;

use PHPUnit\Framework\TestCase;
use Woweb\Zgw\Data\Values\GeoJsonGeometry;
use Woweb\Zgw\Data\Values\Reference;

class ValueObjectsTest extends TestCase
{
    public function test_reference_exposes_url_uuid_and_string(): void
    {
        $reference = new Reference('https://example.com/zaken/api/v1/zaken/abc-123');

        $this->assertSame('https://example.com/zaken/api/v1/zaken/abc-123', $reference->url);
        $this->assertSame('abc-123', $reference->uuid());
        $this->assertSame($reference->url, (string) $reference);
    }

    public function test_reference_uuid_is_null_without_a_path_segment(): void
    {
        $this->assertNull((new Reference('https://example.com'))->uuid());
        $this->assertNull((new Reference('https://example.com/'))->uuid());
    }

    public function test_reference_json_encodes_to_the_bare_url(): void
    {
        $reference = new Reference('https://example.com/zaken/api/v1/zaken/abc-123');

        $this->assertSame($reference->url, $reference->jsonSerialize());
        $this->assertSame($reference->url, json_decode((string) json_encode($reference)));
    }

    public function test_reference_in_a_write_payload_serialises_as_a_standard_link(): void
    {
        $payload = ['zaak' => new Reference('https://example.com/zaken/api/v1/zaken/abc-123')];

        // The reference is a plain URL string on the wire, not a nested {"url":...} object.
        $this->assertSame(['zaak' => 'https://example.com/zaken/api/v1/zaken/abc-123'], json_decode((string) json_encode($payload), true));
    }

    public function test_geojson_simple_geometry(): void
    {
        $geometry = new GeoJsonGeometry(['type' => 'Point', 'coordinates' => [4.9, 52.3]]);

        $this->assertSame('Point', $geometry->type());
        $this->assertSame([4.9, 52.3], $geometry->coordinates());
        $this->assertNull($geometry->geometries());
    }

    public function test_geojson_geometry_collection(): void
    {
        $geometry = new GeoJsonGeometry([
            'type' => 'GeometryCollection',
            'geometries' => [
                ['type' => 'Point', 'coordinates' => [1, 2]],
                'not-a-geometry',
                ['type' => 'Point', 'coordinates' => [3, 4]],
            ],
        ]);

        $members = $geometry->geometries();
        $this->assertCount(2, $members);
        $this->assertInstanceOf(GeoJsonGeometry::class, $members[0]);
        $this->assertSame('Point', $members[1]->type());
        $this->assertNull($geometry->coordinates());
    }

    public function test_geojson_tolerates_missing_or_malformed_members(): void
    {
        $empty = new GeoJsonGeometry([]);

        $this->assertNull($empty->type());
        $this->assertNull($empty->coordinates());
        $this->assertNull($empty->geometries());
        $this->assertNull((new GeoJsonGeometry(['type' => 123]))->type());
    }
}
