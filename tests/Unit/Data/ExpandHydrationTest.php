<?php

declare(strict_types=1);

namespace Woweb\Zgw\Tests\Unit\Data;

use PHPUnit\Framework\TestCase;
use Woweb\Zgw\Data\Generated\Catalogi\ZaakTypeData;
use Woweb\Zgw\Data\Generated\Zaken\RolData;
use Woweb\Zgw\Data\Generated\Zaken\ZaakData;
use Woweb\Zgw\Data\Generated\Zaken\ZaakEmbedded;
use Woweb\Zgw\Data\Values\Reference;

/**
 * The _expand field hydrates the embedded related resources of an expanded (`?expand=`) response
 * into typed DTOs, including cross-component ones (a zaak's zaaktype is a catalogi DTO), and stays
 * null on a response that was not expanded.
 */
class ExpandHydrationTest extends TestCase
{
    public function test_a_non_expanded_response_leaves_expand_null(): void
    {
        $zaak = ZaakData::from([
            'identificatie' => 'ZAAK-2024-0001',
            'zaaktype' => 'https://catalogi.example.com/catalogi/api/v1/zaaktypen/abc',
        ]);

        $this->assertNull($zaak->_expand);
        // The plain zaaktype field stays a Reference; only _expand carries the expanded object.
        $this->assertInstanceOf(Reference::class, $zaak->zaaktype);
    }

    public function test_an_expanded_response_hydrates_nested_and_cross_component_dtos(): void
    {
        $zaak = ZaakData::from([
            'identificatie' => 'ZAAK-2024-0001',
            'zaaktype' => 'https://catalogi.example.com/catalogi/api/v1/zaaktypen/abc',
            '_expand' => [
                'zaaktype' => [
                    'url' => 'https://catalogi.example.com/catalogi/api/v1/zaaktypen/abc',
                    'identificatie' => 'ZAAKTYPE-1',
                    'omschrijving' => 'Een zaaktype',
                ],
                'rollen' => [
                    [
                        'betrokkeneType' => 'natuurlijk_persoon',
                        'betrokkeneIdentificatie' => ['inpBsn' => '111222333'],
                    ],
                ],
            ],
        ]);

        $this->assertInstanceOf(ZaakEmbedded::class, $zaak->_expand);

        // Cross-component: the embedded zaaktype is a catalogi DTO.
        $this->assertInstanceOf(ZaakTypeData::class, $zaak->_expand->zaaktype);
        $this->assertSame('ZAAKTYPE-1', $zaak->_expand->zaaktype->identificatie);

        // Same-component collection: rollen is a list of RolData, recursively typed.
        $this->assertIsArray($zaak->_expand->rollen);
        $this->assertCount(1, $zaak->_expand->rollen);
        $this->assertInstanceOf(RolData::class, $zaak->_expand->rollen[0]);
        $this->assertSame('111222333', $zaak->_expand->rollen[0]->betrokkeneIdentificatie->inpBsn);
    }

    public function test_an_absent_embedded_relation_is_null(): void
    {
        $zaak = ZaakData::from([
            'identificatie' => 'ZAAK-2024-0001',
            '_expand' => [
                'zaaktype' => [
                    'url' => 'https://catalogi.example.com/catalogi/api/v1/zaaktypen/abc',
                ],
            ],
        ]);

        $this->assertInstanceOf(ZaakEmbedded::class, $zaak->_expand);
        $this->assertInstanceOf(ZaakTypeData::class, $zaak->_expand->zaaktype);
        $this->assertNull($zaak->_expand->status);
        $this->assertNull($zaak->_expand->resultaat);
    }
}
