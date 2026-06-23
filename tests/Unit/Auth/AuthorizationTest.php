<?php

declare(strict_types=1);

namespace Woweb\Zgw\Tests\Unit\Auth;

use Woweb\Zgw\Auth\Authorization;
use Woweb\Zgw\Exceptions\AuthorizationException;
use Woweb\Zgw\Tests\TestCase;

class AuthorizationTest extends TestCase
{
    // HS256 requires a key of at least 256 bits (32 bytes); firebase/php-jwt v7 enforces this.
    private const SECRET = 'test-secret-with-sufficient-entropy-0123456789';

    private Authorization $authorization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authorization = new Authorization;
    }

    public function test_it_generates_a_bearer_token(): void
    {
        $config = [
            'client_id' => 'my-client',
            'client_secret' => self::SECRET,
            'user_id' => 'user-1',
            'user_representation' => 'Jane Doe',
        ];

        $token = $this->authorization->getToken($config);

        $this->assertStringStartsWith('Bearer ', $token);
        $this->assertGreaterThan(30, strlen($token));
    }

    public function test_it_throws_when_client_id_is_missing(): void
    {
        $this->expectException(AuthorizationException::class);

        $this->authorization->getToken([
            'client_id' => '',
            'client_secret' => 'secret',
        ]);
    }

    public function test_it_throws_when_client_secret_is_missing(): void
    {
        $this->expectException(AuthorizationException::class);

        $this->authorization->getToken([
            'client_id' => 'client',
            'client_secret' => '',
        ]);
    }

    public function test_token_contains_expected_jwt_claims(): void
    {
        $config = [
            'client_id' => 'test-client',
            'client_secret' => self::SECRET,
            'user_id' => 'u42',
            'user_representation' => 'John',
        ];

        $token = $this->authorization->getToken($config);
        $jwt = explode(' ', $token)[1];

        // Decode payload (second segment) without verifying signature
        $payloadJson = base64_decode(str_pad(
            strtr(explode('.', $jwt)[1], '-_', '+/'),
            (int) ceil(strlen(explode('.', $jwt)[1]) / 4) * 4,
            '=',
        ));

        $payload = json_decode($payloadJson, true);

        $this->assertSame('test-client', $payload['iss']);
        $this->assertSame('test-client', $payload['client_id']);
        $this->assertSame('u42', $payload['user_id']);
        $this->assertSame('John', $payload['user_representation']);
        $this->assertArrayHasKey('iat', $payload);
    }

    public function test_token_has_default_expiry_of_300_seconds(): void
    {
        $payload = $this->decodePayload($this->authorization->getToken([
            'client_id' => 'c',
            'client_secret' => self::SECRET,
        ]));

        $this->assertArrayHasKey('exp', $payload);
        $this->assertSame($payload['iat'] + 300, $payload['exp']);
    }

    public function test_expiry_is_configurable(): void
    {
        $payload = $this->decodePayload($this->authorization->getToken([
            'client_id' => 'c',
            'client_secret' => self::SECRET,
            'jwt_expiry' => 60,
        ]));

        $this->assertSame($payload['iat'] + 60, $payload['exp']);
    }

    public function test_expiry_is_omitted_when_set_to_zero(): void
    {
        $payload = $this->decodePayload($this->authorization->getToken([
            'client_id' => 'c',
            'client_secret' => self::SECRET,
            'jwt_expiry' => 0,
        ]));

        $this->assertArrayNotHasKey('exp', $payload);
        $this->assertArrayHasKey('iat', $payload);
    }

    /**
     * Decode a JWT payload (second segment) without verifying the signature.
     *
     * @return array<string, mixed>
     */
    private function decodePayload(string $bearerToken): array
    {
        $jwt = explode(' ', $bearerToken)[1];
        $segment = explode('.', $jwt)[1];

        $json = base64_decode(str_pad(
            strtr($segment, '-_', '+/'),
            (int) ceil(strlen($segment) / 4) * 4,
            '=',
        ));

        return json_decode($json, true);
    }
}
