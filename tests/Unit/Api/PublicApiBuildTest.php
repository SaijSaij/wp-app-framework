<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Api;

use PHPUnit\Framework\TestCase;
use Saij\WpAppFramework\Editor\RenderContext;
use Saij\WpAppFramework\Manifest\BuildException;
use Saij\WpAppFramework\Manifest\BuildResult;
use Saij\WpAppFramework\Manifest\ManifestBuilder;
use Saij\WpAppFramework\Plugin;
use Saij\WpAppFramework\Project\ProjectKind;
use Saij\WpAppFramework\Tests\Fixtures\ApiBroken;
use Saij\WpAppFramework\Tests\Fixtures\ApiValid;
use Saij\WpAppFramework\Tests\Unit\Manifest\ManifestBuilderTest;
use Saij\WpAppFramework\Theme;
use Saij\WpAppFramework\Twig\TemplateRenderer;

/**
 * Public APIs (`#[PublicApi]`, `#[PluginApi]`) and public template callables in the build.
 */
final class PublicApiBuildTest extends TestCase
{
    /**
     * @param list<non-empty-string> $templatePlugins
     */
    private static function builder(string $fixture, ProjectKind $kind, array $templatePlugins = []): ManifestBuilder
    {
        return new ManifestBuilder(
            [ManifestBuilderTest::fixture($fixture), ManifestBuilder::frameworkSource()],
            [],
            [$kind === ProjectKind::Plugin ? Plugin::class : Theme::class, TemplateRenderer::class, RenderContext::class],
            null,
            $kind,
            'my-plugin',
            $templatePlugins,
            ['Twig\\'],
        );
    }

    public static function buildValid(): BuildResult
    {
        return self::builder('ApiValid', ProjectKind::Plugin)->build();
    }

    /**
     * @return list<string>
     */
    private static function errors(ManifestBuilder $builder): array
    {
        try {
            $builder->build();
        } catch (BuildException $exception) {
            return $exception->getErrors();
        }
        self::fail('Expected a BuildException.');
    }

    public function testCollectsPublicApisAndPluginApiParameters(): void
    {
        $result = self::buildValid();

        self::assertSame([ApiValid\NoteApi::class], $result->getPublicApis());
        self::assertSame(['store' => ApiValid\NoteStore::class], $result->getServices()[ApiValid\NoteApi::class]);
        self::assertSame(
            ['store' => ApiValid\NoteStore::class, 'notes' => ['plugin' => 'other-plugin', 'class' => 'Saij\\WpAppFramework\\Tests\\Fixtures\\OtherPlugin\\NotesApi']],
            $result->getServices()[ApiValid\Consumer::class],
        );
    }

    public function testMarksPublicTemplateCallables(): void
    {
        $public = [];
        foreach (self::buildValid()->getTemplateCallables() as $callable) {
            $public[$callable->getName()] = $callable->isPublic();
        }

        self::assertTrue($public['my_plugin_note_count']);
        self::assertTrue($public['my_plugin_note_list']);
        self::assertTrue($public['my_plugin_highlight']);
        self::assertFalse($public['internal_only']);
    }

    public function testTemplatePluginsAreMappedByPrefix(): void
    {
        $result = self::builder('ApiValid', ProjectKind::Plugin, ['other-plugin', 'third_one'])->build();

        self::assertSame(['other_plugin_' => 'other-plugin', 'third_one_' => 'third_one'], $result->getTemplatePlugins());
    }

    public function testThemesOfferNoPublicApisOrTemplateCallables(): void
    {
        $errors = self::errors(self::builder('ApiValid', ProjectKind::Theme));

        self::assertContains(ApiValid\NoteApi::class . ' has #[PublicApi], but only plugins provide public APIs; this project is a theme.', $errors);
        self::assertContains(
            '#[TemplateFunction] on ' . ApiValid\NoteTemplates::class . '::count() has public: true, but only plugins offer template callables to other consumers.',
            $errors,
        );
    }

    public function testInvalidDeclarationsAreBuildErrors(): void
    {
        $errors = self::errors(self::builder('ApiBroken', ProjectKind::Plugin, ['other-plugin']));
        $leaking = ApiBroken\LeakingApi::class;
        $consumer = ApiBroken\BrokenConsumer::class;
        $templates = ApiBroken\BrokenTemplates::class;
        $prefixed = 'a release prefixes the namespace %s, so the class has a different name in every consumer. Use PHP, WordPress or own classes of the plugin.';

        self::assertEqualsCanonicalizing(
            [
                "Parameter \$environment of {$leaking}::environment() of public API {$leaking} uses Twig\\Environment; " . sprintf($prefixed, 'Twig\\'),
                "The return type of {$leaking}::environment() of public API {$leaking} uses Twig\\Environment; " . sprintf($prefixed, 'Twig\\'),
                'Property $renderer of ' . ApiBroken\Wrapper::class . " of public API {$leaking} uses Saij\\WpAppFramework\\Twig\\TemplateRenderer; " . sprintf($prefixed, 'Saij\\WpAppFramework\\'),
                "#[TemplateFunction] on {$templates}::unprefixed() has public: true, so its name \"unprefixed\" must start with the template prefix \"my_plugin_\" of the plugin.",
                "Parameter \$markup of {$templates}::leak() of public template function \"my_plugin_leak\" uses Twig\\Markup; " . sprintf($prefixed, 'Twig\\'),
                "#[TemplateFunction] on {$templates}::reserved() uses the name \"other_plugin_name\"; names starting with \"other_plugin_\" are reserved for the public template callables of plugin other-plugin (extra.wp-app-framework.template-plugins).",
                "#[PluginApi] on parameter \$notNullable of {$consumer}::__construct() must be nullable with the default null (?Saij\\WpAppFramework\\Tests\\Fixtures\\OtherPlugin\\NotesApi \$notNullable = null): it is null if the plugin is not active.",
                "#[PluginApi] on parameter \$badSlug of {$consumer}::__construct() names \"Other Plugin\", which is not a plugin slug (lowercase letters, digits, \"-\", \"_\").",
                "#[PluginApi] on parameter \$builtin of {$consumer}::__construct() needs the class of the public API as type.",
                "#[PluginApi] on parameter \$own of {$consumer}::__construct(): {$leaking} belongs to this project or the framework, not to plugin other-plugin.",
                'parameter $notes of ' . ApiBroken\HookWithPluginApi::class . '::run(): #[PluginApi] is only supported on constructor parameters of services, not in hooks.',
            ],
            $errors,
        );
    }
}
