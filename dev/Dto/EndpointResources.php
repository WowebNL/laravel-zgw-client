<?php

declare(strict_types=1);

namespace Woweb\Zgw\Dev\Dto;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;
use Woweb\Zgw\Data\Attributes\ZgwResource;

/**
 * Discovers the endpoints annotated with #[ZgwResource], the single source of truth for which
 * resources have a typed DTO. The DTO generator and the TypedMap generator both read from here, so
 * the generated DTOs and the endpoint to DTO map cannot disagree about which resources exist.
 */
final class EndpointResources
{
    /**
     * @return array<class-string, ZgwResource> endpoint class => its ZgwResource attribute.
     */
    public static function discover(string $endpointsDir, string $endpointNamespace): array
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($endpointsDir, \FilesystemIterator::SKIP_DOTS)
        );

        $found = [];

        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $relative = substr($file->getPathname(), strlen($endpointsDir) + 1, -4);
            $class = $endpointNamespace.'\\'.str_replace('/', '\\', $relative);

            if (! class_exists($class)) {
                continue;
            }

            $attributes = (new ReflectionClass($class))->getAttributes(ZgwResource::class);

            if ($attributes !== []) {
                /** @var class-string $class */
                $found[$class] = $attributes[0]->newInstance();
            }
        }

        ksort($found);

        return $found;
    }

    /**
     * The Generated sub-namespace for a component (for example "zaken" becomes "Zaken").
     */
    public static function componentNamespace(string $component): string
    {
        return ucfirst($component);
    }

    /**
     * The fully qualified DTO class name a resource maps to.
     */
    public static function dtoClass(string $baseNamespace, ZgwResource $resource): string
    {
        return $baseNamespace.'\\'.self::componentNamespace($resource->component).'\\'.$resource->schema.'Data';
    }
}
