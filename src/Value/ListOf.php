<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Value;

use Attribute;

/**
 * Declares the item type of an `array` parameter: a backed enum or a value
 * class (PHP has no generics). Used in block attribute classes, value classes
 * and REST arguments.
 */
#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
final readonly class ListOf
{
    /**
     * @param class-string $class A backed enum or a value class.
     */
    public function __construct(public string $class) {}
}
