<?php

declare(strict_types=1);

namespace Woweb\Zgw\Tests\Contract\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;
use Woweb\Zgw\Data\Writes\WriteBuilder;

/**
 * Discovers the committed generated write builders and the ZGW component and schema each was
 * generated from, read from the @zgw-write marker the generator stamps on every builder. The
 * coverage contract test uses this to check the builders' setters against the writable spec fields.
 */
final class GeneratedWriteBuilders
{
    /**
     * @return array<class-string<WriteBuilder>, array{component: string, schema: string}>
     */
    public static function all(): array
    {
        $dir = dirname(__DIR__, 3).'/src/Data/Writes';

        if (! is_dir($dir)) {
            return [];
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
        );

        $builders = [];

        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $relative = substr($file->getPathname(), strlen($dir) + 1, -4);
            $class = 'Woweb\\Zgw\\Data\\Writes\\'.str_replace('/', '\\', $relative);

            if (! class_exists($class) || ! is_subclass_of($class, WriteBuilder::class)) {
                continue;
            }

            $doc = (new ReflectionClass($class))->getDocComment();

            if ($doc !== false && preg_match('/@zgw-write\s+(\w+):(\w+)/', $doc, $m) === 1) {
                /** @var class-string<WriteBuilder> $class */
                $builders[$class] = ['component' => $m[1], 'schema' => $m[2]];
            }
        }

        ksort($builders);

        return $builders;
    }
}
