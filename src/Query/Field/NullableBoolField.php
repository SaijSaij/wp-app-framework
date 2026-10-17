<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Query\Field;

use WebCodingCat\WpAppFramework\DependencyInjection\NoService;

/**
 * A nullable bool column of a table (`?bool`). notEquals() and notIn() also match rows whose column is null, like
 * `null !== $value` in PHP.
 *
 * @api Query fields
 */
#[NoService]
final readonly class NullableBoolField extends BoolField
{
    use NullChecks;
}
