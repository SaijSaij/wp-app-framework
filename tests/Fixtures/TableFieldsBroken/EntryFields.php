<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\TableFieldsBroken;

use RuntimeException;
use Saij\WpAppFramework\Query\Field\EnumField;
use Saij\WpAppFramework\Query\Field\Field;
use Saij\WpAppFramework\Query\Field\IntField;
use Saij\WpAppFramework\Query\Field\NullableIntField;
use Saij\WpAppFramework\Query\Field\StringField;
use Saij\WpAppFramework\Table\TableFields;

/**
 * One method per error; the valid ones are not reported.
 */
#[TableFields(Entry::class)]
final class EntryFields
{
    public static function seats(): IntField
    {
        return new IntField(Entry::class, 'seats');
    }

    public static function wrongClass(): StringField
    {
        return new StringField(Entry::class, 'seats');
    }

    public static function notNullable(): NullableIntField
    {
        return new NullableIntField(Entry::class, 'seats');
    }

    public static function nullable(): IntField
    {
        return new IntField(Entry::class, 'parent');
    }

    /**
     * @return EnumField<Other>
     */
    public static function wrongEnum(): EnumField
    {
        return new EnumField(Entry::class, 'kind', Other::class);
    }

    public static function unknown(): IntField
    {
        return new IntField(Entry::class, 'sits');
    }

    public static function otherRow(): StringField
    {
        return new StringField(Note::class, 'text');
    }

    public static function withParameter(string $name): Field
    {
        return new IntField(Entry::class, $name);
    }

    public static function failing(): Field
    {
        throw new RuntimeException('Not today.');
    }
}
