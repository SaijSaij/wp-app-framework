<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit;

use LogicException;
use PHPUnit\Framework\TestCase;
use Saij\WpAppFramework\Api\InstanceBridge;
use Saij\WpAppFramework\ChildTheme;
use Saij\WpAppFramework\Editor\RenderContext;
use Saij\WpAppFramework\Instance;
use Saij\WpAppFramework\Manifest\ManifestBuilder;
use Saij\WpAppFramework\Manifest\ManifestGenerator;
use Saij\WpAppFramework\Project\ProjectKind;
use Saij\WpAppFramework\Tests\Fixtures\ParentTheme\SectionsApi;
use Saij\WpAppFramework\Tests\Support\Files;
use Saij\WpAppFramework\Tests\Support\Hooks;
use Saij\WpAppFramework\Tests\Unit\Manifest\ManifestBuilderTest;
use Saij\WpAppFramework\Theme;
use Saij\WpAppFramework\Twig\TemplateRenderer;

/**
 * Child themes with code of their own boot a framework instance of their own
 * and reach their parent theme only through its public API.
 */
final class ChildThemeTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/wpaf-child-' . bin2hex(random_bytes(4));
        mkdir($this->root . '/my-child/generated', 0777, true);
        mkdir($this->root . '/my-theme', 0777, true);
        $this->activate('my-child', '2.0.0');
    }

    private function activate(string $stylesheet, string $childVersion): void
    {
        $GLOBALS['wp_app_framework_test_themes'] = [
            'template' => 'my-theme',
            'stylesheet' => $stylesheet,
            'root' => $this->root,
            'versions' => ['my-theme' => '1.0.0', 'my-child' => $childVersion],
        ];
    }

    protected function tearDown(): void
    {
        Hooks::reset();
        unset($GLOBALS['wp_app_framework_test_themes']);
        Files::remove($this->root);
    }

    /**
     * A release manifest of the child theme my-child, built for the parent theme $parent.
     *
     * @param non-empty-string $parent
     */
    private function writeReleaseManifest(string $parent): void
    {
        $result = new ManifestBuilder(
            [ManifestBuilderTest::fixture('ChildApi'), ManifestBuilder::frameworkSource()],
            [],
            [ChildTheme::class, TemplateRenderer::class, RenderContext::class],
            null,
            ProjectKind::ChildTheme,
            'my-child',
            [$parent],
            ['Twig\\'],
            $parent,
        )->build();
        file_put_contents($this->root . '/my-child/' . Instance::RELEASE_MANIFEST, new ManifestGenerator()->generate($result, 'abc123'));
    }

    public function testBootsTheChildThemeUnderItsOwnSlug(): void
    {
        $this->writeReleaseManifest('my-theme');

        ChildTheme::boot();

        self::assertSame(1, did_action('wp_app_framework/init/my-child'));
        self::assertSame(0, did_action('wp_app_framework/init/my-theme'));
    }

    public function testNeedsAnActiveChildTheme(): void
    {
        $this->activate('my-theme', '2.0.0');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('ChildTheme::boot() needs an active child theme, but the active theme "my-theme" has no parent theme; a theme boots with Theme::boot().');

        ChildTheme::boot();
    }

    public function testManifestForAnotherParentThemeIsAnError(): void
    {
        $this->writeReleaseManifest('other-theme');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(sprintf(
            'The manifest %s/my-child/generated/manifest.php was built for the child theme "my-child" of the parent theme "other-theme", but the active parent theme is "my-theme".',
            $this->root,
        ));

        ChildTheme::boot();
    }

    public function testThemeBootFromAChildThemeIsExplained(): void
    {
        do_action('wp_app_framework/init/my-theme');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The child theme my-child is active: if its functions.php calls Theme::boot(), it boots the parent theme; a child theme boots with ChildTheme::boot().');

        Theme::boot();
    }

    public function testThemeApiIsAvailableAfterTheThemeWasLoaded(): void
    {
        $api = new SectionsApi();
        add_filter('wp_app_framework/api/my-theme', static fn(mixed $value, mixed $class): mixed => $class === SectionsApi::class ? $api : $value, 10, 2);

        try {
            InstanceBridge::api(ProjectKind::Theme, 'my-theme', SectionsApi::class);
            self::fail('Expected a LogicException before after_setup_theme.');
        } catch (LogicException $exception) {
            self::assertStringContainsString('was requested before the theme was loaded (after_setup_theme)', $exception->getMessage());
        }

        do_action('after_setup_theme');

        self::assertSame($api, InstanceBridge::api(ProjectKind::Theme, 'my-theme', SectionsApi::class));
        self::assertNull(InstanceBridge::api(ProjectKind::Theme, 'other-theme', SectionsApi::class));
    }

    public function testActiveThemeWithoutTheApiIsAnError(): void
    {
        do_action('after_setup_theme');
        do_action('wp_app_framework/init/my-theme');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The theme my-theme is active but provides no public API ' . SectionsApi::class . '; is the class marked #[PublicApi] in the installed version of the theme?');

        InstanceBridge::api(ProjectKind::Theme, 'my-theme', SectionsApi::class);
    }

    public function testOverrideCacheKeyFollowsTheThemeVersions(): void
    {
        $key = Instance::themeVersionKey();
        self::assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $key);

        $this->activate('my-child', '2.0.1');
        self::assertNotSame($key, Instance::themeVersionKey());
    }
}
