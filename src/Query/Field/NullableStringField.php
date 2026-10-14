<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Query\Field;

use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * A nullable string column of a table (`?string`). notEquals() and notIn() also match rows whose column is null, like
 * `null !== $value` in PHP.
 */
#[NoService]
final readonly class NullableStringField extends StringField
{
    use NullChecks;
}
