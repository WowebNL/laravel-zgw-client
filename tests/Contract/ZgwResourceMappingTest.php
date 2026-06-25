<?php

declare(strict_types=1);

namespace Woweb\Zgw\Tests\Contract;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;
use Woweb\Zgw\Data\Attributes\ZgwResource;
use Woweb\Zgw\Data\Generated\TypedMap;
use Woweb\Zgw\Tests\Contract\Support\ReleaseMatrix;

/**
 * Guards the endpoint to DTO wiring: every endpoint annotated with #[ZgwResource] names a schema
 * that exists in the specs, maps to a DTO that exists, and appears in the generated TypedMap. A new
 * typed resource added without regenerating the map turns this red.
 */
class ZgwResourceMappingTest extends ContractTestCase
{
    public function test_every_annotated_endpoint_maps_to_an_existing_schema_and_dto(): void
    {
        $annotated = $this->annotatedEndpoints();

        $this->assertNotSame([], $annotated, 'Expected at least one endpoint annotated with #[ZgwResource].');

        foreach ($annotated as $endpoint => $resource) {
            $this->assertArrayHasKey(
                $endpoint,
                TypedMap::MAP,
                "Endpoint [{$endpoint}] is annotated with #[ZgwResource] but missing from TypedMap. Run `composer dto:generate`."
            );

            $dto = TypedMap::MAP[$endpoint];
            $this->assertTrue(class_exists($dto), "Mapped DTO [{$dto}] for [{$endpoint}] does not exist.");

            $this->assertSchemaExists($resource->component, $resource->schema, $endpoint);
        }
    }

    public function test_typed_map_only_references_annotated_endpoints(): void
    {
        $annotated = $this->annotatedEndpoints();

        foreach (array_keys(TypedMap::MAP) as $endpoint) {
            $this->assertArrayHasKey(
                $endpoint,
                $annotated,
                "TypedMap references [{$endpoint}], which has no #[ZgwResource] attribute. Regenerate the map."
            );
        }
    }

    private function assertSchemaExists(string $component, string $schema, string $endpoint): void
    {
        foreach (array_keys(ReleaseMatrix::releases()) as $release) {
            if (ReleaseMatrix::specFile($release, $component) === null) {
                continue;
            }

            if ($this->loadSpec($release, $component)->componentSchema($schema) !== null) {
                $this->addToAssertionCount(1);

                return;
            }
        }

        $this->fail("Schema [{$schema}] declared by [{$endpoint}] was not found in any fetched {$component} spec.");
    }

    /**
     * @return array<class-string, ZgwResource>
     */
    private function annotatedEndpoints(): array
    {
        $dir = dirname(__DIR__, 2).'/src/Api/Endpoints';
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        $found = [];

        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $relative = substr($file->getPathname(), strlen($dir) + 1, -4);
            $class = 'Woweb\\Zgw\\Api\\Endpoints\\'.str_replace('/', '\\', $relative);

            if (! class_exists($class)) {
                continue;
            }

            $attributes = (new ReflectionClass($class))->getAttributes(ZgwResource::class);

            if ($attributes !== []) {
                /** @var class-string $class */
                $found[$class] = $attributes[0]->newInstance();
            }
        }

        return $found;
    }
}
