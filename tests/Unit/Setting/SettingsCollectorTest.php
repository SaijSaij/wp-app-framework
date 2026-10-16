<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Setting;

use PHPUnit\Framework\TestCase;
use Saij\WpAppFramework\Manifest\BuildException;
use Saij\WpAppFramework\Manifest\BuildResult;
use Saij\WpAppFramework\Manifest\ManifestBuilder;
use Saij\WpAppFramework\Tests\Fixtures\SettingsValid;
use Saij\WpAppFramework\Tests\Unit\Manifest\ManifestBuilderTest;

/**
 * Build of settings groups (`#[SettingsGroup]`).
 */
final class SettingsCollectorTest extends TestCase
{
    /**
     * @param non-empty-string $namespace
     */
    public static function buildValid(?string $name = 'Fixture Theme', string $namespace = 'my-theme'): BuildResult
    {
        return new ManifestBuilder(
            [ManifestBuilderTest::fixture('SettingsValid'), ManifestBuilder::frameworkSource()],
            slug: 'my-theme',
            name: $name,
            namespace: $namespace,
        )->build();
    }

    public function testGroupsAreOrderedByPositionThenName(): void
    {
        $result = self::buildValid();

        self::assertSame(['layout', 'general'], array_column($result->getSettings(), 'name'));
        // The option belongs to the namespace, not to the slug.
        self::assertSame(['my_theme_layout', 'my_theme_general'], array_column($result->getSettings(), 'option'));
        self::assertSame(['dev_test_layout', 'dev_test_general'], array_column(self::buildValid(namespace: 'dev-test')->getSettings(), 'option'));
        self::assertSame('Fixture Theme', $result->getSettingsTitle());
    }

    public function testSchemaDefaultsAndControlsComeFromTheConstructor(): void
    {
        $general = self::buildValid()->getSettings()[1];

        self::assertSame(SettingsValid\General::class, $general['class']);
        self::assertSame(['General', 'Main settings.', 20], [$general['title'], $general['description'], $general['position']]);
        self::assertSame('object', $general['schema']['type']);
        $properties = $general['schema']['properties'];
        self::assertSame(['type' => 'string', 'enum' => ['compact', 'normal'], 'default' => 'normal'], $properties['density']);
        self::assertSame(['type' => 'string', 'format' => 'uri', 'default' => ''], $properties['contactUrl']);
        // No default in the schema for null; the default object holds it.
        self::assertSame(['type' => ['number', 'null']], $properties['ratio']);
        /** @var array{items: array{properties: array<string, mixed>}} $links */
        $links = $properties['links'];
        self::assertSame(['type' => 'string', 'required' => true], $links['items']['properties']['label']);
        self::assertSame(
            ['tagline' => '', 'density' => 'normal', 'contactUrl' => '', 'count' => 3, 'ratio' => null, 'links' => [], 'secret' => 'x', 'logo' => null, 'footer' => '', 'notice' => '', 'privacyPage' => null, 'featured' => [], 'imprint' => ['url' => '', 'opensInNewTab' => false, 'kind' => 'url', 'id' => 0], 'address' => '', 'brand' => '#1e4d8c'],
            $general['default'],
        );
        // Hidden settings have no control.
        self::assertSame(['tagline', 'density', 'contactUrl', 'count', 'ratio', 'links', 'logo', 'footer', 'notice', 'privacyPage', 'featured', 'imprint', 'address', 'brand'], array_keys($general['controls']));
        self::assertSame(['type' => 'string', 'label' => 'Tagline', 'nullable' => false], $general['controls']['tagline']);
        self::assertSame('list', $general['controls']['links']['type']);
    }

    public function testTextFieldsAndReferencesAreEditedOnTheSettingsPage(): void
    {
        $general = self::buildValid()->getSettings()[1];

        self::assertSame(['type' => 'plain', 'label' => 'Footer', 'nullable' => false], $general['controls']['footer']);
        self::assertSame(['type' => 'rich', 'label' => 'Notice', 'nullable' => false, 'allowedFormats' => ['core/bold', 'core/link']], $general['controls']['notice']);
        self::assertSame(['notice'], $general['richText']);
        self::assertSame(['type' => 'reference', 'label' => 'Privacy page', 'nullable' => true, 'target' => 'post', 'subtypes' => ['page']], $general['controls']['privacyPage']);
        self::assertSame('references', $general['controls']['featured']['type']);
        self::assertSame(['type' => 'plain', 'label' => 'Address', 'nullable' => false, 'multiline' => true], $general['controls']['address']);
        self::assertSame(['type' => 'color', 'label' => 'Brand', 'nullable' => false], $general['controls']['brand']);
    }

    public function testLinksHaveTheLinkControl(): void
    {
        $general = self::buildValid()->getSettings()[1];

        self::assertSame(['type' => 'link', 'label' => 'Imprint', 'nullable' => false], $general['controls']['imprint']);
        /** @var array{properties: array<string, mixed>, default: mixed} $imprint */
        $imprint = $general['schema']['properties']['imprint'];
        self::assertSame(['type' => 'string', 'format' => 'uri', 'default' => ''], $imprint['properties']['url']);
        self::assertSame(['url' => '', 'opensInNewTab' => false, 'kind' => 'url', 'id' => 0], $imprint['default']);
    }

