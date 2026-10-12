<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Block\Markup;

use PHPUnit\Framework\TestCase;
use Saij\WpAppFramework\Block\Markup\MarkupKind;
use Saij\WpAppFramework\Manifest\BuildException;
use Saij\WpAppFramework\Manifest\ManifestBuilder;
use Saij\WpAppFramework\Project\ProjectKind;
use Saij\WpAppFramework\Tests\Support\Files;
use Saij\WpAppFramework\Tests\Unit\Manifest\ManifestBuilderTest;

final class BlockMarkupValidatorTest extends TestCase
{
    private static function builder(string $project, ?string $markupDirectory = null): ManifestBuilder
    {
        return new ManifestBuilder(
            [ManifestBuilderTest::fixture('Markup')],
            markupDirectory: $markupDirectory ?? self::project($project),
        );
    }

    private static function project(string $name): string
    {
        return (string) realpath(__DIR__ . '/../../../Fixtures/MarkupProjects/' . $name);
    }

    public function testValidMarkupPasses(): void
    {
        $markup = self::builder('valid')->build()->getMarkup();

        self::assertNotNull($markup);
        // Templates in any case and below subdirectories; other files are ignored.
        self::assertSame(
            [
                'parts/header.html' => MarkupKind::Part,
                'patterns/hero.php' => MarkupKind::Pattern,
                'patterns/sub/freeform.php' => MarkupKind::Pattern,
                'patterns/sub/teaser.php' => MarkupKind::Pattern,
                'templates/index.html' => MarkupKind::Template,
                'templates/nested/Archive.HTML' => MarkupKind::Template,
            ],
            $markup->getFiles(),
        );
    }

    public function testBuildWithoutMarkupDirectoryChecksNothing(): void
    {
        self::assertNull(new ManifestBuilder([ManifestBuilderTest::fixture('Markup')])->build()->getMarkup());
    }

    public function testThemesAreCheckedButPluginsAreNot(): void
    {
        $project = sys_get_temp_dir() . '/wpaf-markup-' . bin2hex(random_bytes(4));
        mkdir($project . '/src', 0777, true);
        mkdir($project . '/patterns');
        file_put_contents($project . '/style.css', "/*\nTheme Name: Probe\n*/\n");
        file_put_contents($project . '/' . basename($project) . '.php', "<?php\n/**\n * Plugin Name: Probe\n */\n");
        // WordPress registers patterns of themes only.
        file_put_contents($project . '/patterns/broken.php', '<!-- wp:fixture/card {"title":} /-->');

        try {
            foreach (['wordpress-theme' => true, 'wordpress-plugin' => false] as $type => $checked) {
                file_put_contents($project . '/composer.json', json_encode(
                    ['type' => $type, 'autoload' => ['psr-4' => ['MarkupProbe\\' => 'src/']]],
                    JSON_THROW_ON_ERROR,
                ));
                try {
                    $result = ManifestBuilder::forProject($project)->build();
                    self::assertFalse($checked, $type . ' must be checked.');
                    self::assertSame(ProjectKind::Plugin, $result->getKind());
                    self::assertNull($result->getMarkup());
                } catch (BuildException $exception) {
                    self::assertTrue($checked, $type . ' must not be checked.');
                    // Without blocks of its own, fixture/card is a foreign block: only its JSON is checked.
                    self::assertSame(
                        ['patterns/broken.php:1: block fixture/card: invalid JSON in the block comment; WordPress ignores all attributes.'],
                        $exception->getErrors(),
                    );
                }
            }
        } finally {
            Files::remove($project);
        }
    }

    public function testErrorsNameFileLineBlockAndAttribute(): void
    {
        $expected = [
            'block-template-parts/: WordPress reads block templates and template parts from the deprecated directories block-templates/ and block-template-parts/ instead of templates/ and parts/; move the files to templates/ and parts/.',
            'parts/header.html:5: closing comment of block fixture/section, but block fixture/group (line 4) is open.',
            'parts/header.html:6: closing comment of block fixture/group without an opening comment.',
            'parts/header.html:7: block fixture/group is not closed.',
            'parts/header.html:1: block fixture/section: in a template part it is edited in contentOnly mode, where blocks can only be inserted into containers that are content blocks; set #[Supports(contentRole: true)] or give it a content attribute.',
            'patterns/hero.php:9: block fixture/card: PHP code in the block comment; the build cannot check attributes computed at run time. Write the attributes as JSON.',
            'patterns/hero.php:10: block fixture/divider: HTML between the block comments; only a block with a #[RichText(postContent: true)] field stores HTML in the post content.',
            'patterns/single.php:9: block fixture/section: in a pattern it is edited in contentOnly mode, where blocks can only be inserted into containers that are content blocks; set #[Supports(contentRole: true)] or give it a content attribute.',
            'templates/index.html:21: malformed block comment; WordPress treats it as HTML.',
            'templates/index.html:1: block fixture/crad: unknown block; the namespace is used by the blocks of this theme.',
            'templates/index.html:2: block fixture/card: unknown attribute "titel".',
            'templates/index.html:2: block fixture/card: attribute "tone": "pink" is not one of "light", "dark".',
            'templates/index.html:2: block fixture/card: attribute "count": expected integer, got string.',
            'templates/index.html:2: block fixture/card: attribute "link.external": expected boolean, got string.',
            'templates/index.html:2: block fixture/card: attribute "link": unknown property "target".',
            'templates/index.html:2: block fixture/card: attribute "link": missing required property "href".',
            'templates/index.html:2: block fixture/card: attribute "items.0.label": expected string, got integer.',
            'templates/index.html:2: block fixture/card: attribute "align": "left" is not one of "wide", "full", "".',
            'templates/index.html:3: block fixture/card: invalid JSON in the block comment; WordPress ignores all attributes.',
            'templates/index.html:4: block core/paragraph: invalid JSON in the block comment; WordPress ignores all attributes.',
            'templates/index.html:7: block fixture/card: HTML between the block comments; only a block with a #[RichText(postContent: true)] field stores HTML in the post content.',
            'templates/index.html:7: block fixture/card: inner blocks, but the block has none (its render() takes no $content).',
            'templates/index.html:9: block fixture/divider: unknown attribute "className".',
            'templates/index.html:9: block fixture/divider: unknown attribute "style".',
            'templates/index.html:11: block fixture/paragraph: attribute "text" is stored as HTML between the block comments, not in the JSON.',
            'templates/index.html:15: block fixture/group: not in allowedBlocks of fixture/section (fixture/card, core/paragraph).',
            'templates/index.html:17: block core/heading: not in allowedBlocks of fixture/section (fixture/card, core/paragraph).',
        ];

        try {
            self::builder('broken')->build();
            self::fail('BuildException expected.');
        } catch (BuildException $exception) {
            self::assertSame($expected, $exception->getErrors());
        }
    }

    public function testMarkupIsNotCheckedAgainstBrokenBlocks(): void
    {
        // Errors of the block classes come alone; checking markup against incomplete definitions adds only noise.
        try {
            new ManifestBuilder([ManifestBuilderTest::fixture('BlockBroken')], markupDirectory: self::project('broken'))->build();
            self::fail('BuildException expected.');
        } catch (BuildException $exception) {
            foreach ($exception->getErrors() as $error) {
                self::assertStringNotContainsString('templates/', $error);
            }
        }
    }
}
