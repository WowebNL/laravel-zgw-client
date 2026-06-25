<?php

declare(strict_types=1);

namespace Woweb\Zgw\Tests\Unit\Data;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;
use Woweb\Zgw\Data\Generated\Enums\Vertrouwelijkheidaanduiding;
use Woweb\Zgw\Data\Values\Reference;
use Woweb\Zgw\Data\Writes\ZaakWrite;

class ZaakWriteTest extends TestCase
{
    public function test_payload_contains_only_fields_that_were_set(): void
    {
        $payload = (new ZaakWrite)
            ->identificatie('ZAAK-1')
            ->toPayload();

        $this->assertSame(['identificatie' => 'ZAAK-1'], $payload);
        $this->assertArrayNotHasKey('toelichting', $payload);
        $this->assertArrayNotHasKey('startdatum', $payload);
    }

    public function test_explicit_null_is_kept_so_a_patch_can_clear_a_field(): void
    {
        $payload = (new ZaakWrite)
            ->toelichting(null)
            ->toPayload();

        $this->assertArrayHasKey('toelichting', $payload);
        $this->assertNull($payload['toelichting']);
    }

    public function test_normalises_references_dates_and_enums(): void
    {
        $payload = (new ZaakWrite)
            ->zaaktype(new Reference('https://catalogi.example.com/catalogi/api/v1/zaaktypen/abc'))
            ->startdatum(CarbonImmutable::parse('2024-03-01 09:00:00'))
            ->vertrouwelijkheidaanduiding(Vertrouwelijkheidaanduiding::Intern)
            ->toPayload();

        $this->assertSame('https://catalogi.example.com/catalogi/api/v1/zaaktypen/abc', $payload['zaaktype']);
        $this->assertSame('2024-03-01', $payload['startdatum']);
        $this->assertSame('intern', $payload['vertrouwelijkheidaanduiding']);
    }
}