    public function testImagesAreEditedOnTheSettingsPage(): void
    {
        $result = self::buildValid();
        $general = $result->getSettings()[1];

        self::assertSame(['type' => ['integer', 'null']], $general['schema']['properties']['logo']);
        self::assertSame(['type' => 'image', 'label' => 'Logo', 'nullable' => true], $general['controls']['logo']);
        /** @var array{item: array{fields: array<string, mixed>}} $links */
        $links = $general['controls']['links'];
        self::assertSame(['type' => 'image', 'label' => 'Icon', 'nullable' => true], $links['item']['fields']['icon']);

        // A block using the same value class edits the image in the canvas: no inspector control.
        $block = $result->getBlocks()[0];
        self::assertSame('my-theme/links', $block->getName());
        self::assertSame(['links.*.icon'], array_keys($block->getEditor()['fields']));
        /** @var array{item: array{fields: array<string, mixed>}} $blockLinks */
        $blockLinks = $block->getEditor()['controls']['links'];
        self::assertSame(['label', 'url'], array_keys($blockLinks['item']['fields']));
    }

    public function testGroupsAreInjectedFromTheirOptionAndAreNoServices(): void
    {
        $result = self::buildValid();

        self::assertSame(
            ['general' => ['setting' => SettingsValid\General::class], 'layout' => ['setting' => SettingsValid\Layout::class]],
            $result->getServices()[SettingsValid\Banner::class],
        );
        self::assertArrayNotHasKey(SettingsValid\General::class, $result->getServices());
        self::assertNotContains(SettingsValid\General::class, $result->getCandidates());
        // The value factory creates the group objects.
        self::assertArrayHasKey(SettingsValid\General::class, $result->getValues());
        self::assertArrayHasKey(SettingsValid\Link::class, $result->getValues());
    }

    public function testSettingsNeedTheNameOfTheThemeOrPlugin(): void
    {
        try {
            self::buildValid(null);
            self::fail('BuildException expected.');
        } catch (BuildException $exception) {
            self::assertSame([
                'The project declares settings groups, but has no name for its settings page: add a "Theme Name:" header to style.css (themes and child themes) or a "Plugin Name:" header to the main file {slug}.php (plugins).',
            ], $exception->getErrors());
        }
    }

    public function testOptionNamesFitTheOptionsTable(): void
    {
        $namespace = str_repeat('a', 184);
        try {
            self::buildValid('Fixture Theme', $namespace);
            self::fail('BuildException expected.');
        } catch (BuildException $exception) {
            self::assertSame([sprintf(
                '#[SettingsGroup] on %s results in the option name "%s_general", which is longer than 191 characters (the limit of WordPress\' options table); use a shorter name.',
                SettingsValid\General::class,
                $namespace,
            )], $exception->getErrors());
        }
    }

    public function testReportsAllErrors(): void
    {
        $ns = 'Saij\\WpAppFramework\\Tests\\Fixtures\\SettingsBroken\\';
        $expected = [
            "{$ns}HookOnGroup::run() has #[Hook], but {$ns}HookOnGroup is a settings group and therefore not a service.",
            "parameter \$settings of {$ns}UsedInHook::run(): settings group {$ns}Valid is injected into constructors of services only, not in hooks.",
            "#[SettingsGroup] on {$ns}BadName has the invalid name \"Bad-Name\": use lowercase letters, digits and \"_\", starting with a letter, e.g. \"general\".",
            "parameter \$text of {$ns}BlockMarker::__construct() has #[RichText(postContent: true)], which stores the value in the post content of a block; settings groups and post meta have no post content.",
            "parameter \$url of {$ns}ContentMarker::__construct() has #[Content], which marks the content of blocks; settings groups and post meta have no block content.",
            "#[SettingsGroup] on {$ns}DuplicateB uses the name \"duplicate\", which {$ns}DuplicateA already uses.",
            "#[SettingsGroup] on {$ns}EmptyTitle has an empty title.",
            "parameter \$text of {$ns}RichItem::__construct(): allowedFormats of #[RichText] in a settings group or post meta may only contain the formats of the classic editor (core/bold, core/italic, core/link, core/strikethrough, core/subscript, core/superscript), not core/code.",
            "parameter \$value of settings group class {$ns}NoDefault::__construct() needs a default value: a setting is missing until it is saved.",
            "#[SettingsGroup] on {$ns}NotReadonly: value class {$ns}NotReadonly must be declared readonly.",
        ];

        try {
            new ManifestBuilder([ManifestBuilderTest::fixture('SettingsBroken')], name: 'Broken')->build();
            self::fail('BuildException expected.');
        } catch (BuildException $exception) {
            $errors = $exception->getErrors();
            foreach ($expected as $message) {
                self::assertContains($message, $errors, implode("\n", $errors));
            }
            self::assertCount(count($expected), $errors, implode("\n", $errors));
        }
    }
}
