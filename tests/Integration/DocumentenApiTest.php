<?php

declare(strict_types=1);

namespace Woweb\Zgw\Tests\Integration;

use Illuminate\Support\Facades\Http;
use Woweb\Zgw\Api\Endpoints\Documenten\Bestandsdelen;
use Woweb\Zgw\Api\Endpoints\Documenten\Enkelvoudiginformatieobjecten;
use Woweb\Zgw\Api\Endpoints\Documenten\Gebruiksrechten;
use Woweb\Zgw\Api\Endpoints\Documenten\Objectinformatieobjecten;
use Woweb\Zgw\Api\Endpoints\Documenten\Verzendingen;
use Woweb\Zgw\Facades\Zgw;
use Woweb\Zgw\Tests\TestCase;

class DocumentenApiTest extends TestCase
{
    private const BASE = 'https://documenten.example.com/documenten/api/v1/';

    public function test_endpoint_accessors_return_correct_instances(): void
    {
        $documenten = Zgw::connection('main')->documenten();

        $this->assertInstanceOf(Enkelvoudiginformatieobjecten::class, $documenten->enkelvoudiginformatieobjecten());
        $this->assertInstanceOf(Gebruiksrechten::class, $documenten->gebruiksrechten());
        $this->assertInstanceOf(Objectinformatieobjecten::class, $documenten->objectinformatieobjecten());
        $this->assertInstanceOf(Verzendingen::class, $documenten->verzendingen());
        $this->assertInstanceOf(Bestandsdelen::class, $documenten->bestandsdelen());
    }

    public function test_bestandsdelen_put_sends_put_and_returns_array(): void
    {
        $uuid = 'part-uuid';

        Http::fake([
            self::BASE.'bestandsdelen/'.$uuid => Http::response([
                'uuid' => $uuid,
                'volgnummer' => 1,
            ]),
        ]);

        $part = Zgw::connection('main')
            ->documenten()
            ->bestandsdelen()
            ->put($uuid, ['inhoud' => 'base64-chunk']);

        $this->assertSame($uuid, $part['uuid']);

        Http::assertSent(fn ($request) => $request->method() === 'PUT');
    }

    public function test_lock_returns_lock_string(): void
    {
        $uuid = 'doc-uuid';
        $lockString = 'lock-abc-123';

        Http::fake([
            self::BASE.'enkelvoudiginformatieobjecten/'.$uuid.'/lock' => Http::response([
                'lock' => $lockString,
            ]),
        ]);

        $result = Zgw::connection('main')
            ->documenten()
            ->enkelvoudiginformatieobjecten()
            ->lock($uuid);

        $this->assertSame($lockString, $result);
    }

    public function test_unlock_returns_null_on_no_content(): void
    {
        $uuid = 'doc-uuid';

        Http::fake([
            self::BASE.'enkelvoudiginformatieobjecten/'.$uuid.'/unlock' => Http::response(null, 204),
        ]);

        $result = Zgw::connection('main')
            ->documenten()
            ->enkelvoudiginformatieobjecten()
            ->unlock($uuid, 'lock-string');

        $this->assertNull($result);
    }

    public function test_audittrail_returns_collection(): void
    {
        $uuid = 'doc-uuid';

        Http::fake([
            self::BASE.'enkelvoudiginformatieobjecten/'.$uuid.'/audittrail' => Http::response([
                ['uuid' => 'audit-1', 'actie' => 'create'],
                ['uuid' => 'audit-2', 'actie' => 'update'],
            ]),
        ]);

        $trail = Zgw::connection('main')
            ->documenten()
            ->enkelvoudiginformatieobjecten()
            ->audittrail($uuid);

        $this->assertCount(2, $trail);
        $this->assertSame('audit-1', $trail->first()['uuid']);
    }

    public function test_download_returns_binary_body(): void
    {
        $uuid = 'doc-uuid';

        Http::fake([
            self::BASE.'enkelvoudiginformatieobjecten/'.$uuid.'/download' => Http::response('PDF-BYTES'),
        ]);

        $content = Zgw::connection('main')
            ->documenten()
            ->enkelvoudiginformatieobjecten()
            ->download($uuid);

        $this->assertSame('PDF-BYTES', $content);
    }

    public function test_download_forwards_query_parameters(): void
    {
        $uuid = 'doc-uuid';

        Http::fake([
            self::BASE.'enkelvoudiginformatieobjecten/'.$uuid.'/download*' => Http::response('VERSION-2-BYTES'),
        ]);

        $content = Zgw::connection('main')
            ->documenten()
            ->enkelvoudiginformatieobjecten()
            ->download($uuid, ['versie' => 2]);

        $this->assertSame('VERSION-2-BYTES', $content);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'versie=2'));
    }

    public function test_download_accepts_any_media_type_not_only_json(): void
    {
        $uuid = 'doc-uuid';

        Http::fake([
            self::BASE.'enkelvoudiginformatieobjecten/'.$uuid.'/download' => Http::response('PDF-BYTES'),
        ]);

        Zgw::connection('main')
            ->documenten()
            ->enkelvoudiginformatieobjecten()
            ->download($uuid);

        // The binary download endpoint returns raw file content, so the request must not restrict
        // Accept to application/json; otherwise the server responds 406 Not Acceptable.
        Http::assertSent(fn ($request) => $request->header('Accept') === ['*/*']);
    }
}
