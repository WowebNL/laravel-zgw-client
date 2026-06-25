<?php

declare(strict_types=1);

namespace Woweb\Zgw\Data\Casts;

/**
 * A cast that needs the whole response row, not just its own field value.
 *
 * Most casts translate one value in isolation. A polymorphic field is different: which type its
 * value should become is decided by a sibling field (the discriminator), so the cast must see the
 * surrounding row. Hydration routes a cast that implements this to {@see self::castWithRow()}
 * instead of {@see Cast::cast()}.
 */
interface RowAware
{
    /**
     * @param  array<string, mixed>  $row  The full response row the field belongs to.
     */
    public function castWithRow(mixed $value, array $row): mixed;
}
