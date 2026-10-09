<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Manifest;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Saij\WpAppFramework\Manifest\Manifest;
use Saij\WpAppFramework\Manifest\ManifestGenerator;
use Saij\WpAppFramework\Tests\Fixtures\Valid;

final class ManifestGeneratorTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = (string) tempnam(sys_get_temp_dir(), 'manifest');
    }

    protected function tearDown(): void
    {
        unlink($this->file);
    }

    public function testManifestIsPlainData(): void
    {
        $code = new ManifestGenerator()->generate(ManifestBuilderTest::buildValid());
        file_put_contents($this->file, $code);
        $manifest = Manifest::load($this->file);

        self::assertStringNotContainsString('function', $code);
        self::assertSame(Manifest::FORMAT, $manifest['format']);
        self::assertContains(Valid\Dto::class, $manifest['candidates']);
        self::assertSame(
            [
                Valid\Clock::class => [],
                Valid\Greeter::class => ['clock' => Valid\Clock::class],
                Valid\Hooks::class => [],
            ],
            $manifest['services'],
        );
        self::assertSame([], $manifest['routes']);

        self::assertSame(
            [
                'name' => 'fixture_filter',
                'priority' => 10,
                'acceptedArgs' => 2,
                'filter' => true,
                'class' => Valid\Hooks::class,
                'method' => 'filter',
                'arguments' => [
                    ['param' => 'value', 'index' => 0],
                    ['param' => 'greeter', 'service' => Valid\Greeter::class],
                    ['param' => 'context', 'index' => 1],
                ],
            ],
            $manifest['hooks'][0],
        );
    }

    public function testManifestWithOtherFormatIsRejected(): void
    {
        file_put_contents($this->file, "<?php return ['format' => 0];");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('has an unsupported format');

        Manifest::load($this->file);
    }
}
