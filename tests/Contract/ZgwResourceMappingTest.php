<?php

declare(strict_types=1);

namespace Woweb\Zgw\Tests\Contract;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;
use Woweb\Zgw\Contracts\ListsResources;
use Woweb\Zgw\Contracts\ShowsResource;
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

    public function test_every_read_capable_endpoint_is_annotated(): void
    {
        $annotated = $this->annotatedEndpoints();

        foreach ($this->endpointClasses() as $class) {
            $readable = is_subclass_of($class, ListsResources::class) || is_subclass_of($class, ShowsResource::class);

            if (! $readable) {
                continue;
            }

            $this->assertArrayHasKey(
                $class,
                $annotated,
                "Endpoint [{$class}] can list or show resources but has no #[ZgwResource] attribute, so it has no typed DTO."
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

        // A resource can be release-specific (for example ZaakNotitie is ZGW 1.7+), so when only
        // some releases are fetched its schema may legitimately be absent here. Only fail when all
        // releases are present (the all-releases contract job); otherwise defer to that job.
        if ($this->hasAllReleases($component)) {
            $this->fail("Schema [{$schema}] declared by [{$endpoint}] was not found in any {$component} spec.");
        }
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
     * @return array<class-string, ZgwResource>
     */
    private function annotatedEndpoints(): array
    {
        $found = [];

        foreach ($this->endpointClasses() as $class) {
            $attributes = (new ReflectionClass($class))->getAttributes(ZgwResource::class);

            if ($attributes !== []) {
                $found[$class] = $attributes[0]->newInstance();
            }
        }

        return $found;
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
