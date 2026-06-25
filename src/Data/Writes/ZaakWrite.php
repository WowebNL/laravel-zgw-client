<?php

declare(strict_types=1);

namespace Woweb\Zgw\Data\Writes;

use DateTimeInterface;
use Woweb\Zgw\Data\Generated\Enums\Vertrouwelijkheidaanduiding;
use Woweb\Zgw\Data\Values\Reference;

/**
 * Builds a create or update payload for a Zaak.
 *
 * Only fields you set end up in toPayload(), so the same builder is safe for a PATCH (set just the
 * fields you want to change) and for a create (set everything required). To clear a field on a
 * PATCH, set it to null explicitly.
 *
 *   $payload = (new ZaakWrite)
 *       ->toelichting('Bijgewerkt na controle')
 *       ->vertrouwelijkheidaanduiding(Vertrouwelijkheidaanduiding::Intern)
 *       ->toPayload();
 *
 * This is a hand-written slice. Branch 3 generates the write builders for every resource.
 */
final class ZaakWrite extends WriteBuilder
{
    public function identificatie(?string $value): static
    {
        return $this->set('identificatie', $value);
    }

    public function bronorganisatie(?string $value): static
    {
        return $this->set('bronorganisatie', $value);
    }

    public function toelichting(?string $value): static
    {
        return $this->set('toelichting', $value);
    }

    public function zaaktype(Reference|string|null $value): static
    {
        return $this->set('zaaktype', $this->reference($value));
    }

    public function startdatum(DateTimeInterface|string|null $value): static
    {
        return $this->set('startdatum', $this->date($value));
    }

    public function vertrouwelijkheidaanduiding(Vertrouwelijkheidaanduiding|string|null $value): static
    {
        return $this->set('vertrouwelijkheidaanduiding', $this->enum($value));
    }
}
