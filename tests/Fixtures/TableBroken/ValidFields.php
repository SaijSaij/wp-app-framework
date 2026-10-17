<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\TableBroken;

use WebCodingCat\WpAppFramework\Query\Field\StringField;
use WebCodingCat\WpAppFramework\Table\TableFields;

#[TableFields(Valid::class)]
final class ValidFields
{
    public static function name(): StringField
    {
        return new StringField(Valid::class, 'name');
    }
}
