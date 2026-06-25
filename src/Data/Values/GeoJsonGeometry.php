<?php

declare(strict_types=1);

namespace Woweb\Zgw\Data\Values;

/**
 * A GeoJSON geometry value object (RFC 7946).
 *
 * GeoJSON is a stable, standardised structure that does not change with the ZGW release, and it is
 * polymorphic (Point, LineString, Polygon, their Multi variants and GeometryCollection). It is
 * therefore modelled as a hand-written value object rather than a generated, spec-driven DTO: it
 * gives typed access to the geometry while staying tolerant of the exact shape. It does no I/O and
 * keeps the decoded structure available through $value.
 */
final readonly class GeoJsonGeometry
{
    /**
     * @param  array<string, mixed>  $value  The decoded GeoJSON geometry.
     */
    public function __construct(
        public array $value,
    ) {}

    /**
     * The geometry type (for example "Point" or "Polygon"), or null when absent.
     */
    public function type(): ?string
    {
        $type = $this->value['type'] ?? null;

        return is_string($type) ? $type : null;
    }

    /**
     * The coordinates array for a simple geometry, or null for a GeometryCollection or when absent.
     *
     * @return array<int|string, mixed>|null
     */
    public function coordinates(): ?array
    {
        $coordinates = $this->value['coordinates'] ?? null;

        return is_array($coordinates) ? $coordinates : null;
    }

    /**
     * The member geometries of a GeometryCollection, or null for a simple geometry.
     *
     * @return list<self>|null
     */
    public function geometries(): ?array
    {
        $geometries = $this->value['geometries'] ?? null;

        if (! is_array($geometries)) {
            return null;
        }

        $members = [];

        foreach ($geometries as $geometry) {
            if (is_array($geometry)) {
                $members[] = new self($geometry);
            }
        }

        return $members;
    }
}
