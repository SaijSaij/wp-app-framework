<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Unit\Release;

use WebCodingCat\WpAppFramework\Manifest\Manifest;
use WebCodingCat\WpAppFramework\Release\ReleaseException;
use WebCodingCat\WpAppFramework\Release\TemplateCompilation;

final class TemplateCompilationTest extends ReleaseTestCase
{
    private function manifest(): string
    {
        $file = $this->root . '/manifest.php';
        file_put_contents($file, '<?php return ' . var_export([
            'format' => Manifest::FORMAT,
            'kind' => 'plugin',
            'slug' => 'probe',
            'templateCallables' => [],
            'templateProviders' => [],
            'services' => [],
        ], true) . ';');

        return $file;
    }

    public function testAProjectWithoutTemplatesGetsAnEmptyOutputDirectory(): void
    {
        mkdir($this->root . '/project');
        $output = $this->root . '/compiled';

        self::assertSame(0, TemplateCompilation::compile($this->root . '/project', $this->manifest(), $output));
        // The release scans this directory for unprefixed names; it must exist also without templates.
        self::assertDirectoryExists($output);
    }

    public function testTheOutputDirectoryMustNotExist(): void
    {
        mkdir($this->root . '/compiled');

        $this->expectException(ReleaseException::class);
        $this->expectExceptionMessage('already exists');
        TemplateCompilation::compile($this->root, $this->manifest(), $this->root . '/compiled');
    }
}
