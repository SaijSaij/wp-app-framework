<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Value;

use Closure;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Saij\WpAppFramework\Manifest\BuildException;
use Saij\WpAppFramework\Manifest\BuildResult;
use Saij\WpAppFramework\Manifest\Manifest;
use Saij\WpAppFramework\Manifest\ManifestBuilder;
use Saij\WpAppFramework\Manifest\ManifestGenerator;
use Saij\WpAppFramework\Setting\SettingsStore;
use Saij\WpAppFramework\Tests\Fixtures\IdentifierBroken;
use Saij\WpAppFramework\Tests\Fixtures\IdentifierValid;
use Saij\WpAppFramework\Tests\Support\Hooks;
use Saij\WpAppFramework\Tests\Support\Recorded;
use Saij\WpAppFramework\Tests\Unit\Manifest\ManifestBuilderTest;
use Saij\WpAppFramework\Value\Identifier;
use Saij\WpAppFramework\Value\Identifiers;
use Saij\WpAppFramework\Value\ValueFactory;

/**
 * Identifiers of list items (`#[Identifier]`): schema with pattern, no control, the identifier in the list's value
 * description and control, the check of the value factory, filling them in when settings are saved, build errors.
 */
final class IdentifierTest extends TestCase
{
    protected function tearDown(): void
    {
        Hooks::reset();
    }

    private static function build(): BuildResult
    {
        return new ManifestBuilder(
            [ManifestBuilderTest::fixture('IdentifierValid'), ManifestBuilder::frameworkSource()],
            slug: 'my-theme',
            name: 'Fixture Theme',
            namespace: 'my-theme',
        )->build();
    }

    public function testIdentifiersHaveAPatternAndNoControl(): void
    {
        $result = self::build();
        $block = $result->getBlocks()[0];
        /** @var array{items: array{properties: array{id: mixed, panes: array{items: array{properties: array{key: mixed}}}}}} $tabs */
        $tabs = $block->getArgs()['attributes']['tabs'];

        self::assertSame(['type' => 'string', 'pattern' => Identifier::PATTERN, 'default' => ''], $tabs['items']['properties']['id']);
        self::assertSame(['type' => 'string', 'pattern' => Identifier::PATTERN, 'default' => ''], $tabs['items']['properties']['panes']['items']['properties']['key']);

        /** @var array{identifier: string, item: array{fields: array<string, array<string, mixed>>}} $control */
        $control = $block->getEditor()['controls']['tabs'];
        self::assertSame('id', $control['identifier']);
        self::assertArrayNotHasKey('id', $control['item']['fields']);
        /** @var array{identifier: string, item: array{fields: array<string, mixed>}} $panes */
        $panes = $control['item']['fields']['panes'];
        self::assertSame('key', $panes['identifier']);
        self::assertSame(['note'], array_keys($panes['item']['fields']));

        $values = $result->getValues();
        self::assertSame(['type' => 'string', 'nullable' => false, 'identifier' => true], $values[IdentifierValid\Tab::class]['id']['value']);
        self::assertSame('id', $values[IdentifierValid\TabsAttributes::class]['tabs']['value']['identifier'] ?? null);
    }

    public function testThePatternAcceptsEightLowercaseHexDigitsAndTheEmptyString(): void
    {
        // WordPress matches patterns like this in rest_validate_json_schema_pattern().
        $wordpress = '#' . str_replace('#', '\\#', Identifier::PATTERN) . '#u';
        foreach (['', '0a1b2c3d', 'ffffffff'] as $identifier) {
            self::assertSame(1, preg_match($wordpress, $identifier), $identifier);
            self::assertTrue(Identifier::isIdentifier($identifier), $identifier);
        }
        foreach (['0A1B2C3D', '0a1b2c3', '0a1b2c3d4', 'scheme-1', ' 0a1b2c3d'] as $identifier) {
            self::assertSame(0, preg_match($wordpress, $identifier), $identifier);
            self::assertFalse(Identifier::isIdentifier($identifier), $identifier);
        }
        // Without the modifier D, $ accepts a newline at the end; the value factory does not.
        self::assertSame(1, preg_match($wordpress, "0a1b2c3d\n"));
        self::assertFalse(Identifier::isIdentifier("0a1b2c3d\n"));
    }

    public function testGeneratedIdentifiersAreNewAndValid(): void
    {
        $identifier = Identifier::generate([]);
        self::assertTrue(Identifier::isIdentifier($identifier));
        self::assertNotSame('', $identifier);
        self::assertNotSame($identifier, Identifier::generate([$identifier]));
    }

    public function testTheValueFactoryChecksIdentifiersAgain(): void
    {
        $result = self::build();
        $factory = new ValueFactory($result->getValues());
        $schemas = $result->getBlocks()[0]->getArgs()['attributes'];

        $attrs = $factory->createAttributes(IdentifierValid\TabsAttributes::class, ['tabs' => [['id' => '0a1b2c3d', 'title' => 'A'], ['title' => 'B']]], $schemas);
        self::assertSame(['0a1b2c3d', ''], [$attrs->tabs[0]->id, $attrs->tabs[1]->id]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Value "tabs[0].id" must be an identifier of eight lowercase hex digits or an empty string, \'Tab 1\' given.');
        $factory->createAttributes(IdentifierValid\TabsAttributes::class, ['tabs' => [['id' => 'Tab 1']]], $schemas);
    }

