<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Query\Field;

use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * A nullable int column of a table (`?int`). notEquals() and notIn() also match rows whose column is null, like
 * `null !== $value` in PHP.
 */
#[NoService]
final readonly class NullableIntField extends IntField
{
    use NullChecks;
}
