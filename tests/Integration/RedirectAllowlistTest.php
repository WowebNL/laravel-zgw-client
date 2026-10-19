<?php

declare(strict_types=1);

namespace Woweb\Zgw\Tests\Integration;

use Illuminate\Support\Facades\Http;
use Woweb\Zgw\Exceptions\DisallowedHostException;
use Woweb\Zgw\Facades\Zgw;
use Woweb\Zgw\Tests\TestCase;

class RedirectAllowlistTest extends TestCase
{
    private const DOCUMENTEN = 'https://documenten.example.com/documenten/api/v1/';

    private const ZAKEN = 'https://zaken.example.com/zaken/api/v1/';

    private const UNTRUSTED = 'https://untrusted.example.net/target';

    public function test_get_raw_by_url_rejects_a_redirect_to_an_untrusted_origin(): void
    {
        $url = self::DOCUMENTEN.'enkelvoudiginformatieobjecten/doc-uuid/download';

        Http::fake([
            $url => Http::response('', 302, ['Location' => self::UNTRUSTED]),
            self::UNTRUSTED => Http::response('OFF-LIST'),
        ]);

        $this->expectDisallowed(fn () => Zgw::connection('main')->direct()->getRawByUrl($url));

        $this->assertNothingSentToUntrustedHost();
    }

    public function test_get_by_url_rejects_a_redirect_to_an_untrusted_origin(): void
    {
        $url = self::ZAKEN.'zaken/abc-123';

        Http::fake([
            $url => Http::response('', 302, ['Location' => self::UNTRUSTED]),
            self::UNTRUSTED => Http::response(['url' => self::UNTRUSTED]),
        ]);

        $this->expectDisallowed(fn () => Zgw::connection('main')->direct()->getByUrl($url));

        $this->assertNothingSentToUntrustedHost();
    }

    public function test_pagination_rejects_a_next_link_that_redirects_to_an_untrusted_origin(): void
    {
        Http::fake([
            self::ZAKEN.'zaken?page=2' => Http::response('', 302, ['Location' => self::UNTRUSTED]),
            self::ZAKEN.'zaken' => Http::response([
                'count' => 2,
                'next' => self::ZAKEN.'zaken?page=2',
                'previous' => null,
                'results' => [['uuid' => 'p1-1']],
            ]),
            self::UNTRUSTED => Http::response([
                'count' => 2,
                'next' => null,
                'previous' => null,
                'results' => [['uuid' => 'injected']],
            ]),
        ]);

        $this->expectDisallowed(fn () => Zgw::connection('main')->zaken()->zaken()->index()->all());

        $this->assertNothingSentToUntrustedHost();
    }

    public function test_a_redirect_within_the_same_origin_is_followed(): void
    {
        $url = self::DOCUMENTEN.'enkelvoudiginformatieobjecten/doc-uuid/download';
        $target = self::DOCUMENTEN.'enkelvoudiginformatieobjecten/doc-uuid/download-v2';

        Http::fake([
            $target => Http::response('SAME-ORIGIN-BYTES'),
            $url => Http::response('', 302, ['Location' => $target]),
        ]);

        $this->assertSame('SAME-ORIGIN-BYTES', Zgw::connection('main')->direct()->getRawByUrl($url));
    }

    public function test_a_redirect_to_an_allowed_extra_host_is_followed(): void
    {
        config()->set('zgw.connections.main.allowed_hosts', ['files.example.com']);

        $url = self::DOCUMENTEN.'enkelvoudiginformatieobjecten/doc-uuid/download';
        $target = 'https://files.example.com/storage/doc-uuid';

        Http::fake([
            $url => Http::response('', 302, ['Location' => $target]),
            $target => Http::response('FILE-HOST-BYTES'),
        ]);

        $this->assertSame('FILE-HOST-BYTES', Zgw::connection('main')->direct()->getRawByUrl($url));
    }

    public function test_a_rejected_redirect_is_not_retried(): void
    {
        config()->set('zgw.connections.main.retry_times', 2);
        config()->set('zgw.connections.main.retry_sleep_ms', 0);

        $url = self::DOCUMENTEN.'enkelvoudiginformatieobjecten/doc-uuid/download';

        Http::fake([
            $url => Http::response('', 302, ['Location' => self::UNTRUSTED]),
            self::UNTRUSTED => Http::response('OFF-LIST'),
        ]);

        $this->expectDisallowed(fn () => Zgw::connection('main')->direct()->getRawByUrl($url));

        $toAllowedHost = Http::recorded(fn ($request) => $request->url() === $url);

        $this->assertCount(1, $toAllowedHost);
        $this->assertNothingSentToUntrustedHost();
    }

    private function expectDisallowed(callable $call): void
    {
        try {
            $call();
            $this->fail('Expected DisallowedHostException was not thrown.');
        } catch (DisallowedHostException $e) {
            $this->assertStringContainsString('untrusted.example.net', $e->getMessage());
        }
    }

    private function assertNothingSentToUntrustedHost(): void
    {
        $this->assertCount(
            0,
            Http::recorded(fn ($request) => str_contains($request->url(), 'untrusted.example.net')),
        );
    }
}
