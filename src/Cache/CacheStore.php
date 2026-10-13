<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Cache;

use Closure;
use InvalidArgumentException;
use LogicException;
use Saij\WpAppFramework\Cache\Backend\Backend;
use Saij\WpAppFramework\Cache\Backend\FileBackend;
use Saij\WpAppFramework\DependencyInjection\Container;

/**
 * The cache of a theme or plugin. Inject it to cache data of your own, for
 * example the response of an external API:
 *
 *     $weather = $this->cache->remember('weather', fn(): array => $this->client->fetch(), ttl: 600, tags: [Feed::Weather]);
 *
 * Blocks and REST routes with #[Cache] use the same cache. Entries are stored
 * in the first available backend of the filter
 * `wp_app_framework/cache/backends/{slug}` (default: object cache, files,
 * transients).
 *
 * @phpstan-import-type CacheEntry from CachePolicy
 * @phpstan-import-type VaryEntry from CachePolicy
 */
final class CacheStore
{
    /** Default order of the backends; the first available one stores the entries. */
    public const array DEFAULT_BACKENDS = ['object', 'file', 'transient'];

    private ?Backend $backend = null;

    private ?string $backendName = null;

    private bool $backendSelected = false;

    /**
     * @param non-empty-string            $slug
     * @param array<value-of<CacheBackend>, Backend> $backends
     * @param array<class-string, string> $settingsGroups Group name by class of the settings groups.
     */
    public function __construct(
        private readonly string $slug,
        private readonly array $backends,
        private readonly TagVersions $versions,
        private readonly CodeGeneration $generation,
        private readonly Container $container,
        private readonly array $settingsGroups = [],
    ) {}

    /**
     * Returns the cached value of the key, or computes, stores and returns it.
     *
     * Values must be plain data: null, booleans, numbers, strings and arrays of
     * them. Exceptions of $compute are passed on; nothing is stored then.
     *
     * @template T
     * @param Closure(): T    $compute
     * @param int             $ttl  Lifetime in seconds, at least 1.
     * @param list<CacheTag>  $tags Invalidate the entry.
     * @param list<CacheVary> $vary Circumstances the entry depends on.
     * @return T
     */
    public function remember(string $key, Closure $compute, int $ttl, array $tags = [], array $vary = []): mixed
    {
        if ($key === '') {
            throw new InvalidArgumentException('CacheStore::remember() needs a key that is not empty.');
        }
        [$entry, $errors] = CachePolicy::entry(
            $ttl,
            $tags,
            $vary,
            fn(string $class): ?string => $this->settingsGroups[$class] ?? null,
            static fn(string $class): ?string => is_subclass_of($class, VaryProvider::class) ? null : sprintf('%s does not implement VaryProvider', $class),
        );
        if ($entry === null) {
            throw new InvalidArgumentException(sprintf('CacheStore::remember("%s"): %s.', $key, implode('; ', $errors)));
        }

        /** @var T The value $compute returned, possibly in an earlier request. */
        return $this->fetch('custom', $key, null, $entry, static fn(): array => [$compute(), true])[0];
    }

    /**
     * Invalidates the tags: entries with one of them are never read again.
     */
    public function invalidate(CacheTag ...$tags): void
    {
        foreach ($tags as $tag) {
            [$key, $error] = CachePolicy::tagKey($tag, fn(string $class): ?string => $this->settingsGroups[$class] ?? null);
            if ($key === null) {
                throw new InvalidArgumentException(sprintf('CacheStore::invalidate(): %s.', $error));
            }
            $this->versions->invalidate($key);
        }
    }

    /**
     * The backend that stores the entries in this request ("object", "file" or "transient"); null if caching is off.
     */
    public function getBackendName(): ?string
    {
        $this->backend();

        return $this->backendName;
    }

