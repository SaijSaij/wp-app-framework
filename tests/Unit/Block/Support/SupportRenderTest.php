<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Unit\Block\Support;

use LogicException;
use PHPUnit\Framework\TestCase;
use WebCodingCat\WpAppFramework\Asset\BuildAssets;
use WebCodingCat\WpAppFramework\Block\BlockRegistrar;
use WebCodingCat\WpAppFramework\Block\BlockTemplateFunctions;
use WebCodingCat\WpAppFramework\DependencyInjection\Container;
use WebCodingCat\WpAppFramework\Editor\RenderContext;
use WebCodingCat\WpAppFramework\Manifest\Manifest;
use WebCodingCat\WpAppFramework\Manifest\ManifestGenerator;
use WebCodingCat\WpAppFramework\Tests\Support\Recorded;
use WebCodingCat\WpAppFramework\Value\ValueFactory;
use WebCodingCat\WpAppFramework\View\ViewAssets;
use WP_Block;

/**
 * Class names of custom block supports on the root element while blocks render.
 *
 * @phpstan-import-type ManifestData from Manifest
 */
final class SupportRenderTest extends TestCase
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

        file_put_contents($this->file, new ManifestGenerator()->generate(SupportCollectorTest::buildValid()));
        $this->manifest = Manifest::load($this->file);
        $GLOBALS['wp_app_framework_test_blocks'] = [];
    }

    protected function tearDown(): void
    {
        ini_set('error_log', (string) $this->previousLog);
        unlink($this->log);
        unlink($this->file);
        unset($GLOBALS['wp_app_framework_test_blocks'], $GLOBALS['wp_app_framework_test_doing_filter']);
    }

    private function register(bool $debug): void
    {
        $context = new RenderContext();
        new BlockRegistrar(new Container($this->manifest['services'], [RenderContext::class => $context]), new ValueFactory($this->manifest['values']), $context, $debug, 'my-theme', new ViewAssets('my-theme', new BuildAssets('my-theme', '/nonexistent/build', 'https://example.test/build')))
            ->register($this->manifest['blocks']);
    }

    /**
     * @param array<string, mixed> $attributes Prepared attributes, defaults applied.
     */
    private static function render(string $name, array $attributes): string
    {
        return Recorded::renderCallback($name)($attributes, '', new WP_Block(['blockName' => $name]));
    }

    public function testRootElementGetsTheClassNamesOfTheSupports(): void
    {
        $this->register(true);

        self::assertSame(
            '<div class="wp-block-test card surface-dark">Hi</div>',
            self::render('fixture/card', ['title' => 'Hi', 'surface' => 'dark', 'framed' => false]),
        );
        self::assertSame(
            '<div class="wp-block-test card surface-light is-framed has-frame accent-blue">Hi</div>',
            self::render('fixture/card', ['title' => 'Hi', 'surface' => 'light', 'framed' => true, 'accent' => 'accent-blue']),
        );
        self::assertSame('<div>plain</div>', self::render('fixture/plain', []));
    }

    public function testWrapperAttributesOutsideOfABlockHaveNoSupportClasses(): void
    {
        self::assertSame('class="wp-block-test x"', new BlockTemplateFunctions(new RenderContext())->blockWrapperAttributes(['class' => 'x']));
    }

    public function testInvalidClassNamesAreAnError(): void
    {
        $this->register(true);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            'Block support WebCodingCat\\WpAppFramework\\Tests\\Fixtures\\SupportValid\\Accent::className() returned the invalid class name "a"b" for the value \'a"b\' of attribute "accent" of block fixture/card; use letters, digits, "-" and "_".',
        );
        self::render('fixture/card', ['surface' => 'dark', 'framed' => false, 'accent' => 'a"b']);
    }

    public function testRenderingWithoutWrapperAttributesIsAnError(): void
    {
        $this->register(true);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            'Block fixture/unwrapped uses block supports (#[UseSupport]), but did not print its root element with block_wrapper_attributes(), so the class names of the supports are missing.',
        );
        self::render('fixture/unwrapped', ['framed' => true]);
    }

    public function testErrorsAreLoggedWithoutDebug(): void
    {
        $this->register(false);

        self::assertSame('', self::render('fixture/unwrapped', ['framed' => true]));
        self::assertSame('', self::render('fixture/card', ['surface' => 'dark', 'framed' => false, 'accent' => 'a b!']));
        $log = (string) file_get_contents($this->log);
        self::assertStringContainsString('wp-app-framework: block fixture/unwrapped (WebCodingCat\\WpAppFramework\\Tests\\Fixtures\\SupportValid\\Unwrapped::render()) failed: LogicException: Block fixture/unwrapped uses block supports', $log);
        self::assertStringContainsString('returned the invalid class name "b!"', $log);
    }

    public function testExcerptsIgnoreTheSupports(): void
    {
        $this->register(true);
        $GLOBALS['wp_app_framework_test_doing_filter'] = 'get_the_excerpt';

        self::assertSame("<p>Hi</p>\n", self::render('fixture/card', ['title' => 'Hi', 'surface' => 'dark', 'framed' => false, 'accent' => 'a"b']));
    }
}
