<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Cache;

use WebCodingCat\WpAppFramework\DependencyInjection\NoService;

/**
 * The current version of each tag, part of the keys of the entries with that
 * tag: invalidating a tag gives it a new version, so its entries are never
 * read again and expire.
 *
 * A version is a random value in an autoloaded option per tag, created when the
 * tag is first read. Options are cached by WordPress (with a persistent object
 * cache, there), but never lost like an evicted object cache entry, which would
 * bring back old entries. Invalidating a tag nobody has read yet does nothing.
 *
 * @internal
 */
#[NoService]
final class TagVersions
{
    /** @var array<string, string> Version by tag key, read in this request. */
    private array $versions = [];

    /**
     * @param non-empty-string $prefix Prefix of the option names: the slug with "_" instead of "-" and "_".
     */
    public function __construct(private readonly string $prefix) {}

    /**
     * @param list<non-empty-string> $tags
     * @return array<string, string> Version by tag key.
     */
    public function get(array $tags): array
    {
        $versions = [];
        foreach ($tags as $tag) {
            $versions[$tag] = $this->versions[$tag] ??= $this->read($tag);
        }

        return $versions;
    }

    /**
     * @param non-empty-string $tag
     */
    public function invalidate(string $tag): void
    {
        $option = $this->option($tag);
        if (get_option($option) === false) {
            unset($this->versions[$tag]);

            return;
        }

        $version = self::newVersion();
        update_option($option, $version, true);
        $this->versions[$tag] = $version;
    }

    /**
     * The option of a tag: the prefix, "cache_tag_" and a hash of the tag key (tag keys may be longer than option names).
     */
    public function option(string $tag): string
    {
        return $this->prefix . 'cache_tag_' . hash('xxh128', $tag);
    }

    private function read(string $tag): string
    {
        $option = $this->option($tag);
        $version = get_option($option);
        if (is_string($version) && $version !== '') {
            return $version;
        }

        $version = self::newVersion();
        if (add_option($option, $version, '', true)) {
            return $version;
        }

        // Another request added it in the meantime.
        $stored = get_option($option);

        return is_string($stored) && $stored !== '' ? $stored : $version;
    }

    private static function newVersion(): string
    {
        return bin2hex(random_bytes(8));
    }
}
