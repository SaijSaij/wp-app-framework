<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Block;

use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Saij\WpAppFramework\Block\BlockRegistrar;
use Saij\WpAppFramework\DependencyInjection\Container;
use Saij\WpAppFramework\Editor\RenderContext;
use Saij\WpAppFramework\Manifest\Manifest;
use Saij\WpAppFramework\Manifest\ManifestGenerator;
use Saij\WpAppFramework\Value\ValueFactory;
use WP_Block;

/**
 * Registers the blocks of a generated manifest with the register_block_type()
 * test double and calls their render callbacks.
 */
final class BlockRegistrarTest extends TestCase
{
    private string $file;
    private string $log;
    private string|false $previousLog;

    /** @var array<string, mixed> */
    private array $manifest;

    protected function setUp(): void
    {
        $this->file = (string) tempnam(sys_get_temp_dir(), 'manifest');
        $this->log = (string) tempnam(sys_get_temp_dir(), 'log');
        $this->previousLog = ini_set('error_log', $this->log);

        file_put_contents($this->file, new ManifestGenerator()->generate(BlockCollectorTest::buildValid()));
        $this->manifest = Manifest::load($this->file);
        $GLOBALS['wp_app_framework_test_blocks'] = [];
    }

    protected function tearDown(): void
    {
        ini_set('error_log', (string) $this->previousLog);
        unlink($this->log);
        unlink($this->file);
        unset($GLOBALS['wp_app_framework_test_blocks']);
    }

    /**
     * @return array<string, array<string, mixed>> Registered block types by name.
     */
    private function register(bool $debug): array
    {
        new BlockRegistrar(new Container($this->manifest['services']), new ValueFactory($this->manifest['values']), new RenderContext(), $debug)
            ->register($this->manifest['blocks']);

        return $GLOBALS['wp_app_framework_test_blocks'];
    }

    public function testRegistersTheManifestArgumentsWithARenderCallback(): void
    {
        $registered = $this->register(false);

        self::assertSame(['fixture/failing', 'fixture/hero', 'fixture/post-title', 'fixture/section'], array_keys($registered));
        $hero = $registered['fixture/hero'];
        self::assertIsCallable($hero['render_callback']);
        unset($hero['render_callback']);
        self::assertSame($this->manifest['blocks'][1]['args'], $hero);
    }

    public function testRenderCallbackPassesAttributesContentAndBlockByType(): void
    {
        $registered = $this->register(false);

        self::assertSame(
            '[Top|white|2|<p>inner</p>|fixture/hero]',
            $registered['fixture/hero']['render_callback'](
                ['topline' => 'Top', 'gradient' => 'white', 'buttons' => [['label' => 'A'], ['label' => 'B']], 'className' => 'x'],
                '<p>inner</p>',
                new WP_Block('fixture/hero'),
            ),
        );
        self::assertSame('<section><p>inner</p></section>', $registered['fixture/section']['render_callback']([], '<p>inner</p>', new WP_Block('fixture/section')));
        self::assertSame('title of fixture/post-title', $registered['fixture/post-title']['render_callback']([], '', new WP_Block('fixture/post-title')));
    }

    public function testErrorsAreLoggedAndRenderNothingWithoutDebug(): void
    {
        $registered = $this->register(false);

        self::assertSame('', $registered['fixture/failing']['render_callback']([], '', new WP_Block('fixture/failing')));
        self::assertStringContainsString(
            'wp-app-framework: block fixture/failing (Saij\\WpAppFramework\\Tests\\Fixtures\\BlockValid\\Failing::render()) failed: RuntimeException: fixture block failure in ',
            (string) file_get_contents($this->log),
        );

        // Invalid attributes fail in the value factory, also logged.
        self::assertSame('', $registered['fixture/hero']['render_callback'](['gradient' => 'red'], '', new WP_Block('fixture/hero')));
        self::assertStringContainsString('InvalidArgumentException: Value "gradient" must be a value of', (string) file_get_contents($this->log));
    }

    public function testErrorsAreRethrownInDebugMode(): void
    {
        $registered = $this->register(true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('fixture block failure');
        $registered['fixture/failing']['render_callback']([], '', new WP_Block('fixture/failing'));
    }

    public function testRenderingWithoutBlockInstanceFailsIfTheBlockNeedsIt(): void
    {
        $registered = $this->register(true);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Block fixture/post-title was rendered without a WP_Block instance, but Saij\\WpAppFramework\\Tests\\Fixtures\\BlockValid\\PostTitle::render() needs one.');
        $registered['fixture/post-title']['render_callback']([]);
    }
}
