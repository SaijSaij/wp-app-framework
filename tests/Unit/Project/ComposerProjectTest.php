<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Project;

use PHPUnit\Framework\TestCase;
use Saij\WpAppFramework\Manifest\BuildException;
use Saij\WpAppFramework\Project\ComposerProject;
use Saij\WpAppFramework\Project\ProjectKind;
use Saij\WpAppFramework\Tests\Support\Files;

final class ComposerProjectTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/wpaf-project-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->root)) {
            Files::remove($this->root);
        }
    }

    /**
     * @param array<string, mixed> $composer
     * @param string|null          $style    Contents of style.css; null for none.
     */
    private function project(string $slug, array $composer, ?string $style = "/*\nTheme Name: Test\n*/\n"): string
    {
        $directory = $this->root . '/' . $slug;
        mkdir($directory . '/src', 0777, true);
        file_put_contents($directory . '/composer.json', (string) json_encode($composer));
        if ($style !== null) {
            file_put_contents($directory . '/style.css', $style);
        }

        return $directory;
    }

    /**
     * @param array<string, mixed> $composer
     * @return list<string>
     */
    private function errors(string $slug, array $composer, ?string $style = "/*\nTheme Name: Test\n*/\n"): array
    {
        try {
            ComposerProject::load($this->project($slug, $composer, $style));
        } catch (BuildException $exception) {
            return $exception->getErrors();
        }
        self::fail('Expected a BuildException.');
    }

    public function testReadsKindSlugSourcesAndTemplatePlugins(): void
    {
        $directory = $this->project('my-plugin', [
            'type' => 'wordpress-plugin',
            'autoload' => ['psr-4' => ['My\\Plugin\\' => 'src/']],
            'extra' => [
                'wp-app-framework' => ['template-plugins' => ['other-plugin', 'third']],
                'strauss' => ['namespace_prefix' => 'My\\Plugin\\Vendor\\'],
            ],
        ]);
        mkdir($directory . '/vendor/composer', 0777, true);
        file_put_contents($directory . '/vendor/composer/installed.json', (string) json_encode([
            'packages' => [
                ['name' => 'twig/twig', 'autoload' => ['psr-4' => ['Twig\\' => 'src/']]],
                ['name' => 'phpstan/phpstan', 'autoload' => ['files' => ['bootstrap.php']]],
                ['name' => 'saij/other-plugin', 'autoload' => ['psr-4' => ['Saij\\Other\\' => 'src/']]],
            ],
            'dev-package-names' => ['phpstan/phpstan', 'saij/other-plugin'],
        ]));

        $project = ComposerProject::load($directory);

        self::assertSame('my-plugin', $project->getSlug());
        self::assertSame(ProjectKind::Plugin, $project->getKind());
        self::assertSame('My\\Plugin\\', $project->getSources()[0]->getPrefix());
        self::assertSame(realpath($directory . '/src'), $project->getSources()[0]->getDirectory());
        self::assertSame(['other-plugin', 'third'], $project->getTemplateProviders());
        // Development packages are not shipped, so their names are not prefixed.
        self::assertSame(['My\\Plugin\\Vendor\\', 'Twig\\'], $project->getForeignNamespaces());
    }

    public function testTemplatePrefixReplacesHyphens(): void
    {
        self::assertSame('plugin_dev_test_', ComposerProject::templatePrefix('plugin-dev-test'));
    }

    public function testTypeIsRequired(): void
    {
        self::assertSame(
            [sprintf('%s/my-theme/composer.json must declare "type": "wordpress-theme" or "wordpress-plugin", found "library".', $this->root)],
            $this->errors('my-theme', ['type' => 'library', 'autoload' => ['psr-4' => ['My\\' => 'src/']]]),
        );
    }

    public function testDirectoryNameMustBeASlug(): void
    {
        self::assertSame(
            [sprintf('The directory name "My Theme" of %s/My Theme is not a valid slug; WordPress names the theme or plugin after it. Use lowercase letters, digits, "-" and "_".', $this->root)],
            $this->errors('My Theme', ['type' => 'wordpress-theme']),
        );
    }

    public function testInvalidTemplatePluginsAreReported(): void
    {
        $file = $this->root . '/my-theme/composer.json';

        self::assertSame(
            [
                sprintf('extra.wp-app-framework.template-plugins in %s contains "Bad Slug", which is not a plugin slug.', $file),
                sprintf('extra.wp-app-framework.template-plugins in %s lists the project itself (my-theme).', $file),
                sprintf('extra.wp-app-framework.template-plugins in %s: the template prefixes shop_extra_ of shop-extra and shop_ of shop overlap; a template name could not be assigned to one plugin or theme.', $file),
            ],
            $this->errors('my-theme', [
                'type' => 'wordpress-theme',
                'autoload' => ['psr-4' => ['My\\' => 'src/']],
                'extra' => ['wp-app-framework' => ['template-plugins' => ['Bad Slug', 'my-theme', 'shop', 'shop-extra']]],
            ]),
        );
    }

    public function testThemeWithoutTemplateHeaderIsATheme(): void
    {
        $project = ComposerProject::load($this->project('my-theme', ['type' => 'wordpress-theme', 'autoload' => ['psr-4' => ['My\\' => 'src/']]]));

        self::assertSame(ProjectKind::Theme, $project->getKind());
        self::assertNull($project->getParent());
    }

    public function testTemplateHeaderMakesAChildThemeThatUsesTheCallablesOfItsParent(): void
    {
        $project = ComposerProject::load($this->project(
            'my-child',
            [
                'type' => 'wordpress-theme',
                'autoload' => ['psr-4' => ['My\\Child\\' => 'src/']],
                'extra' => ['wp-app-framework' => ['template-plugins' => ['shop']]],
            ],
            "/*\nTheme Name: Child\n * Template: my-theme */\n",
        ));

        self::assertSame(ProjectKind::ChildTheme, $project->getKind());
        self::assertSame('my-theme', $project->getParent());
        self::assertSame(['my-theme', 'shop'], $project->getTemplateProviders());
    }

    public function testThemeNeedsAStyleSheet(): void
    {
        self::assertSame(
            [sprintf('%1$s/my-theme/style.css not found; %1$s/my-theme/composer.json declares "type": "wordpress-theme", and a theme needs a style.css.', $this->root)],
            $this->errors('my-theme', ['type' => 'wordpress-theme', 'autoload' => ['psr-4' => ['My\\' => 'src/']]], null),
        );
    }

    public function testTemplateHeaderMustNameAnotherTheme(): void
    {
        self::assertSame(
            [sprintf('The "Template:" header of %s/my-child/style.css names "my-child", which is not the directory name of another theme; a child theme names its parent theme there.', $this->root)],
            $this->errors('my-child', ['type' => 'wordpress-theme', 'autoload' => ['psr-4' => ['My\\' => 'src/']]], "/*\nTemplate: my-child\n*/"),
        );
    }

    public function testChildThemeDoesNotDeclareItsParentAndPrefixesMustNotOverlapWithIt(): void
    {
        $file = $this->root . '/shop-child/composer.json';

        self::assertSame(
            [
                sprintf('extra.wp-app-framework.template-plugins in %s lists the parent theme shop; a child theme uses its public template callables without declaring them.', $file),
                sprintf('extra.wp-app-framework.template-plugins in %s: the template prefixes shop_extra_ of shop-extra and shop_ of the parent theme shop overlap; a template name could not be assigned to one plugin or theme.', $file),
            ],
            $this->errors('shop-child', [
                'type' => 'wordpress-theme',
                'autoload' => ['psr-4' => ['My\\' => 'src/']],
                'extra' => ['wp-app-framework' => ['template-plugins' => ['shop', 'shop-extra']]],
            ], "/*\nTemplate: shop\n*/"),
        );
    }
}
