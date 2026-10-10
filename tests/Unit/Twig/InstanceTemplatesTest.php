<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Twig;

use PHPUnit\Framework\TestCase;
use Saij\WpAppFramework\Api\PluginTemplateFunctions;
use Saij\WpAppFramework\DependencyInjection\Container;
use Saij\WpAppFramework\Plugin;
use Saij\WpAppFramework\Tests\Support\Files;
use Saij\WpAppFramework\Tests\Support\Hooks;
use Saij\WpAppFramework\Tests\Unit\Api\InstanceBridgeTest;
use Saij\WpAppFramework\Theme;
use Saij\WpAppFramework\Twig\TemplateRenderer;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * Templates of several framework instances: separated compiled templates,
 * overrides of plugin templates in the theme and public template callables of plugins.
 */
final class InstanceTemplatesTest extends TestCase
{
    private const string TEMPLATES = __DIR__ . '/../../Fixtures/Templates';

    private string $cache;

    private string $templates;

    protected function setUp(): void
    {
        $this->cache = sys_get_temp_dir() . '/wpaf-instances-' . bin2hex(random_bytes(4));
        $this->templates = (string) realpath(self::TEMPLATES);
    }

    protected function tearDown(): void
    {
        Hooks::reset();
        if (is_dir($this->cache)) {
            Files::remove($this->cache);
        }
    }

    /**
     * @param list<string> $overrides
     */
    private function plugin(array $overrides, bool $development = false): TemplateRenderer
    {
        return new TemplateRenderer(
            'my-plugin',
            $this->templates . '/plugin',
            $overrides,
            $this->templates . '/plugin/src',
            'plugin',
            $this->templates . '/framework/src',
            $this->cache,
            $development,
            [],
            [],
            new Container([]),
        );
    }

    private function theme(): TemplateRenderer
    {
        return new TemplateRenderer(
            'my-theme',
            $this->templates . '/theme',
            [],
            $this->templates . '/theme/src',
            'theme',
            $this->templates . '/framework/src',
            $this->cache,
            false,
            [],
            [],
            new Container([]),
        );
    }

    private function consumer(): TemplateRenderer
    {
        return new TemplateRenderer(
            'my-theme',
            $this->templates . '/consumer',
            [],
            $this->templates . '/consumer/src',
            'theme',
            $this->templates . '/framework/src',
            $this->cache,
            false,
            [[
                'kind' => 'function',
                'name' => 'has_plugin',
                'class' => PluginTemplateFunctions::class,
                'method' => 'hasPlugin',
                'html' => false,
                'public' => false,
            ]],
            ['my_plugin_' => 'my-plugin'],
            new Container([PluginTemplateFunctions::class => []]),
        );
    }

    public function testTemplatesWithTheSameNameStaySeparateBetweenInstances(): void
    {
        // Same relative path, same cache directory and options: only the slug separates the compiled classes.
        self::assertSame('theme', $this->theme()->render('Shared/Level.twig'));
        self::assertSame('plugin', $this->plugin([])->render('Shared/Level.twig'));
        self::assertSame('theme', $this->theme()->render('Shared/Level.twig'));
    }

    public function testThemeOverridesPluginTemplates(): void
    {
        $overrides = Plugin::templateOverrides('my-plugin', $this->templates . '/theme', $this->templates . '/theme');
        $renderer = $this->plugin($overrides, true);

        self::assertSame([$this->templates . '/theme/overrides/my-plugin'], $overrides);
        self::assertSame('theme-override', $renderer->render('Shared/Level.twig'));
        self::assertSame('theme-override-only', $renderer->render('Only/Override.twig'));
        self::assertSame('plugin-only', $renderer->render('Shared/PluginOnly.twig'));
        self::assertSame('plugin', $renderer->render('@plugin/Shared/Level.twig'));
        self::assertSame('framework', $renderer->render('@framework/Shared/Level.twig'));
    }

    public function testChildThemeOverridesBeforeTheTheme(): void
    {
        $overrides = Plugin::templateOverrides('my-plugin', $this->templates . '/theme', $this->templates . '/child');

        self::assertSame([$this->templates . '/child/overrides/my-plugin', $this->templates . '/theme/overrides/my-plugin'], $overrides);
        self::assertSame('child-override', $this->plugin($overrides, true)->render('Shared/Level.twig'));
    }

    public function testWithoutOverridesThePluginTemplatesApply(): void
    {
        self::assertSame([], Plugin::templateOverrides('other-plugin', $this->templates . '/theme', $this->templates . '/child'));
        self::assertSame([], Theme::templateOverrides('theme', $this->templates . '/theme', $this->templates . '/theme'));
    }

    public function testCallsPublicTemplateCallablesOfAPlugin(): void
    {
        InstanceBridgeTest::bootPlugin();

        self::assertSame(
            '2 notes|<ul><li>first</li><li>&lt;second&gt;</li></ul>|<mark>&lt;b&gt;x&lt;/b&gt;</mark>|<em>&lt;b&gt;x&lt;/b&gt;</em>|<mark><ul><li>first</li><li>&lt;second&gt;</li></ul></mark>|yes',
            trim($this->consumer()->render('PluginCalls.twig')),
        );
        self::assertSame('2', $this->consumer()->render('Guarded.twig'));
    }

    public function testTemplatesCompileWithoutThePlugin(): void
    {
        $this->consumer()->compile('PluginCalls.twig');

        self::assertSame('none', $this->consumer()->render('Guarded.twig'));
    }

    public function testCallingAMissingPluginIsAnError(): void
    {
        $this->expectException(RuntimeError::class);
        $this->expectExceptionMessage('Template function "my_plugin_note_count" belongs to my-plugin, which is not active');

        $this->consumer()->render('PluginCalls.twig');
    }

    public function testOtherUnknownNamesStayCompileErrors(): void
    {
        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessage('Unknown "unknown_function" function');

        $this->consumer()->render('Unknown.twig');
    }
}
