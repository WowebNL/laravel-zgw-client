<?php

declare(strict_types=1);

namespace Woweb\Zgw\Tests\Contract;

use Symfony\Component\Yaml\Exception\ParseException;
use Woweb\Zgw\Tests\Contract\Support\ReleaseMatrix;
use Woweb\Zgw\Tests\Contract\Support\SpecModel;
use Woweb\Zgw\Tests\TestCase;

/**
 * Base class for the contract suite, which validates the client against the real VNG OpenAPI
 * specs for every supported ZGW release. The specs are fetched (not committed) by
 * scripts/fetch-specs.php, so the suite skips cleanly when they are absent and `composer test`
 * still runs offline.
 */
abstract class ContractTestCase extends TestCase
{
    /** @var array<string, SpecModel> */
    private static array $specCache = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (! ReleaseMatrix::specsAvailable()) {
            $this->markTestSkipped(
                'No ZGW OpenAPI spec fixtures found. Run `composer contract:fetch` (or `php scripts/fetch-specs.php`) first.'
            );
        }
    }

    protected function loadSpec(string $release, string $component): SpecModel
    {
        $key = "{$release}/{$component}";

        if (! isset(self::$specCache[$key])) {
            $file = ReleaseMatrix::specFile($release, $component);

            if ($file === null) {
                $this->markTestSkipped("Spec fixture for {$key} is not present.");
            }

            try {
                self::$specCache[$key] = SpecModel::fromFile($file);
            } catch (ParseException $e) {
                $this->markTestSkipped(
                    "Spec fixture for {$key} could not be parsed by symfony/yaml ({$e->getMessage()}). ".
                    'Enable the libyaml PHP extension (ext-yaml) for full coverage.'
                );
            }
        }

        return self::$specCache[$key];
    }

    /**
     * Reduce a fully qualified request URL to the operation path relative to the API root,
     * i.e. everything after the `/{api}/api/v1` base that ZgwConnection builds.
     */
    protected function relativePath(string $url): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $marker = '/api/v1';
        $position = strpos($path, $marker);

        if ($position === false) {
            return $path;
        }

        $relative = substr($path, $position + strlen($marker));

        return $relative === '' ? '/' : $relative;
    }
}
