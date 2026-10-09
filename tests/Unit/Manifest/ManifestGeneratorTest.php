<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Manifest;

use ArgumentCountError;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Saij\WpAppFramework\DependencyInjection\Container;
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

    public function testGeneratedManifestRuns(): void
    {
        file_put_contents($this->file, new ManifestGenerator()->generate(ManifestBuilderTest::buildValid()));
        $manifest = Manifest::load($this->file);

        self::assertSame(Manifest::FORMAT, $manifest['format']);
        self::assertContains(Valid\Dto::class, $manifest['candidates']);
        self::assertSame([Valid\Clock::class, Valid\Greeter::class, Valid\Hooks::class], array_keys($manifest['services']));

        $container = new Container($manifest['services']);
        $hooks = array_column($manifest['hooks'], null, 'name');

        $filter = $hooks['fixture_filter'];
        self::assertSame(Valid\Hooks::class . '::filter()', $filter['source']);
        self::assertSame(
            'Value: Hello cli at noon!',
            ($filter['callback'])($container, ['Value: ', new Valid\Context('cli')]),
        );

        self::assertNull(($hooks['fixture_action']['callback'])($container, []));
        self::assertSame(['action'], $container->get(Valid\Hooks::class)->calls);
    }

    public function testMissingOptionalWordPressArgumentUsesTheDefault(): void
    {
        file_put_contents($this->file, new ManifestGenerator()->generate(ManifestBuilderTest::buildValid()));
        $manifest = Manifest::load($this->file);
        $filter = array_column($manifest['hooks'], null, 'name')['fixture_filter'];

        self::assertSame(
            'Value: Hello nobody at noon!',
            ($filter['callback'])(new Container($manifest['services']), ['Value: ']),
        );
    }

    public function testMissingRequiredWordPressArgumentFailsLikeAPlainCallback(): void
    {
        file_put_contents($this->file, new ManifestGenerator()->generate(ManifestBuilderTest::buildValid()));
        $manifest = Manifest::load($this->file);
        $filter = array_column($manifest['hooks'], null, 'name')['fixture_filter'];

        $this->expectException(ArgumentCountError::class);

        ($filter['callback'])(new Container($manifest['services']), []);
    }

    public function testManifestWithOtherFormatIsRejected(): void
    {
        file_put_contents($this->file, "<?php return ['format' => 0];");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('has an unsupported format');

        Manifest::load($this->file);
    }
}
