<?php

declare(strict_types=1);

namespace Woweb\Zgw\Auth;

use Firebase\JWT\JWT;
use Woweb\Zgw\Contracts\AuthorizationInterface;
use Woweb\Zgw\Exceptions\AuthorizationException;

class Authorization implements AuthorizationInterface
{
    /**
     * Generate a Bearer JWT token for the given connection config.
     *
     * The JWT payload follows the ZGW authorization spec and the official vng-api-common
     * reference implementation: identity-only claims signed with HS256.
     * https://vng-realisatie.github.io/gemma-zaken/themas/achtergronddocumentatie/authenticatie-autorisatie
     *
     * An "exp" claim is added on top of the reference claim set. The VNG guidance advises a
     * short expiry because JWTs cannot be revoked, and the provider stack (PyJWT) honours
     * "exp" when present. The lifetime is configurable via [jwt_expiry] in seconds; set it to
     * 0 to omit the claim entirely for strict legacy providers.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws AuthorizationException When required config keys are missing.
     */
    public function getToken(array $config): string
    {
        $clientId = $config['client_id'] ?? '';
        $clientSecret = $config['client_secret'] ?? '';

        if ($clientId === '' || $clientSecret === '') {
            throw new AuthorizationException(
                'ZGW authorization requires a non-empty [client_id] and [client_secret].'
            );
        }

        $issuedAt = time();
        $expiresIn = (int) ($config['jwt_expiry'] ?? 300);

        $payload = [
            'iss' => $clientId,
            'iat' => $issuedAt,
            'client_id' => $clientId,
            'user_id' => $config['user_id'] ?? '',
            'user_representation' => $config['user_representation'] ?? '',
        ];

        if ($expiresIn > 0) {
            $payload['exp'] = $issuedAt + $expiresIn;
        }

        $token = JWT::encode($payload, $clientSecret, 'HS256');

        return 'Bearer '.$token;
    }
}
