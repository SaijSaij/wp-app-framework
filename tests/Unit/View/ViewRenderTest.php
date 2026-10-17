<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Unit\View;

use LogicException;
use PHPUnit\Framework\TestCase;
use WebCodingCat\WpAppFramework\Asset\BuildAssets;
use WebCodingCat\WpAppFramework\Block\BlockRegistrar;
use WebCodingCat\WpAppFramework\DependencyInjection\Container;
use WebCodingCat\WpAppFramework\Editor\RenderContext;
use WebCodingCat\WpAppFramework\Manifest\Manifest;
use WebCodingCat\WpAppFramework\Manifest\ManifestGenerator;
use WebCodingCat\WpAppFramework\Tests\Support\Files;
use WebCodingCat\WpAppFramework\Tests\Support\Recorded;
use WebCodingCat\WpAppFramework\Value\ValueFactory;
use WebCodingCat\WpAppFramework\View\ViewAssets;
use WP_Block;

/**
 * Marker of the root element and loading of the view scripts while blocks render.
 *
 * @phpstan-import-type ManifestData from Manifest
 */
final class ViewRenderTest extends TestCase
{
    private const string URI = 'https://example.test/build';

    private string $file;

    private string $build;

    /** @var ManifestData */
    private array $manifest;

    private RenderContext $context;

    protected function setUp(): void
    {
        $this->file = (string) tempnam(sys_get_temp_dir(), 'manifest');
        $this->build = sys_get_temp_dir() . '/wpaf-view-build-' . bin2hex(random_bytes(4));
        mkdir($this->build);
        file_put_contents($this->file, new ManifestGenerator()->generate(ViewBuildTest::buildValid()));
        $this->manifest = Manifest::load($this->file);
        $this->context = new RenderContext();
        $GLOBALS['wp_app_framework_test_blocks'] = [];
        $GLOBALS['wp_app_framework_test_scripts'] = [];
    }

    protected function tearDown(): void
    {
        unlink($this->file);
        Files::remove($this->build);
        unset($GLOBALS['wp_app_framework_test_blocks'], $GLOBALS['wp_app_framework_test_scripts'], $GLOBALS['wp_app_framework_test_doing_filter']);
    }

    /**
     * @param list<string> $dependencies
     */
    private function writeAsset(string $script, string $version, array $dependencies = []): void
    {
        $file = $this->build . '/' . $script . '.asset.php';
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0o777, true);
        }
        file_put_contents($file, '<?php return ' . var_export(['dependencies' => $dependencies, 'version' => $version], true) . ';');
    }

    private function writeAssets(): void
    {
        $this->writeAsset(ViewAssets::RUNTIME, 'r1');
        $this->writeAsset('Faq.view', 'f1', ['my-theme-wp-app-framework-view']);
        $this->writeAsset('Reveal.view', 'v1', ['my-theme-wp-app-framework-view']);
    }

    private function register(): void
    {
        $container = new Container($this->manifest['services'], [RenderContext::class => $this->context]);
        new BlockRegistrar($container, new ValueFactory($this->manifest['values']), $this->context, true, 'my-theme', new ViewAssets('my-theme', new BuildAssets('my-theme', $this->build, self::URI)))
            ->register($this->manifest['blocks']);
    }

    /**
     * @param array<string, mixed> $attributes Prepared attributes, defaults applied.
     */
    private static function render(string $name, array $attributes = []): string
    {
        return Recorded::renderCallback($name)($attributes, '', new WP_Block(['blockName' => $name]));
    }

    public function testRootElementIsMarkedAndTheScriptsAreEnqueuedDeferredInTheFooter(): void
    {
        $this->writeAssets();
        $this->register();

        $html = self::render('fixture/faq', ['question' => 'Why?', 'revealOnScroll' => true]);

        self::assertSame(
            '<div class="wp-block-test faq reveals" data-wp-app-framework-views="{&quot;slug&quot;:&quot;my-theme&quot;,&quot;views&quot;:[{&quot;view&quot;:&quot;Faq.view&quot;},{&quot;view&quot;:&quot;Reveal.view&quot;,&quot;value&quot;:true}]}">Why?</div>',
            $html,
        );
        $args = ['in_footer' => true, 'strategy' => 'defer'];
        self::assertSame([
            'my-theme-wp-app-framework-view' => ['src' => self::URI . '/vendor/wp-app-framework/view.js', 'deps' => [], 'ver' => 'r1', 'args' => $args, 'enqueued' => true, 'before' => []],
            'my-theme-fixture-faq-view' => ['src' => self::URI . '/Faq.view.js', 'deps' => ['my-theme-wp-app-framework-view'], 'ver' => 'f1', 'args' => $args, 'enqueued' => true, 'before' => []],
            'my-theme-support-reveal-on-scroll-view' => ['src' => self::URI . '/Reveal.view.js', 'deps' => ['my-theme-wp-app-framework-view'], 'ver' => 'v1', 'args' => $args, 'enqueued' => true, 'before' => []],
        ], Recorded::scripts());
    }

    public function testTheScriptOfABlockSupportLoadsOnceForAllBlocksUsingIt(): void
    {
        $this->writeAssets();
        $this->register();

        self::assertSame(
            '<p class="wp-block-test reveals" data-wp-app-framework-views="{&quot;slug&quot;:&quot;my-theme&quot;,&quot;views&quot;:[{&quot;view&quot;:&quot;Reveal.view&quot;,&quot;value&quot;:true}]}">Note</p>',
            self::render('fixture/note', ['revealOnScroll' => true]),
        );
        self::render('fixture/faq', ['revealOnScroll' => false]);
        self::render('fixture/note', ['revealOnScroll' => false]);

        self::assertSame(
            ['my-theme-wp-app-framework-view', 'my-theme-support-reveal-on-scroll-view', 'my-theme-fixture-faq-view'],
            array_keys(Recorded::scripts()),
        );
    }

    public function testBlocksWithoutViewScriptsAreNotMarkedAndLoadNothing(): void
    {
        $this->register();

        self::assertSame('<div>Plain</div>', self::render('fixture/plain'));
        self::assertSame([], Recorded::scripts());
    }

    public function testEditorRendersAreNotMarkedAndLoadNothing(): void
    {
        $this->register();

        $html = $this->context->renderForEditor(static fn(): string => self::render('fixture/faq', ['question' => 'Why?', 'revealOnScroll' => false]));

        self::assertSame('<div class="wp-block-test faq">Why?</div>', $html);
        self::assertSame([], Recorded::scripts());
    }

    public function testExcerptsLoadNothing(): void
    {
        $this->register();
        $GLOBALS['wp_app_framework_test_doing_filter'] = 'get_the_excerpt';

        self::assertSame("<p>Why?</p>\n", self::render('fixture/faq', ['question' => 'Why?', 'revealOnScroll' => false]));
        self::assertSame([], Recorded::scripts());
    }

    public function testABlockWithViewScriptsMustPrintItsWrapperAttributes(): void
    {
        $this->register();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Block fixture/bare has view scripts, but did not print its root element with block_wrapper_attributes(), so its views cannot be mounted.');
        self::render('fixture/bare');
    }

    public function testAMissingBuildIsAnError(): void
    {
        $this->register();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The script vendor/wp-app-framework/view is not built: ' . $this->build . '/vendor/wp-app-framework/view.asset.php is missing. Run "npm run build" (or "npm run start") in the theme or plugin my-theme.');
        self::render('fixture/note', ['revealOnScroll' => true]);
    }
}
