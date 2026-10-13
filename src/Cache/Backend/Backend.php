<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Cache\Backend;

/**
 * Stores cache entries; values are plain data (null, scalars and arrays of them).
 *
 * @internal
 */
interface Backend
{
    /**
     * Whether the backend can be used in this request.
     */
    public function isAvailable(): bool;

    /**
     * The stored value, or null on a miss; the value is wrapped, so a stored null is a hit.
     *
     * @param non-empty-string $key
     * @return array{value: mixed}|null
     */
    public function get(string $key): ?array;

    /**
     * @param non-empty-string $key
     * @param int<1, max>      $ttl Seconds.
     */
    public function set(string $key, mixed $value, int $ttl): void;
}
