<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Unit\I18n;

use PHPUnit\Framework\TestCase;
use WebCodingCat\WpAppFramework\Filesystem\FileTree;
use WebCodingCat\WpAppFramework\Manifest\BuildException;
use WebCodingCat\WpAppFramework\Manifest\ManifestBuilder;
use WebCodingCat\WpAppFramework\Manifest\ManifestGenerator;
use WebCodingCat\WpAppFramework\Tests\Support\ProjectLoader;

/**
 * The text domain and the translations of a project in the build: the Text Domain header is required, the PO files
 * in languages/ give the locales of the manifest.
 */
final class LanguagesTest extends TestCase
{
    private string $project;

    protected function setUp(): void
    {
        $this->project = sys_get_temp_dir() . '/wpaf-languages-' . bin2hex(random_bytes(4));
        mkdir($this->project . '/src', 0777, true);
        mkdir($this->project . '/languages');
        file_put_contents($this->project . '/composer.json', json_encode(
            ['type' => 'wordpress-theme', 'autoload' => ['psr-4' => ['LanguagesProbe\\' => 'src/']], 'extra' => ['wp-app-framework' => ['namespace' => 'probe']]],
            JSON_THROW_ON_ERROR,
        ));
    }

    protected function tearDown(): void
    {
        FileTree::remove($this->project);
    }

    /**
     * @return list<string>
     */
    private function errors(): array
    {
        $loader = ProjectLoader::register($this->project);
        try {
            ManifestBuilder::forProject($this->project)->build();

            return [];
        } catch (BuildException $exception) {
            return $exception->getErrors();
        } finally {
            $loader->unregister();
        }
    }

    public function testListsTheLocalesOfThePoFilesOfTheTextDomain(): void
    {
        file_put_contents($this->project . '/style.css', "/*\nTheme Name: Probe\nText Domain: probe\n*/\n");
        file_put_contents($this->project . '/languages/probe-de_DE.po', '');
        file_put_contents($this->project . '/languages/probe-de_DE_formal.po', '');
        file_put_contents($this->project . '/languages/probe-pt_BR.po', '');
        file_put_contents($this->project . '/languages/README.md', '');

        $loader = ProjectLoader::register($this->project);
        try {
            $result = ManifestBuilder::forProject($this->project)->build();
        } finally {
            $loader->unregister();
        }

        self::assertSame('probe', $result->getTextDomain());
        self::assertSame(['de_DE', 'de_DE_formal', 'pt_BR'], $result->getLocales());
        $manifest = eval('?>' . new ManifestGenerator()->generate($result));
        self::assertIsArray($manifest);
        self::assertSame(['probe', ['de_DE', 'de_DE_formal', 'pt_BR']], [$manifest['textDomain'], $manifest['translations']]);
    }

    public function testRequiresTheTextDomainHeaderAndPoFilesOfIt(): void
    {
        file_put_contents($this->project . '/style.css', "/*\nTheme Name: Probe\n*/\n");
        $slug = basename($this->project);
        self::assertSame(
            [sprintf('The theme has no Text Domain header (style.css); its texts are translated with it, usually its slug: Text Domain: %s', $slug)],
            $this->errors(),
        );

        file_put_contents($this->project . '/style.css', "/*\nTheme Name: Probe\nText Domain: probe\n*/\n");
        file_put_contents($this->project . '/languages/other-de_DE.po', '');
        file_put_contents($this->project . '/languages/de_DE.po', '');
        self::assertSame([
            $this->project . '/languages/de_DE.po: a translation is named {text domain}-{locale}.po, here probe-de_DE.po for German (the text domain is the Text Domain header).',
            $this->project . '/languages/other-de_DE.po: a translation is named {text domain}-{locale}.po, here probe-de_DE.po for German (the text domain is the Text Domain header).',
        ], $this->errors());
    }
}
