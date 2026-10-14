<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Query\Field;

use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * A nullable bool column of a table (`?bool`). notEquals() and notIn() also match rows whose column is null, like
 * `null !== $value` in PHP.
 */
#[NoService]
final readonly class NullableBoolField extends BoolField
{
    use NullChecks;
}
