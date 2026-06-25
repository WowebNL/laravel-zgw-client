<?php

declare(strict_types=1);

namespace Woweb\Zgw\Data;

use Woweb\Zgw\Data\Concerns\HydratesFromArray;

/**
 * Base class for every read DTO.
 *
 * A DTO is a tolerant, structural snapshot of a ZGW resource. It carries no invariants and does no
 * I/O: it only moves data across the boundary from a response array into typed properties. Every
 * concrete DTO declares its public properties and, where a field needs translation, a cast.
 */
abstract class Data
{
    use HydratesFromArray;

    /**
     * Fields present in the response but not declared on this DTO (forward compatibility).
     *
     * @var array<string, mixed>
     */
    public array $extra = [];

    /**
     * The untouched response array this DTO was hydrated from.
     *
     * @var array<string, mixed>
     */
    public array $raw = [];
}
