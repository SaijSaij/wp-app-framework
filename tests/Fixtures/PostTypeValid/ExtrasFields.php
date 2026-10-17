<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\PostTypeValid;

use WebCodingCat\WpAppFramework\Query\Field\BoolField;
use WebCodingCat\WpAppFramework\Query\MetaFields;

/**
 * Written by hand: only the field that is queried.
 */
#[MetaFields(Extras::class)]
final class ExtrasFields
{
    public static function featured(): BoolField
    {
        return new BoolField(Extras::class, 'featured');
    }
}
