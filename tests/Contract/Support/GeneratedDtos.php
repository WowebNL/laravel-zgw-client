<?php

declare(strict_types=1);

namespace Woweb\Zgw\Tests\Contract\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;
use Woweb\Zgw\Data\Data;

/**
 * Discovers the committed generated DTOs and the ZGW component and schema each was generated from,
 * read from the machine-readable @zgw-schema marker the generator stamps on every DTO. The coverage
 * and version-metadata contract tests share this so they check the same set against the specs.
 */
final class GeneratedDtos
{
    /**
     * @return array<class-string<Data>, array{component: string, schema: string}>
     */
    public static function all(): array
    {
        $dir = dirname(__DIR__, 2).'/src/Data/Generated';

        if (! is_dir($dir)) {
            return [];
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
        );

        $dtos = [];

        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $relative = substr($file->getPathname(), strlen($dir) + 1, -4);
            $class = 'Woweb\\Zgw\\Data\\Generated\\'.str_replace('/', '\\', $relative);

            if (! class_exists($class) || ! is_subclass_of($class, Data::class)) {
                continue;
            }

            $doc = (new ReflectionClass($class))->getDocComment();

            if ($doc !== false && preg_match('/@zgw-schema\s+(\w+):(\w+)/', $doc, $m) === 1) {
                /** @var class-string<Data> $class */
                $dtos[$class] = ['component' => $m[1], 'schema' => $m[2]];
            }
        }

        ksort($dtos);

        return $dtos;
    }
}
