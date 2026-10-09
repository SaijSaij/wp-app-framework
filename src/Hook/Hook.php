<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Hook;

use Attribute;

/**
 * Registers a public method of a service as a WordPress action or filter.
 *
 * A method with return type `void` becomes an action, any other return type a filter.
 * Parameters whose type is a service candidate are injected; all other parameters
 * receive the arguments passed by WordPress, in their order.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class Hook
{
    public function __construct(
        public string $name,
        public int $priority = 10,
    ) {
    }
}
