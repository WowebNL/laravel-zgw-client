<?php

declare(strict_types=1);

namespace Woweb\Zgw\Tests\Integration;

use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Woweb\Zgw\Exceptions\InvalidIdentifierException;
use Woweb\Zgw\Facades\Zgw;
use Woweb\Zgw\Tests\TestCase;

class IdentifierValidationTest extends TestCase
{
    private const BASE = 'https://zaken.example.com/zaken/api/v1/';

    /**
     * @return list<array{0: string}>
     */
    public static function maliciousIdentifiers(): array
    {
        return [
            ['../../other'],
            ['..%2fadmin'],
            ['abc/extra'],
            ['abc?inject=1'],
            ['abc#frag'],
            ['has space'],
            [''],
        ];
    }

    #[DataProvider('maliciousIdentifiers')]
    public function test_show_rejects_unsafe_identifier_without_sending(string $id): void
    {
        Http::fake();

        try {
            Zgw::connection('main')->zaken()->zaken()->show($id);
            $this->fail('Expected InvalidIdentifierException for: '.$id);
        } catch (InvalidIdentifierException) {
            $this->addToAssertionCount(1);
        }

        Http::assertNothingSent();
    }

    public function test_delete_rejects_unsafe_identifier(): void
    {
        Http::fake();

        $this->expectException(InvalidIdentifierException::class);

        Zgw::connection('main')->zaken()->zaken()->delete('../zaken/other');
    }

    public function test_nested_endpoint_rejects_unsafe_parent_id(): void
    {
        Http::fake();

        $this->expectException(InvalidIdentifierException::class);

        Zgw::connection('main')->zaken()->zaken()->zaakeigenschappen('../../evil');
    }

    public function test_valid_uuid_is_accepted_and_used_in_url(): void
    {
        $uuid = '7b9f2c1a-3d4e-4f5a-8b6c-1d2e3f4a5b6c';

        Http::fake([
            self::BASE.'zaken/'.$uuid => Http::response(['uuid' => $uuid]),
        ]);

        $zaak = Zgw::connection('main')->zaken()->zaken()->show($uuid);

        $this->assertSame($uuid, $zaak['uuid']);
        Http::assertSent(fn ($request) => $request->url() === self::BASE.'zaken/'.$uuid);
    }
}
