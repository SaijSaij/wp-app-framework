<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Cache\Backend;

use RuntimeException;
use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * The persistent object cache: available if an object-cache.php drop-in is
 * installed (wp_using_ext_object_cache()). Keys carry the slug of the instance.
 *
 * @internal
 */
#[NoService]
final readonly class ObjectBackend implements Backend
{
    public const string GROUP = 'wp-app-framework';

    /**
     * @param non-empty-string $slug
     */
    public function __construct(private string $slug) {}

    public function isAvailable(): bool
    {
        // Without a drop-in, WordPress returns its global unchanged, which may still be null (load.php).
        return wp_using_ext_object_cache() === true;
    }

    public function get(string $key): ?array
    {
        $found = false;
        $value = wp_cache_get($this->slug . ':' . $key, self::GROUP, false, $found);

        return $found && is_array($value) && array_key_exists('value', $value) ? ['value' => $value['value']] : null;
    }

    public function set(string $key, mixed $value, int $ttl): void
    {
        if (!wp_cache_set($this->slug . ':' . $key, ['value' => $value], self::GROUP, $ttl)) {
            throw new RuntimeException(sprintf('The object cache did not store the cache entry %s of %s.', $key, $this->slug));
        }
    }
}
