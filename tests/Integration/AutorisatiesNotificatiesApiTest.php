<?php

declare(strict_types=1);

namespace Woweb\Zgw\Tests\Integration;

use Illuminate\Support\Facades\Http;
use Woweb\Zgw\Api\Endpoints\Autorisaties\Applicaties;
use Woweb\Zgw\Api\Endpoints\Notificaties\Abonnementen;
use Woweb\Zgw\Api\Endpoints\Notificaties\Kanalen;
use Woweb\Zgw\Api\Endpoints\Notificaties\Notificaties;
use Woweb\Zgw\Facades\Zgw;
use Woweb\Zgw\Tests\TestCase;

class AutorisatiesNotificatiesApiTest extends TestCase
{
    private const AC = 'https://autorisaties.example.com/autorisaties/api/v1/';

    private const NRC = 'https://notificaties.example.com/notificaties/api/v1/';

    public function test_endpoint_accessors_return_correct_instances(): void
    {
        $this->assertInstanceOf(Applicaties::class, Zgw::connection('main')->autorisaties()->applicaties());
        $this->assertInstanceOf(Abonnementen::class, Zgw::connection('main')->notificaties()->abonnementen());
        $this->assertInstanceOf(Kanalen::class, Zgw::connection('main')->notificaties()->kanalen());
        $this->assertInstanceOf(Notificaties::class, Zgw::connection('main')->notificaties()->notificaties());
    }

    public function test_applicaties_consumer_lookup_sends_client_id(): void
    {
        Http::fake([
            self::AC.'applicaties/consumer*' => Http::response(['url' => self::AC.'applicaties/app-1', 'clientIds' => ['my-client']]),
        ]);

        $applicatie = Zgw::connection('main')->autorisaties()->applicaties()->consumer('my-client');

        $this->assertSame(self::AC.'applicaties/app-1', $applicatie['url']);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'clientId=my-client'));
    }

    public function test_abonnement_store_posts_to_abonnement_endpoint(): void
    {
        Http::fake([
            self::NRC.'abonnement' => Http::response(['url' => self::NRC.'abonnement/ab-1'], 201),
        ]);

        $abonnement = Zgw::connection('main')->notificaties()->abonnementen()->store([
            'callbackUrl' => 'https://app.example.com/webhook',
            'auth' => 'Bearer xyz',
            'kanalen' => [['naam' => 'zaken']],
        ]);

        $this->assertSame(self::NRC.'abonnement/ab-1', $abonnement['url']);
        Http::assertSent(fn ($request) => $request->method() === 'POST' && $request->url() === self::NRC.'abonnement');
    }

    public function test_kanalen_index_lists_channels(): void
    {
        Http::fake([
            self::NRC.'kanaal' => Http::response([
                'count' => 1, 'next' => null, 'previous' => null,
                'results' => [['url' => self::NRC.'kanaal/zaken', 'naam' => 'zaken']],
            ]),
        ]);

        $kanalen = Zgw::connection('main')->notificaties()->kanalen()->index();

        $this->assertSame('zaken', $kanalen->first()['naam']);
    }

    public function test_notificatie_send_posts_to_notificaties_endpoint(): void
    {
        Http::fake([
            self::NRC.'notificaties' => Http::response([], 201),
        ]);

        Zgw::connection('main')->notificaties()->notificaties()->send([
            'kanaal' => 'zaken',
            'hoofdObject' => 'https://zaken.example.com/zaken/api/v1/zaken/abc',
            'resource' => 'status',
            'actie' => 'create',
        ]);

        Http::assertSent(fn ($request) => $request->method() === 'POST' && $request->url() === self::NRC.'notificaties');
    }
}
