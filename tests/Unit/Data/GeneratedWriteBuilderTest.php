<?php

declare(strict_types=1);

namespace Woweb\Zgw\Tests\Unit\Data;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;
use Woweb\Zgw\Data\Writes\WriteBuilder;
use Woweb\Zgw\Tests\Contract\Support\GeneratedWriteBuilders;

/**
 * Exercises every setter of every generated write builder at once. Each setter is called with a
 * value matching its parameter type, then the payload is checked to contain only the fields that
 * were set, in order. This drives the generated setters and the WriteBuilder normalisers, and
 * confirms the presence-based payload semantics hold for all builders.
 */
class GeneratedWriteBuilderTest extends TestCase
{
    public function test_every_setter_records_its_field_in_the_payload(): void
    {
        $checked = 0;

        foreach (array_keys(GeneratedWriteBuilders::all()) as $class) {
            $builder = new $class;
            $this->assertInstanceOf(WriteBuilder::class, $builder);

            $setters = $this->setters($class);
            $this->assertNotSame([], $setters, "{$class} should declare at least one setter.");

            $expected = [];
            foreach ($setters as $method) {
                $builder->{$method->getName()}($this->argumentFor($method));
                $expected[] = $method->getName();
            }

            $payload = $builder->toPayload();

            // Presence: exactly the fields that were set, nothing more.
            $this->assertSame($expected, array_keys($payload), "{$class} payload keys should match the setters called.");

            $checked++;
        }

        $this->assertGreaterThan(20, $checked, 'Expected the full set of generated write builders to be checked.');
    }

    public function test_unset_fields_stay_out_of_the_payload(): void
    {
        foreach (array_keys(GeneratedWriteBuilders::all()) as $class) {
            $this->assertSame([], (new $class)->toPayload(), "a fresh {$class} has an empty payload");
        }
    }

    /**
     * @param  class-string  $class
     * @return list<ReflectionMethod>
     */
    private function setters(string $class): array
    {
        $setters = [];

        foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() === $class) {
                $setters[] = $method;
            }
        }

        return $setters;
    }

    private function argumentFor(ReflectionMethod $method): mixed
    {
        $type = $method->getParameters()[0]->getType();

        // A union setter (Reference|string, DateTimeInterface|string, Enum|string|int) accepts a
        // string, which the WriteBuilder normalisers pass through unchanged.
        if ($type instanceof ReflectionUnionType) {
            return 'https://example.com/api/v1/resource/1';
        }

        $name = $type instanceof ReflectionNamedType ? $type->getName() : 'string';

        return match ($name) {
            'bool' => true,
            'int' => 1,
            'float' => 1.0,
            'array' => ['a' => 'b'],
            default => 'value',
        };
    }
}
