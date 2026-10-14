<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\TableValid;

use Saij\WpAppFramework\Query\Field\StringField;
use Saij\WpAppFramework\Table\TableFields;

/**
 * Written by hand, with one field only.
 */
#[TableFields(Counter::class)]
final class CounterFields
{
    public static function name(): StringField
    {
        return new StringField(Counter::class, 'name');
    }
}
