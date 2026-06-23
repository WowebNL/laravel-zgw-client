<?php

declare(strict_types=1);

namespace Woweb\Zgw\Tests\Unit\Auth;

use Woweb\Zgw\Auth\ClientSecretValidator;
use Woweb\Zgw\Exceptions\WeakSecretException;
use Woweb\Zgw\Tests\TestCase;

class ClientSecretValidatorTest extends TestCase
{
    private ClientSecretValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new ClientSecretValidator;
    }

    public function test_secret_shorter_than_default_minimum_is_rejected(): void
    {
        $this->expectException(WeakSecretException::class);

        $this->validator->validate('too-short');
    }

    public function test_secret_meeting_default_minimum_passes(): void
    {
        $this->validator->validate(str_repeat('a', 32));

        $this->addToAssertionCount(1);
    }

    public function test_min_length_can_be_lowered_per_connection(): void
    {
        $this->validator->validate('shorty', ['min_length' => 6]);

        $this->addToAssertionCount(1);
    }

    public function test_validation_can_be_disabled(): void
    {
        $this->validator->validate('x', ['min_length' => 0]);

        $this->addToAssertionCount(1);
    }

    public function test_uppercase_requirement_is_enforced_when_enabled(): void
    {
        $rules = ['min_length' => 0, 'require_uppercase' => true];

        $this->validator->validate('HASUPPER', $rules);
        $this->addToAssertionCount(1);

        $this->expectException(WeakSecretException::class);
        $this->validator->validate('nouppercase', $rules);
    }

    public function test_symbol_requirement_is_enforced_when_enabled(): void
    {
        $rules = ['min_length' => 0, 'require_symbol' => true];

        $this->validator->validate('has!symbol', $rules);
        $this->addToAssertionCount(1);

        $this->expectException(WeakSecretException::class);
        $this->validator->validate('alphanumeric123', $rules);
    }

    public function test_combined_rules_all_must_pass(): void
    {
        $rules = [
            'min_length' => 12,
            'require_uppercase' => true,
            'require_lowercase' => true,
            'require_number' => true,
            'require_symbol' => true,
        ];

        $this->validator->validate('Str0ng-Secret!', $rules);
        $this->addToAssertionCount(1);

        // Missing a digit and a symbol.
        $this->expectException(WeakSecretException::class);
        $this->validator->validate('NoDigitsOrSymbols', $rules);
    }
}
