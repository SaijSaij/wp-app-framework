<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Cache;

/**
 * A service that provides a circumstance of your own for ServiceVary, e.g. the
 * region of the visitor.
 */
interface VaryProvider
{
    /**
     * The current value; each value gets a cache entry of its own.
     */
    public function value(): string|int|float|bool|null;
}
