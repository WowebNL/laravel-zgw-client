<?php

declare(strict_types=1);

namespace Woweb\Zgw\Tests\Contract;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;
use SplFileInfo;
use Woweb\Zgw\Api\Attributes\ZgwResource;
use Woweb\Zgw\Contracts\CreatesResource;
use Woweb\Zgw\Contracts\PatchesResource;
use Woweb\Zgw\Contracts\ReplacesResource;
use Woweb\Zgw\Tests\Contract\Support\GeneratedWriteBuilders;
use Woweb\Zgw\Tests\Contract\Support\ReleaseMatrix;

/**
 * Guards the generated write builders against the standard: every writable (not readOnly) field the
 * specs define has a setter, no setter exists for a field the specs no longer allow writing, and
 * every write-capable endpoint has a builder. When the specs change and the builders are not
 * regenerated, this turns red with the exact field names.
 */
class WriteBuilderCoverageTest extends ContractTestCase
{
    public function test_every_write_builder_matches_its_writable_spec_fields(): void
    {
        $checked = 0;

        foreach (GeneratedWriteBuilders::all() as $builderClass => $source) {
            $writable = $this->unionOfWritableFields($source['component'], $source['schema']);

            if ($writable === []) {
                continue; // This component's specs are not present in this job.
            }

            $setters = $this->setterNames($builderClass);

            // Every field the fetched specs allow writing must have a setter. This holds per release.
            $missing = array_values(array_diff($writable, $setters));
            $this->assertSame([], $missing, "These writable {$source['schema']} fields have no setter on {$builderClass}: ".implode(', ', $missing));

            // The reverse (no setter without a writable field) only holds against the full
            // cross-release union, so only assert it when all releases are present.
            if ($this->hasAllReleases($source['component'])) {
                $orphan = array_values(array_diff($setters, $writable));
                $this->assertSame([], $orphan, "These {$builderClass} setters are not writable in any {$source['schema']} spec: ".implode(', ', $orphan));
            }

            $checked++;
        }

        if ($checked === 0) {
            $this->markTestSkipped('No generated write builder could be checked (no matching specs fetched).');
        }
    }

    public function test_every_write_capable_endpoint_has_a_write_builder(): void
    {
        $builderSchemas = [];
        foreach (GeneratedWriteBuilders::all() as $source) {
            $builderSchemas[$source['component']][$source['schema']] = true;
        }

        $found = 0;

        foreach ($this->endpointClasses() as $class) {
            $writeCapable = is_subclass_of($class, CreatesResource::class)
                || is_subclass_of($class, PatchesResource::class)
                || is_subclass_of($class, ReplacesResource::class);

            if (! $writeCapable) {
                continue;
            }

            $attributes = (new ReflectionClass($class))->getAttributes(ZgwResource::class);
            if ($attributes === []) {
                continue;
            }

            $resource = $attributes[0]->newInstance();
            $this->assertArrayHasKey(
                $resource->schema,
                $builderSchemas[$resource->component] ?? [],
                "Endpoint [{$class}] can write but has no generated {$resource->schema}Write builder. Run `composer dto:generate`."
            );
            $found++;
        }

        $this->assertGreaterThan(0, $found, 'Expected at least one write-capable annotated endpoint.');
    }

    /**
     * @return list<string>
     */
    private function unionOfWritableFields(string $component, string $schema): array
    {
        $fields = [];

        foreach (array_keys(ReleaseMatrix::releases()) as $release) {
            if (ReleaseMatrix::specFile($release, $component) === null) {
                continue;
            }

            $schemaArray = $this->loadSpec($release, $component)->componentSchema($schema);

            if ($schemaArray === null) {
                continue;
            }

            foreach ($this->loadSpec($release, $component)->writablePropertyNames($schemaArray) as $name) {
                $fields[$name] = true;
            }
        }

        return array_keys($fields);
    }

    /**
     * The setter method names a builder declares itself (its writable fields), excluding the
     * WriteBuilder base helpers.
     *
     * @param  class-string  $builderClass
     * @return list<string>
     */
    private function setterNames(string $builderClass): array
    {
        $names = [];

        foreach ((new ReflectionClass($builderClass))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() === $builderClass) {
                $names[] = $method->getName();
            }
        }

        return $names;
    }

    private function hasAllReleases(string $component): bool
    {
        foreach (array_keys(ReleaseMatrix::releases()) as $release) {
            if (ReleaseMatrix::specFile($release, $component) === null) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<class-string>
     */
    private function endpointClasses(): array
    {
        $dir = dirname(__DIR__, 2).'/src/Api/Endpoints';
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        $classes = [];

        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $relative = substr($file->getPathname(), strlen($dir) + 1, -4);
            $class = 'Woweb\\Zgw\\Api\\Endpoints\\'.str_replace('/', '\\', $relative);

            if (class_exists($class)) {
                /** @var class-string $class */
                $classes[] = $class;
            }
        }

        return $classes;
    }
}
