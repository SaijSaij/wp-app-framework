<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Query;

use PHPUnit\Framework\TestCase;
use Saij\WpAppFramework\Query\All;
use Saij\WpAppFramework\Query\Any;
use Saij\WpAppFramework\Query\Field\FieldTypes;
use Saij\WpAppFramework\Query\Field\IntField;
use Saij\WpAppFramework\Query\Field\NullableIntField;
use Saij\WpAppFramework\Query\Group;
use Saij\WpAppFramework\Tests\Fixtures\PostTypeValid\EventDetails;
use Saij\WpAppFramework\Tests\Fixtures\PostTypeValid\EventDetailsFields;
use Saij\WpAppFramework\Tests\Fixtures\TableValid\LogEntryFields;

/**
 * The kinds of conditions: meta fields, terms and the columns of tables.
 */
final class GroupTest extends TestCase
{
    public function testConditionsOnColumnsAreOfTheirOwnKind(): void
    {
        self::assertSame('row', Group::kindOf(LogEntryFields::attempts()->greaterThan(1)));
        self::assertSame('row', Any::of(LogEntryFields::attempts()->greaterThan(1), All::of(LogEntryFields::postId()->isNull()))->getKind());
        self::assertSame('meta', Group::kindOf(EventDetailsFields::capacity()->equals(1)));
    }

    public function testNullableFieldClassesNeverFitMetaFields(): void
    {
        self::assertSame(
            sprintf('%s::$capacity needs %s, %s given', EventDetails::class, IntField::class, NullableIntField::class),
            FieldTypes::mismatch(new NullableIntField(EventDetails::class, 'capacity'), ['type' => 'int', 'nullable' => false]),
        );
    }
}
