<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\TableValid;

use WebCodingCat\WpAppFramework\Query\Field\StringField;
use WebCodingCat\WpAppFramework\Table\TableFields;

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
