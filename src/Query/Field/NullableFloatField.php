<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Query\Field;

use WebCodingCat\WpAppFramework\DependencyInjection\NoService;

/**
 * A nullable float column of a table (`?float`). notEquals() and notIn() also match rows whose column is null, like
 * `null !== $value` in PHP.
 *
 * @api Query fields
 */
#[NoService]
final readonly class NullableFloatField extends FloatField
{
    use NullChecks;
}
