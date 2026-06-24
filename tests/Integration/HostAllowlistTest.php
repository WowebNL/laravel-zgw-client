<?php

declare(strict_types=1);

namespace Woweb\Zgw\Tests\Integration;

use Illuminate\Support\Facades\Http;
use Woweb\Zgw\Api\Endpoints\DirectEndpoint;
use Woweb\Zgw\Exceptions\DisallowedHostException;
use Woweb\Zgw\Facades\Zgw;
use Woweb\Zgw\Tests\TestCase;

class HostAllowlistTest extends TestCase
{
    private const BASE = 'https://zaken.example.com/zaken/api/v1/';

    public function test_pagination_next_to_untrusted_host_is_rejected(): void
    {
        Http::fake([
            self::BASE.'zaken' => Http::response([
                'count' => 2,
                'next' => 'https://evil.example.com/zaken?page=2',
                'previous' => null,
                'results' => [
                    ['uuid' => 'p1-1', 'identificatie' => 'ZAAK-001'],
                ],
            ]),
        ]);

        try {
            Zgw::connection('main')->zaken()->zaken()->index()->all();
            $this->fail('Expected DisallowedHostException was not thrown.');
        } catch (DisallowedHostException $e) {
            $this->assertStringContainsString('evil.example.com', $e->getMessage());
        }

        // The first (trusted) request fired, but nothing was sent to the untrusted host.
        Http::assertSentCount(1);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'evil.example.com'));
    }

    public function test_pagination_next_to_another_configured_zgw_host_is_allowed(): void
    {
        Http::fake([
            self::BASE.'zaken' => Http::response([
                'count' => 2,
                'next' => 'https://catalogi.example.com/next-page',
                'previous' => null,
                'results' => [
                    ['uuid' => 'p1-1', 'identificatie' => 'ZAAK-001'],
                ],
            ]),
            'https://catalogi.example.com/next-page' => Http::response([
                'count' => 2,
                'next' => null,
                'previous' => self::BASE.'zaken',
                'results' => [
                    ['uuid' => 'p2-1', 'identificatie' => 'ZAAK-002'],
                ],
            ]),
        ]);

        $zaken = Zgw::connection('main')->zaken()->zaken()->index();

        $this->assertCount(2, $zaken);
    }

    public function test_get_by_url_to_trusted_host_is_allowed(): void
    {
        $url = self::BASE.'zaken/abc-123';

        Http::fake([
            $url => Http::response(['uuid' => 'abc-123', 'identificatie' => 'ZAAK-001']),
        ]);

        $direct = new DirectEndpoint(Zgw::connection('main'));
        $result = $direct->getByUrl($url);

        $this->assertSame('abc-123', $result['uuid']);
    }

    public function test_get_by_url_to_untrusted_host_is_rejected_without_sending(): void
    {
        Http::fake();

        $direct = new DirectEndpoint(Zgw::connection('main'));

        try {
            $direct->getByUrl('https://attacker.example.com/exfiltrate');
            $this->fail('Expected DisallowedHostException was not thrown.');
        } catch (DisallowedHostException $e) {
            $this->assertStringContainsString('attacker.example.com', $e->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_unparseable_next_link_is_rejected(): void
    {
        Http::fake([
            self::BASE.'zaken' => Http::response([
                'count' => 1,
                'next' => 'not-a-valid-url',
                'previous' => null,
                'results' => [
                    ['uuid' => 'p1-1', 'identificatie' => 'ZAAK-001'],
                ],
            ]),
        ]);

        $this->expectException(DisallowedHostException::class);

        Zgw::connection('main')->zaken()->zaken()->index()->all();
    }
}
