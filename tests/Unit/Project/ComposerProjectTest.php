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
     */
    private function project(string $slug, array $composer): string
    {
        $directory = $this->root . '/' . $slug;
        mkdir($directory . '/src', 0777, true);
        file_put_contents($directory . '/composer.json', (string) json_encode($composer));

        return $directory;
    }

    /**
     * @param array<string, mixed> $composer
     * @return list<string>
     */
    private function errors(string $slug, array $composer): array
    {
        try {
            ComposerProject::load($this->project($slug, $composer));
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
        self::assertSame(['other-plugin', 'third'], $project->getTemplatePlugins());
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
                sprintf('extra.wp-app-framework.template-plugins in %s lists shop and shop-extra, whose template prefixes shop_ and shop_extra_ overlap; a template name could not be assigned to one plugin.', $file),
            ],
            $this->errors('my-theme', [
                'type' => 'wordpress-theme',
                'autoload' => ['psr-4' => ['My\\' => 'src/']],
                'extra' => ['wp-app-framework' => ['template-plugins' => ['Bad Slug', 'my-theme', 'shop', 'shop-extra']]],
            ]),
        );
    }
}
