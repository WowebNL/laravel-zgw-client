<?php

declare(strict_types=1);

namespace Woweb\Zgw\Auth;

use Woweb\Zgw\Exceptions\WeakSecretException;

/**
 * Validates a connection's client_secret against configurable strength rules.
 *
 * The secret is the HS256 signing key, so a weak secret makes JWT forgery more feasible.
 * Rules are supplied per connection (config key [secret_rules]); any rule that is absent
 * falls back to the default below. To disable a rule, set it to 0 / false. Setting
 * [min_length] to 0 with all character requirements false disables validation entirely,
 * which preserves the legacy behaviour for providers that issue short secrets.
 */
class ClientSecretValidator
{
    /** @var array<string, int|bool> */
    private const DEFAULTS = [
        'min_length' => 32,
        'require_uppercase' => false,
        'require_lowercase' => false,
        'require_number' => false,
        'require_symbol' => false,
    ];

    /**
     * @param  array<string, mixed>  $rules
     *
     * @throws WeakSecretException
     */
    public function validate(string $secret, array $rules = []): void
    {
        $minLength = (int) ($rules['min_length'] ?? self::DEFAULTS['min_length']);

        if (strlen($secret) < $minLength) {
            throw new WeakSecretException(
                "ZGW client_secret must be at least {$minLength} characters. ".
                'Configure [secret_rules.min_length] for this connection to change this.'
            );
        }

        $this->assertClass($rules, 'require_uppercase', '/[A-Z]/', $secret, 'an uppercase letter');
        $this->assertClass($rules, 'require_lowercase', '/[a-z]/', $secret, 'a lowercase letter');
        $this->assertClass($rules, 'require_number', '/[0-9]/', $secret, 'a digit');
        $this->assertClass($rules, 'require_symbol', '/[^A-Za-z0-9]/', $secret, 'a symbol');
    }

    /**
     * @param  array<string, mixed>  $rules
     */
    private function assertClass(array $rules, string $key, string $pattern, string $secret, string $label): void
    {
        $required = (bool) ($rules[$key] ?? self::DEFAULTS[$key]);

        if ($required && preg_match($pattern, $secret) !== 1) {
            throw new WeakSecretException(
                "ZGW client_secret must contain {$label}. ".
                "Configure [secret_rules.{$key}] for this connection to change this."
            );
        }
    }
}
