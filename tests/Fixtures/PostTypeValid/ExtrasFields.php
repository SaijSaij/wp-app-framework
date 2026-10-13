<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\PostTypeValid;

use Saij\WpAppFramework\Query\Field\BoolField;
use Saij\WpAppFramework\Query\MetaFields;

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
