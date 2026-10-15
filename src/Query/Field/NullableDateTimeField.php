<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Query\Field;

use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * A nullable time column of a table (`?DateTimeImmutable`). notEquals() and notIn() also match rows whose column is
 * null, like `null !== $value` in PHP.
 *
 * @api Query fields
 */
#[NoService]
final readonly class NullableDateTimeField extends DateTimeField
{
    use NullChecks;
}
