<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\TableBroken;

use Saij\WpAppFramework\Query\Field\StringField;
use Saij\WpAppFramework\Table\TableFields;

#[TableFields(Valid::class)]
final class ValidFields
{
    public static function name(): StringField
    {
        return new StringField(Valid::class, 'name');
    }
}
