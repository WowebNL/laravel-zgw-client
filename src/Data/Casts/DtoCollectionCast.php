<?php

declare(strict_types=1);

namespace Woweb\Zgw\Data\Casts;

use Woweb\Zgw\Data\Data;

/**
 * Casts a list of objects into a list of typed DTOs. Non-object entries are skipped, so a single
 * malformed element never breaks the whole collection.
 */
final class DtoCollectionCast implements Cast
{
    /**
     * @param  class-string<Data>  $dto
     */
    public function __construct(
        private readonly string $dto,
    ) {}

    /**
     * @return list<Data>
     */
    public function cast(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $items = [];

        foreach ($value as $row) {
            if (is_array($row)) {
                $items[] = ($this->dto)::from($row);
            }
        }

        return $items;
    }
}
