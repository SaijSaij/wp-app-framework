<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Unit\Api;

use LogicException;
use PHPUnit\Framework\TestCase;
use stdClass;
use WebCodingCat\WpAppFramework\Api\InstanceBridge;
use WebCodingCat\WpAppFramework\DependencyInjection\Container;
use WebCodingCat\WpAppFramework\Manifest\Manifest;
use WebCodingCat\WpAppFramework\Manifest\ManifestGenerator;
use WebCodingCat\WpAppFramework\Project\ProjectKind;
use WebCodingCat\WpAppFramework\Tests\Fixtures\ApiValid\Note;
use WebCodingCat\WpAppFramework\Tests\Fixtures\ApiValid\NoteApi;
use WebCodingCat\WpAppFramework\Tests\Support\Hooks;
use WebCodingCat\WpAppFramework\Twig\TemplateCallableKind;
use WebCodingCat\WpAppFramework\Twig\TemplateRenderer;

/**
 * The hooks between framework instances: public APIs and public template callables of a plugin.
 *
 * @phpstan-import-type ManifestData from Manifest
 */
final class InstanceBridgeTest extends TestCase
{
    protected function tearDown(): void
    {
        Hooks::reset();
    }

    /**
     * The container of the plugin fixture (ApiValid) and its manifest.
     *
     * @return array{Container, ManifestData}
     */
    public static function plugin(): array
    {
        $file = (string) tempnam(sys_get_temp_dir(), 'manifest');
        file_put_contents($file, new ManifestGenerator()->generate(PublicApiBuildTest::buildValid()));
        $manifest = Manifest::load($file);
        unlink($file);

        $container = new Container($manifest['services'], [], [
            // NoteStore injects the renderer but never renders.
            TemplateRenderer::class => static fn(Container $container): TemplateRenderer => new TemplateRenderer(
                'my-plugin',
                sys_get_temp_dir(),
                [],
                sys_get_temp_dir(),
                'plugin',
                sys_get_temp_dir(),
                sys_get_temp_dir(),
                false,
                [],
                [],
                $container,
            ),
        ]);

        return [$container, $manifest];
    }

    /**
     * Boots the plugin fixture as far as the bridge is concerned.
     */
    public static function bootPlugin(): void
    {
        [$container, $manifest] = self::plugin();
        InstanceBridge::provideApis('my-plugin', $manifest['publicApis'], $container);
        InstanceBridge::provideTemplateCallables(
            'my-plugin',
            array_values(array_filter($manifest['templateCallables'], static fn(array $callable): bool => $callable['public'])),
            $container,
        );
        do_action(InstanceBridge::initAction('my-plugin'));
    }

    public function testApiIsAnErrorBeforeAllPluginsAreLoaded(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The public API ' . NoteApi::class . ' of plugin my-plugin was requested before all plugins were loaded (plugins_loaded)');

        InstanceBridge::api(ProjectKind::Plugin, 'my-plugin', NoteApi::class);
    }

    public function testApiIsNullWithoutThePlugin(): void
    {
        do_action('plugins_loaded');

        self::assertFalse(InstanceBridge::isBooted('my-plugin'));
        self::assertNull(InstanceBridge::api(ProjectKind::Plugin, 'my-plugin', NoteApi::class));
    }

    public function testApiOfABootedPluginIsTheSharedService(): void
    {
        self::bootPlugin();
        do_action('plugins_loaded');

        $api = InstanceBridge::api(ProjectKind::Plugin, 'my-plugin', NoteApi::class);

        self::assertTrue(InstanceBridge::isBooted('my-plugin'));
        self::assertInstanceOf(NoteApi::class, $api);
        self::assertSame($api, InstanceBridge::api(ProjectKind::Plugin, 'my-plugin', NoteApi::class));
        self::assertEquals([new Note('first'), new Note('<second>')], $api->getNotes());
    }

    public function testActivePluginWithoutTheApiIsAnError(): void
    {
        do_action(InstanceBridge::initAction('my-plugin'));
        do_action('plugins_loaded');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The plugin my-plugin is active but provides no public API ' . NoteApi::class . '; is the class marked #[PublicApi] in the installed version of the plugin?');

        InstanceBridge::api(ProjectKind::Plugin, 'my-plugin', NoteApi::class);
    }

    public function testApiOfTheWrongClassIsAnError(): void
    {
        add_filter('wp_app_framework/api/my-plugin', static fn(mixed $api, mixed $class): object => new stdClass(), 10, 2);
        do_action('plugins_loaded');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The plugin my-plugin returned stdClass for its public API ' . NoteApi::class . '.');

        InstanceBridge::api(ProjectKind::Plugin, 'my-plugin', NoteApi::class);
    }

    public function testTemplateCallablesOfABootedPlugin(): void
    {
        self::bootPlugin();

        $count = InstanceBridge::templateCallable('my-plugin', TemplateCallableKind::Function, 'my_plugin_note_count');
        $highlight = InstanceBridge::templateCallable('my-plugin', TemplateCallableKind::Filter, 'my_plugin_highlight');

        self::assertFalse($count['html']);
        self::assertSame('2!', ($count['callable'])('!'));
        self::assertTrue($highlight['html']);
        self::assertSame('<em>x</em>', ($highlight['callable'])('x', 'em'));
    }

    public function testOnlyPublicTemplateCallablesAreProvided(): void
    {
        self::bootPlugin();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('my-plugin provides no public template function "internal_only".');

        InstanceBridge::templateCallable('my-plugin', TemplateCallableKind::Function, 'internal_only');
    }

    public function testTemplateCallableOfAMissingPluginIsAnError(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Template function "my_plugin_note_count" belongs to my-plugin, which is not active; for a plugin, check with has_plugin(\'my-plugin\') before using it.');

        InstanceBridge::templateCallable('my-plugin', TemplateCallableKind::Function, 'my_plugin_note_count');
    }
}
