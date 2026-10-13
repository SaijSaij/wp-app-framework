<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Cache\Backend;

use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * Transients: always available; in the database unless an object cache stores them.
 *
 * @internal
 */
#[NoService]
final readonly class TransientBackend implements Backend
{
    /**
     * @param non-empty-string $prefix Prefix of the transient names: the slug with "_" instead of "-" and "_".
     */
    public function __construct(private string $prefix) {}

    public function isAvailable(): bool
    {
        return true;
    }

    public function get(string $key): ?array
    {
        $value = get_transient($this->name($key));

        return is_array($value) && array_key_exists('value', $value) ? ['value' => $value['value']] : null;
    }

    public function set(string $key, mixed $value, int $ttl): void
    {
        // set_transient() returns false also if the value did not change; a stored value with the same key is the same.
        set_transient($this->name($key), ['value' => $value], $ttl);
    }

    private function name(string $key): string
    {
        return $this->prefix . 'cache_' . $key;
    }
}