    public function testSettingsFillInMissingAndDuplicateIdentifiersWhenSaved(): void
    {
        $file = (string) tempnam(sys_get_temp_dir(), 'manifest');
        file_put_contents($file, new ManifestGenerator()->generate(self::build()));
        $manifest = Manifest::load($file);
        unlink($file);

        $group = $manifest['settings']['groups'][0];
        self::assertSame(['schemes.*.id'], $group['identifiers']);
        // The default keeps the identifiers it has; the others are generated when the group is saved.
        self::assertSame([['id' => '0a1b2c3d', 'name' => 'Page'], ['id' => '', 'name' => 'Dark']], $group['default']['schemes']);

        new SettingsStore('my-theme', $manifest['settings']['groups'], new ValueFactory($manifest['values']))->register();
        /** @var Closure(mixed): array{schemes: list<array{id: string, name: string}>} $sanitize */
        $sanitize = Recorded::settings()['my_theme_schemes']['sanitize_callback'];

        $saved = $sanitize([])['schemes'];
        self::assertSame('0a1b2c3d', $saved[0]['id']);
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $saved[1]['id']);
        self::assertNotSame('0a1b2c3d', $saved[1]['id']);

        $saved = $sanitize(['schemes' => [['id' => '0a1b2c3d', 'name' => 'A'], ['id' => '0a1b2c3d', 'name' => 'B'], ['id' => 'ffffffff', 'name' => 'C']]])['schemes'];
        self::assertSame(['0a1b2c3d', 'ffffffff'], [$saved[0]['id'], $saved[2]['id']]);
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $saved[1]['id']);
        self::assertNotContains($saved[1]['id'], ['0a1b2c3d', 'ffffffff']);
    }

    public function testFillingWalksNestedListsAndKeepsOtherValues(): void
    {
        $value = Identifiers::fill(
            ['tabs' => [['id' => 'aaaaaaaa', 'panes' => [['key' => ''], 'junk']], ['panes' => 'junk']], 'other' => 'x'],
            ['tabs.*.id', 'tabs.*.panes.*.key'],
        );
        /** @var array{tabs: array{0: array{id: string, panes: array{0: array{key: string}, 1: string}}, 1: array{id: string, panes: string}}, other: string} $value */
        self::assertSame('aaaaaaaa', $value['tabs'][0]['id']);
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $value['tabs'][0]['panes'][0]['key']);
        self::assertSame('junk', $value['tabs'][0]['panes'][1]);
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $value['tabs'][1]['id']);
        self::assertSame('junk', $value['tabs'][1]['panes']);
        self::assertSame('x', $value['other']);

        // Paths relative to a value that is a list itself, as for post meta.
        /** @var array{0: array{key: string}} $list */
        $list = Identifiers::fill([['key' => '']], ['*.key']);
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $list[0]['key']);
        self::assertSame('junk', Identifiers::fill('junk', ['*.key']));
    }

    public function testReportsAllErrors(): void
    {
        $at = static fn(string $class, string $name): string => sprintf('parameter $%s of %s::__construct()', $name, $class);
        $item = IdentifierBroken\BrokenItem::class;
        $attributes = IdentifierBroken\BrokenAttributes::class;
        $expected = [
            $at($item, 'number') . ' has #[Identifier], which is only allowed on string (eight hex digits, an empty string until the identifier is generated).',
            $at($item, 'nullable') . ' has #[Identifier], which is only allowed on string (eight hex digits, an empty string until the identifier is generated).',
            $at($item, 'plain') . ' has #[Identifier] and #[PlainText]; an identifier is generated and has no control.',
            $at($item, 'hidden') . ' has #[Identifier] and #[Hidden]; an identifier is generated and has no control.',
            $at($item, 'preset') . ': the default of an identifier must be \'\', the value of an item without identifier yet; the list editors generate it.',
            $at($attributes, 'two') . ': #[ListOf]: value class ' . IdentifierBroken\TwoIdentifiers::class . ' has #[Identifier] on $first and $second; an item has one identifier.',
            $at($attributes, 'single') . ': value class ' . IdentifierBroken\Single::class . ' has #[Identifier] on $id, which is only allowed in items of #[ListOf]; here the class is a value of its own.',
            $at($attributes, 'invalid') . ': item 0 of the default has the identifier "ABC"; an identifier has eight lowercase hex digits, or is empty to be generated.',
            $at($attributes, 'twice') . ': the identifier "0a1b2c3d" appears twice in the default.',
            $at(IdentifierBroken\Single::class, 'id') . ' has #[Identifier], which is only allowed in value classes that are items of #[ListOf]; Single is no list item.',
            $at(IdentifierBroken\TopAttributes::class, 'id') . ' has #[Identifier], which is only allowed in value classes that are items of #[ListOf]; TopAttributes is no list item.',
        ];

        try {
            new ManifestBuilder([ManifestBuilderTest::fixture('IdentifierBroken')])->build();
            self::fail('BuildException expected.');
        } catch (BuildException $exception) {
            self::assertSame($expected, $exception->getErrors());
        }
    }
}
