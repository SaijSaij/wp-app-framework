<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Block;

use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Saij\WpAppFramework\Asset\BuildAssets;
use Saij\WpAppFramework\Block\BlockRegistrar;
use Saij\WpAppFramework\DependencyInjection\Container;
use Saij\WpAppFramework\Editor\RenderContext;
use Saij\WpAppFramework\I18n\Translator;
use Saij\WpAppFramework\Manifest\Manifest;
use Saij\WpAppFramework\Manifest\ManifestGenerator;
use Saij\WpAppFramework\Tests\Support\Recorded;
use Saij\WpAppFramework\Value\ValueFactory;
use Saij\WpAppFramework\View\ViewAssets;
use WP_Block;
use WP_Block_Type_Registry;

/**
 * Registers the blocks of a generated manifest with the register_block_type()
 * test double and calls their render callbacks.
 *
 * @phpstan-import-type ManifestData from Manifest
 */
final class BlockRegistrarTest extends TestCase
{
    private string $file;
    private string $log;
    private string|false $previousLog;

    /** @var ManifestData */
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
        WP_Block_Type_Registry::get_instance()->unregister('fixture/hero');
    }

    /**
     * @return array<string, array<string, mixed>> Registered block types by name.
     */
    private function register(bool $debug): array
    {
        new BlockRegistrar(new Container($this->manifest['services']), new ValueFactory($this->manifest['values']), new RenderContext(), $debug, 'my-theme', new ViewAssets('my-theme', new BuildAssets('my-theme', '/nonexistent/build', 'https://example.test/build')))
            ->register($this->manifest['blocks']);

        return Recorded::blocks();
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

    public function testTranslatesTitleDescriptionAndKeywordsWithTheTextDomain(): void
    {
        $GLOBALS['wp_app_framework_test_translations'] = ['my-theme' => ['Hero' => 'Großes Banner', 'A hero.' => 'Ein Banner.', 'banner' => 'Kopfbild']];
        try {
            new BlockRegistrar(new Container($this->manifest['services']), new ValueFactory($this->manifest['values']), new RenderContext(), false, 'my-theme', new ViewAssets('my-theme', new BuildAssets('my-theme', '/nonexistent/build', 'https://example.test/build')), translator: new Translator('my-theme'))
                ->register($this->manifest['blocks']);
        } finally {
            unset($GLOBALS['wp_app_framework_test_translations']);
        }

        $hero = Recorded::blocks()['fixture/hero'];
        self::assertSame(['Großes Banner', 'Ein Banner.', ['Kopfbild']], [$hero['title'] ?? null, $hero['description'] ?? null, $hero['keywords'] ?? null]);
    }

    public function testRenderCallbackPassesAttributesContentAndBlockByType(): void
    {
        $this->register(false);

        self::assertSame(
            '[Top|white|2|<p>inner</p>|fixture/hero]',
            Recorded::renderCallback('fixture/hero')(
                ['topline' => 'Top', 'gradient' => 'white', 'buttons' => [['label' => 'A'], ['label' => 'B']], 'className' => 'x'],
                '<p>inner</p>',
                new WP_Block(['blockName' => 'fixture/hero']),
            ),
        );
        self::assertSame('<section><p>inner</p></section>', Recorded::renderCallback('fixture/section')([], '<p>inner</p>', new WP_Block(['blockName' => 'fixture/section'])));
        self::assertSame('title of fixture/post-title', Recorded::renderCallback('fixture/post-title')([], '', new WP_Block(['blockName' => 'fixture/post-title'])));
    }

    public function testErrorsAreLoggedAndRenderNothingWithoutDebug(): void
    {
        $this->register(false);

        self::assertSame('', Recorded::renderCallback('fixture/failing')([], '', new WP_Block(['blockName' => 'fixture/failing'])));
        self::assertStringContainsString(
            'wp-app-framework: block fixture/failing (Saij\\WpAppFramework\\Tests\\Fixtures\\BlockValid\\Failing::render()) failed: RuntimeException: fixture block failure in ',
            (string) file_get_contents($this->log),
        );

        // Invalid attributes fail in the value factory, also logged.
        self::assertSame('', Recorded::renderCallback('fixture/hero')(['gradient' => 'red'], '', new WP_Block(['blockName' => 'fixture/hero'])));
        self::assertStringContainsString('InvalidArgumentException: Value "gradient" must be a value of', (string) file_get_contents($this->log));
    }

    public function testErrorsAreRethrownInDebugMode(): void
    {
        $this->register(true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('fixture block failure');
        Recorded::renderCallback('fixture/failing')([], '', new WP_Block(['blockName' => 'fixture/failing']));
    }

    public function testRenderingWithoutBlockInstanceFailsIfTheBlockNeedsIt(): void
    {
        $this->register(true);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Block fixture/post-title was rendered without a WP_Block instance, but Saij\\WpAppFramework\\Tests\\Fixtures\\BlockValid\\PostTitle::render() needs one.');
        Recorded::renderCallback('fixture/post-title')([]);
    }

    public function testBlockNamesRegisteredElsewhereAreSkippedAndLoggedWithoutDebug(): void
    {
        WP_Block_Type_Registry::get_instance()->register('fixture/hero');

        $registered = $this->register(false);

        self::assertSame(['fixture/failing', 'fixture/post-title', 'fixture/section'], array_keys($registered));
        self::assertStringContainsString(
            'wp-app-framework: Block fixture/hero of my-theme cannot be registered: a block with this name is already registered (by another plugin, theme or framework instance).',
            (string) file_get_contents($this->log),
        );
    }

    public function testBlockNamesRegisteredElsewhereAreAnErrorInDebugMode(): void
    {
        WP_Block_Type_Registry::get_instance()->register('fixture/hero');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Block fixture/hero of my-theme cannot be registered: a block with this name is already registered');

        $this->register(true);
    }
}
