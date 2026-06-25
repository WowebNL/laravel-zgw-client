<?php

declare(strict_types=1);

namespace Woweb\Zgw\Tests\Contract;

use Woweb\Zgw\Dev\Dto\DtoGenerator;
use Woweb\Zgw\Tests\Contract\Support\ReleaseMatrix;

/**
 * Proves the generator emits correct @since / @deprecated metadata against the real standard, using
 * a resource that genuinely changed across releases. EnkelvoudigInformatieObject gained
 * tonenAanInitiator in ZGW 1.6 and isGereedVoorPublicatie in 1.7, so the generated DTO must carry
 * the matching @since annotations. The output is written to a temp dir and not committed; this
 * complements DtoVersionMetadataTest, which checks the committed DTOs.
 */
class GeneratorVersionMetadataTest extends ContractTestCase
{
    private string $tmpDir = '';

    protected function setUp(): void
    {
        parent::setUp();

        $haveAll = ReleaseMatrix::specFile('1.5', 'documenten') !== null
            && ReleaseMatrix::specFile('1.6', 'documenten') !== null
            && ReleaseMatrix::specFile('1.7', 'documenten') !== null;

        if (! $haveAll) {
            $this->markTestSkipped('Documenten specs for 1.5, 1.6 and 1.7 are required for this test.');
        }

        $this->tmpDir = sys_get_temp_dir().'/zgw-dto-gen-'.bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        if ($this->tmpDir !== '' && is_dir($this->tmpDir)) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->tmpDir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($files as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($this->tmpDir);
        }

        parent::tearDown();
    }

    public function test_generator_stamps_since_for_fields_added_in_later_releases(): void
    {
        (new DtoGenerator(
            component: 'documenten',
            rootSchemas: ['EnkelvoudigInformatieObject'],
            namespace: 'Woweb\\Zgw\\Data\\Generated\\Documenten',
            outDir: $this->tmpDir,
        ))->generate();

        $file = $this->tmpDir.'/EnkelvoudigInformatieObjectData.php';
        $this->assertFileExists($file);

        $code = (string) file_get_contents($file);

        $this->assertMatchesRegularExpression(
            '/@since ZGW 1\.6.*\n\s*public[^\n]*\$tonenAanInitiator;/',
            $code,
            'Expected tonenAanInitiator to be annotated @since ZGW 1.6.'
        );
        $this->assertMatchesRegularExpression(
            '/@since ZGW 1\.7.*\n\s*public[^\n]*\$isGereedVoorPublicatie;/',
            $code,
            'Expected isGereedVoorPublicatie to be annotated @since ZGW 1.7.'
        );
    }
}
