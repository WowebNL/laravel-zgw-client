<?php

declare(strict_types=1);

namespace Woweb\Zgw\Tests\Integration;

use Illuminate\Support\Facades\Http;
use Woweb\Zgw\Exceptions\ApiRequestException;
use Woweb\Zgw\Exceptions\ValidationException;
use Woweb\Zgw\Facades\Zgw;
use Woweb\Zgw\Tests\TestCase;

class ValidationErrorTest extends TestCase
{
    private const BASE = 'https://zaken.example.com/zaken/api/v1/';

    public function test_validatiefout_body_becomes_a_validation_exception(): void
    {
        Http::fake([
            self::BASE.'zaken' => Http::response([
                'type' => 'http://example.com/ref/fouten/ValidationError/',
                'code' => 'invalid',
                'title' => 'Invalid input.',
                'status' => 400,
                'detail' => '',
                'invalidParams' => [
                    ['name' => 'zaaktype', 'code' => 'required', 'reason' => 'Dit veld is vereist.'],
                    ['name' => 'bronorganisatie', 'code' => 'invalid', 'reason' => 'Ongeldig RSIN.'],
                ],
            ], 400),
        ]);

        try {
            Zgw::connection('main')->zaken()->zaken()->store(['foo' => 'bar']);
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            // Backwards compatible: still an ApiRequestException.
            $this->assertInstanceOf(ApiRequestException::class, $e);

            $this->assertSame('invalid', $e->validationCode());
            $this->assertSame('Invalid input.', $e->title());

            $params = $e->invalidParams();
            $this->assertCount(2, $params);
            $this->assertSame('zaaktype', $params[0]->name);
            $this->assertSame('required', $params[0]->code);
            $this->assertSame('Dit veld is vereist.', $params[0]->reason);
        }
    }

    public function test_non_validation_error_stays_a_plain_api_request_exception(): void
    {
        Http::fake([
            self::BASE.'zaken' => Http::response(['detail' => 'Not found'], 404),
        ]);

        $this->expectException(ApiRequestException::class);

        try {
            Zgw::connection('main')->zaken()->zaken()->store(['foo' => 'bar']);
        } catch (ValidationException $e) {
            $this->fail('A 404 without invalidParams must not become a ValidationException.');
        }
    }
}
