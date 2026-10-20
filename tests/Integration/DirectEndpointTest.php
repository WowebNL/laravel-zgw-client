<?php

declare(strict_types=1);

namespace Woweb\Zgw\Tests\Integration;

use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Woweb\Zgw\Api\Endpoints\DirectEndpoint;
use Woweb\Zgw\Exceptions\ApiRequestException;
use Woweb\Zgw\Exceptions\DisallowedHostException;
use Woweb\Zgw\Facades\Zgw;
use Woweb\Zgw\Tests\TestCase;

class DirectEndpointTest extends TestCase
{
    private const DOCUMENTEN = 'https://documenten.example.com/documenten/api/v1/';

    private const ZAKEN = 'https://zaken.example.com/zaken/api/v1/';

    public function test_connection_exposes_a_direct_endpoint(): void
    {
        $this->assertInstanceOf(DirectEndpoint::class, Zgw::connection('main')->direct());
    }

    public function test_direct_shortcut_fetches_a_resource_by_url(): void
    {
        $url = self::ZAKEN.'zaken/abc-123';

        Http::fake([
            $url => Http::response(['uuid' => 'abc-123', 'url' => $url]),
        ]);

        $resource = Zgw::connection('main')->direct()->getByUrl($url);

        $this->assertSame('abc-123', $resource['uuid']);
    }

    public function test_get_by_url_derives_uuid_from_url_when_absent(): void
    {
        $url = self::DOCUMENTEN.'enkelvoudiginformatieobjecten/doc-uuid';

        Http::fake([
            $url => Http::response(['url' => $url, 'titel' => 'Example']),
        ]);

        $resource = Zgw::connection('main')->direct()->getByUrl($url);

        $this->assertSame('doc-uuid', $resource['uuid']);
    }

    public function test_get_by_url_keeps_an_existing_uuid(): void
    {
        $url = self::ZAKEN.'zaken/from-url';

        Http::fake([
            $url => Http::response(['uuid' => 'from-body', 'url' => $url]),
        ]);

        $resource = Zgw::connection('main')->direct()->getByUrl($url);

        $this->assertSame('from-body', $resource['uuid']);
    }

    public function test_get_by_url_without_url_field_adds_no_uuid(): void
    {
        $url = self::ZAKEN.'zaken/abc-123';

        Http::fake([
            $url => Http::response(['identificatie' => 'ZAAK-001']),
        ]);

        $resource = Zgw::connection('main')->direct()->getByUrl($url);

        $this->assertArrayNotHasKey('uuid', $resource);
    }

    public function test_get_raw_by_url_returns_the_exact_body(): void
    {
        $url = self::DOCUMENTEN.'enkelvoudiginformatieobjecten/doc-uuid/download';

        Http::fake([
            $url => Http::response('PDF-BYTES'),
        ]);

        $content = Zgw::connection('main')->direct()->getRawByUrl($url);

        $this->assertSame('PDF-BYTES', $content);
    }

    public function test_get_raw_by_url_returns_binary_content_unchanged(): void
    {
        $url = self::DOCUMENTEN.'enkelvoudiginformatieobjecten/doc-uuid/download';

        // Every byte value, including NUL, invalid UTF-8 sequences and line endings.
        $bytes = '';
        for ($i = 0; $i < 256; $i++) {
            $bytes .= chr($i);
        }
        $bytes .= "\r\n\x00\xFF\xFE";

        Http::fake([
            $url => Http::response($bytes, 200, ['Content-Type' => 'application/octet-stream']),
        ]);

        $content = Zgw::connection('main')->direct()->getRawByUrl($url);

        $this->assertSame(strlen($bytes), strlen($content));
        $this->assertSame(bin2hex($bytes), bin2hex($content));
    }

    public function test_get_raw_by_url_accepts_any_media_type_and_sends_authorization(): void
    {
        $url = self::DOCUMENTEN.'enkelvoudiginformatieobjecten/doc-uuid/download';

        Http::fake([
            $url => Http::response('PDF-BYTES'),
        ]);

        Zgw::connection('main')->direct()->getRawByUrl($url);

        Http::assertSent(function ($request) use ($url): bool {
            return $request->url() === $url
                && $request->method() === 'GET'
                && $request->header('Accept') === ['*/*']
                && str_starts_with($request->header('Authorization')[0] ?? '', 'Bearer ');
        });
    }

    public function test_get_raw_by_url_to_untrusted_host_is_rejected_without_sending(): void
    {
        Http::fake();

        try {
            Zgw::connection('main')->direct()->getRawByUrl('https://untrusted.example.net/file.pdf');
            $this->fail('Expected DisallowedHostException was not thrown.');
        } catch (DisallowedHostException $e) {
            $this->assertStringContainsString('untrusted.example.net', $e->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_get_raw_by_url_to_unparseable_url_is_rejected_without_sending(): void
    {
        Http::fake();

        try {
            Zgw::connection('main')->direct()->getRawByUrl('not-a-valid-url');
            $this->fail('Expected DisallowedHostException was not thrown.');
        } catch (DisallowedHostException) {
            // expected
        }

        Http::assertNothingSent();
    }

    public function test_get_raw_by_url_to_an_allowed_extra_host_is_permitted(): void
    {
        config()->set('zgw.connections.main.allowed_hosts', ['files.example.com']);

        $url = 'https://files.example.com/download/doc-uuid';

        Http::fake([
            $url => Http::response('FILE-BYTES'),
        ]);

        $this->assertSame('FILE-BYTES', Zgw::connection('main')->direct()->getRawByUrl($url));
    }

    /**
     * @return array<string, array{int}>
     */
    public static function failedStatusProvider(): array
    {
        return [
            'not found' => [404],
            'forbidden' => [403],
            'server error' => [500],
            'bad gateway' => [502],
        ];
    }

    #[DataProvider('failedStatusProvider')]
    public function test_get_raw_by_url_throws_on_a_failed_status(int $status): void
    {
        $url = self::DOCUMENTEN.'enkelvoudiginformatieobjecten/doc-uuid/download';

        Http::fake([
            $url => Http::response('<html>error page</html>', $status),
        ]);

        try {
            Zgw::connection('main')->direct()->getRawByUrl($url);
            $this->fail('Expected ApiRequestException was not thrown.');
        } catch (ApiRequestException $e) {
            $this->assertSame($status, $e->getCode());
            $this->assertSame($status, $e->getResponse()->status());
            // The body may contain personal data, so it must stay off the (logged) message.
            $this->assertStringNotContainsString('error page', $e->getMessage());
        }
    }
}
