<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Query\Field;

use BackedEnum;
use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * A nullable backed enum column of a table (`?Level`). notEquals() and notIn() also match rows whose column is
 * null, like `null !== $value` in PHP.
 *
 * @template E of BackedEnum
 * @extends EnumField<E>
 *
 * @api Query fields
 */
#[NoService]
final readonly class NullableEnumField extends EnumField
{
    use NullChecks;
}