    /**
     * Returns the cached value or computes it; $compute returns the value and whether it may be stored.
     *
     * @internal Used by the block and REST registrars.
     *
     * @param 'block'|'rest'|'custom'          $kind
     * @param CacheEntry                       $entry
     * @param Closure(): array{0: mixed, 1: bool} $compute
     * @return array{0: mixed, 1: bool} The value and whether it came from the cache.
     */
    public function fetch(string $kind, string $id, mixed $inputs, array $entry, Closure $compute): array
    {
        $backend = $this->backend();
        if ($backend === null) {
            return [$compute()[0], false];
        }

        $key = hash('xxh128', serialize([
            $this->slug,
            get_current_blog_id(),
            $kind,
            $id,
            $inputs,
            array_map($this->varyValue(...), $entry['vary']),
            $this->versions->get($entry['tags']),
            $this->generation->get(),
        ]));

        $cached = $backend->get($key);
        if ($cached !== null) {
            return [$cached['value'], true];
        }

        [$value, $store] = $compute();
        if ($store) {
            self::assertPlainData($value, sprintf('%s %s', $kind === 'custom' ? 'key' : $kind, $id), '');
            $backend->set($key, $value, $entry['ttl']);
        }

        return [$value, false];
    }

    /**
     * Deletes expired entries of the file backend (WP-Cron).
     *
     * @internal
     */
    public function cleanUp(): void
    {
        $file = $this->backends[CacheBackend::File->value] ?? null;
        if ($file instanceof FileBackend) {
            $file->cleanUp();
        }
    }

    private function backend(): ?Backend
    {
        if ($this->backendSelected) {
            return $this->backend;
        }

        $filter = 'wp_app_framework/cache/backends/' . $this->slug;
        $configured = apply_filters($filter, self::DEFAULT_BACKENDS);
        if (!is_array($configured) || !array_is_list($configured)) {
            throw new LogicException(sprintf('The filter %s must return a list of backends, %s given.', $filter, get_debug_type($configured)));
        }
        foreach ($configured as $name) {
            $case = is_string($name) ? CacheBackend::tryFrom($name) : null;
            if ($case === null) {
                throw new LogicException(sprintf(
                    'The filter %s returned the unknown backend %s; use "%s".',
                    $filter,
                    var_export($name, true),
                    implode('", "', array_column(CacheBackend::cases(), 'value')),
                ));
            }
            $backend = $this->backends[$case->value] ?? null;
            if ($backend !== null && $backend->isAvailable()) {
                $this->backend = $backend;
                $this->backendName = $case->value;
                break;
            }
        }
        $this->backendSelected = true;

        return $this->backend;
    }

    /**
     * @param VaryEntry $variant
     */
    private function varyValue(array $variant): mixed
    {
        return match ($variant['type']) {
            'user' => get_current_user_id(),
            'logged_in' => is_user_logged_in(),
            'role' => self::roles(),
            'url' => wp_parse_url(self::server('REQUEST_URI') ?? '/', PHP_URL_PATH),
            'query' => array_key_exists($variant['name'], $_GET) ? wp_unslash($_GET[$variant['name']]) : null,
            'header' => self::server(self::headerKey($variant['name'])),
            'cookie' => array_key_exists($variant['name'], $_COOKIE) ? wp_unslash($_COOKIE[$variant['name']]) : null,
            'service' => $this->container->get($variant['class'])->value(),
        };
    }

    /**
     * @return list<string>
     */
    private static function roles(): array
    {
        $roles = array_values(array_filter(wp_get_current_user()->roles, is_string(...)));
        sort($roles, SORT_STRING);

        return $roles;
    }

    private static function server(string $key): ?string
    {
        $value = $_SERVER[$key] ?? null;

        return is_string($value) ? wp_unslash($value) : null;
    }

    /**
     * The key of a request header in $_SERVER, e.g. HTTP_ACCEPT_LANGUAGE; Content-Type and Content-Length have no prefix.
     */
    private static function headerKey(string $name): string
    {
        $key = strtoupper(str_replace('-', '_', $name));

        return in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true) ? $key : 'HTTP_' . $key;
    }

    private static function assertPlainData(mixed $value, string $entry, string $path): void
    {
        if ($value === null || is_scalar($value)) {
            return;
        }
        if (is_array($value)) {
            foreach ($value as $index => $item) {
                self::assertPlainData($item, $entry, $path . '[' . var_export($index, true) . ']');
            }

            return;
        }

        throw new LogicException(sprintf(
            'The cache value of %s contains %s%s; cache values must be plain data (null, booleans, numbers, strings and arrays of them).',
            $entry,
            get_debug_type($value),
            $path === '' ? '' : ' at ' . $path,
        ));
    }
}
