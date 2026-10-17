<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Value;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Saij\WpAppFramework\Block\BlockDefinition;
use Saij\WpAppFramework\Manifest\BuildException;
use Saij\WpAppFramework\Manifest\BuildResult;
use Saij\WpAppFramework\Manifest\ManifestBuilder;
use Saij\WpAppFramework\Tests\Fixtures\DateTimeBroken\BrokenAttributes;
use Saij\WpAppFramework\Tests\Fixtures\DateTimeValid;
use Saij\WpAppFramework\Tests\Unit\Manifest\ManifestBuilderTest;
use Saij\WpAppFramework\Value\DateTime;
use Saij\WpAppFramework\Value\ValueFactory;

/**
 * Dates (`#[DateTime]`): schema with pattern, control and value description, the check of the value factory and
 * build errors.
 */
final class DateTimeTest extends TestCase
{
    private static function build(): BuildResult
    {
        return new ManifestBuilder([ManifestBuilderTest::fixture('DateTimeValid')])->build();
    }

    private static function concert(BuildResult $result): BlockDefinition
    {
        return $result->getBlocks()[0];
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private static function create(BuildResult $result, array $attributes): DateTimeValid\ConcertAttributes
    {
        return new ValueFactory($result->getValues())
            ->createAttributes(DateTimeValid\ConcertAttributes::class, $attributes, self::concert($result)->getArgs()['attributes']);
    }

    public function testDatesAreStringsWithAPatternAndAControlOfTheirOwn(): void
    {
        $result = self::build();
        $block = self::concert($result);
        $attributes = $block->getArgs()['attributes'];

        self::assertSame(['type' => 'string', 'pattern' => DateTime::PATTERN, 'default' => ''], $attributes['start']);
        self::assertSame(['type' => 'string', 'pattern' => DateTime::DATE_PATTERN, 'default' => '2026-11-01'], $attributes['saleEnds']);
        self::assertSame(
            ['type' => 'object', 'properties' => ['at' => ['type' => 'string', 'pattern' => DateTime::PATTERN, 'default' => '2026-10-31T18:30']]],
            $attributes['rehearsals']['items'],
        );

        $controls = $block->getEditor()['controls'];
        self::assertSame(['type' => 'date', 'label' => 'Start', 'nullable' => false, 'time' => true, 'content' => false], $controls['start']);
        self::assertSame(['type' => 'date', 'label' => 'Sale ends', 'nullable' => false, 'time' => false, 'content' => false], $controls['saleEnds']);
        $values = $result->getValues()[DateTimeValid\ConcertAttributes::class];
        self::assertSame(['type' => 'string', 'nullable' => false, 'date' => true], $values['start']['value']);
        self::assertSame(['type' => 'string', 'nullable' => false, 'date' => false], $values['saleEnds']['value']);
    }

    public function testThePatternsAcceptTheirFormatAndTheEmptyString(): void
    {
        // WordPress matches patterns like this in rest_validate_json_schema_pattern().
        $wordpress = static fn(string $pattern): string => '#' . str_replace('#', '\\#', $pattern) . '#u';
        foreach (['', '2026-11-14T20:00', '2026-02-28T00:00', '2028-02-29T23:59'] as $date) {
            self::assertSame(1, preg_match($wordpress(DateTime::PATTERN), $date), $date);
            self::assertTrue(DateTime::isDate($date, true), $date);
        }
        foreach (['', '2026-11-14', '2028-02-29'] as $date) {
            self::assertSame(1, preg_match($wordpress(DateTime::DATE_PATTERN), $date), $date);
            self::assertTrue(DateTime::isDate($date, false), $date);
        }
        foreach (['2026-11-14', '2026-11-14 20:00', '2026-11-14T20:00:00', '2026-11-14T20:00Z', '14.11.2026 20:00'] as $date) {
            self::assertSame(0, preg_match($wordpress(DateTime::PATTERN), $date), $date);
            self::assertFalse(DateTime::isDate($date, true), $date);
        }
        // The pattern accepts dates that do not exist; isDate() does not.
        foreach (['2026-02-29T20:00', '2026-11-31T20:00', '2026-11-14T24:00', '2026-11-14T20:60', '2026-13-01T00:00'] as $date) {
            self::assertSame(1, preg_match($wordpress(DateTime::PATTERN), $date), $date);
            self::assertFalse(DateTime::isDate($date, true), $date);
        }
        self::assertFalse(DateTime::isDate('2026-02-30', false));
        self::assertFalse(DateTime::isDate("2026-11-14\n", false));
    }

    public function testTheValueFactoryChecksDatesAgain(): void
    {
        $result = self::build();

        $attrs = self::create($result, ['start' => '2026-11-14T20:00', 'rehearsals' => [['at' => '2026-11-13T19:00']]]);
        self::assertSame('2026-11-14T20:00', $attrs->start);
        self::assertSame('2026-11-01', $attrs->saleEnds);
        self::assertSame('2026-11-13T19:00', $attrs->rehearsals[0]->at);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Value "saleEnds" must be a date in the format YYYY-MM-DD or an empty string, \'2026-02-30\' given.');
        self::create($result, ['saleEnds' => '2026-02-30']);
    }

    public function testReportsAllErrors(): void
    {
        $at = static fn(string $name): string => sprintf('parameter $%s of %s::__construct()', $name, BrokenAttributes::class);
        $expected = [
            $at('timestamp') . ' has #[DateTime], which is only allowed on strings (a date, an empty string for no date).',
            $at('nullable') . ' has #[DateTime] on ?string; use string, an empty string means no date.',
            $at('plain') . ' has #[DateTime] and #[PlainText]; a date has a control of its own.',
            $at('color') . ' has #[DateTime] and #[Color]; a date has a control of its own.',
            $at('withSeconds') . ': the default "2026-11-14T20:00:00" is no date; use a date such as "2026-11-14T20:00", or an empty string for no date.',
            $at('withTime') . ': the default "2026-11-14T20:00" is no date; use a date such as "2026-11-14", or an empty string for no date.',
            $at('missing') . ': the default "2026-02-30T20:00" is no date; use a date such as "2026-11-14T20:00", or an empty string for no date.',
        ];

        try {
            new ManifestBuilder([ManifestBuilderTest::fixture('DateTimeBroken')])->build();
            self::fail('BuildException expected.');
        } catch (BuildException $exception) {
            self::assertSame($expected, $exception->getErrors());
        }
    }
}
