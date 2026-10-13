<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Cache;

use BackedEnum;
use Closure;
use Saij\WpAppFramework\Cache\Tag\PostTypeTag;
use Saij\WpAppFramework\Cache\Tag\SettingsTag;
use Saij\WpAppFramework\Cache\Tag\TaxonomyTag;
use Saij\WpAppFramework\Cache\Vary\CookieVary;
use Saij\WpAppFramework\Cache\Vary\HeaderVary;
use Saij\WpAppFramework\Cache\Vary\LoggedInVary;
use Saij\WpAppFramework\Cache\Vary\QueryParamVary;
use Saij\WpAppFramework\Cache\Vary\RoleVary;
use Saij\WpAppFramework\Cache\Vary\ServiceVary;
use Saij\WpAppFramework\Cache\Vary\UrlVary;
use Saij\WpAppFramework\Cache\Vary\UserVary;
use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * Turns the ttl, tags and variants of a cache entry into plain data, the same
 * way in the build (#[Cache]) and at run time (CacheStore::remember()), and
 * reports invalid values.
 *
 * A tag becomes a key such as "post_type:post", "taxonomy:category",
 * "settings:{group name}" or "custom:{enum class}:{value}"; a variant becomes
 * an array with its type and, if it has one, its name or provider class.
 *
 * @internal
 *
 * @phpstan-type VaryEntry array{type: 'user'|'logged_in'|'role'|'url'}
 *     |array{type: 'query'|'header'|'cookie', name: non-empty-string}
 *     |array{type: 'service', class: class-string<VaryProvider>}
 * @phpstan-type CacheEntry array{ttl: int<1, max>, tags: list<non-empty-string>, vary: list<VaryEntry>}
 */
#[NoService]
final class CachePolicy
{
    /** Same limits as register_post_type() and register_taxonomy(). */
    private const int MAX_POST_TYPE_LENGTH = 20;

    private const int MAX_TAXONOMY_LENGTH = 32;

    /**
     * @param array<mixed>                          $tags
     * @param array<mixed>                          $vary
     * @param Closure(class-string): ?string        $settingsGroup Name of the settings group of a class; null if the
     *                                                             class is no settings group of the instance.
     * @param Closure(class-string): ?string        $providerError Why a class cannot provide a ServiceVary; null if it can.
     * @return array{0: CacheEntry|null, 1: list<string>} The entry, or null and the errors.
     */
    public static function entry(int $ttl, array $tags, array $vary, Closure $settingsGroup, Closure $providerError): array
    {
        $errors = [];
        if ($ttl < 1) {
            $errors[] = sprintf('the ttl must be at least 1 second, %d given', $ttl);
        }

        $tagKeys = [];
        foreach ($tags as $tag) {
            [$key, $error] = self::tagKey($tag, $settingsGroup);
            if ($key === null) {
                $errors[] = (string) $error;
                continue;
            }
            if (in_array($key, $tagKeys, true)) {
                $errors[] = sprintf('the tag %s is listed twice', $key);
                continue;
            }
            $tagKeys[] = $key;
        }

        $varyEntries = [];
        foreach ($vary as $variant) {
            [$entry, $error] = self::varyEntry($variant, $providerError);
            if ($entry === null) {
                $errors[] = (string) $error;
                continue;
            }
            if (in_array($entry, $varyEntries, true)) {
                $errors[] = sprintf('the variant %s is listed twice', self::describeVary($entry));
                continue;
            }
            $varyEntries[] = $entry;
        }

        if ($errors !== [] || $ttl < 1) {
            return [null, $errors];
        }

        return [['ttl' => $ttl, 'tags' => $tagKeys, 'vary' => $varyEntries], []];
    }

    /**
     * The key of a tag, or null and the reason it is invalid.
     *
     * @param Closure(class-string): ?string $settingsGroup
     * @return array{0: non-empty-string|null, 1: string|null}
     */
    public static function tagKey(mixed $tag, Closure $settingsGroup): array
    {
        if ($tag instanceof PostTypeTag) {
            $postType = $tag->getPostType();
            if ($postType === '' || strlen($postType) > self::MAX_POST_TYPE_LENGTH) {
                return [null, sprintf('PostTypeTag needs a post type name of 1 to %d characters, "%s" given', self::MAX_POST_TYPE_LENGTH, $postType)];
            }

            return ['post_type:' . $postType, null];
        }
        if ($tag instanceof TaxonomyTag) {
            $taxonomy = $tag->getTaxonomy();
            if ($taxonomy === '' || strlen($taxonomy) > self::MAX_TAXONOMY_LENGTH) {
                return [null, sprintf('TaxonomyTag needs a taxonomy name of 1 to %d characters, "%s" given', self::MAX_TAXONOMY_LENGTH, $taxonomy)];
            }

            return ['taxonomy:' . $taxonomy, null];
        }
        if ($tag instanceof SettingsTag) {
            $name = $settingsGroup($tag->getGroup());
            if ($name === null || $name === '') {
                return [null, sprintf('SettingsTag needs a class with #[SettingsGroup] of this theme or plugin, %s given', $tag->getGroup())];
            }

            return ['settings:' . $name, null];
        }
        if ($tag instanceof CacheTag && $tag instanceof BackedEnum && is_string($tag->value)) {
            return ['custom:' . $tag::class . ':' . $tag->value, null];
        }
        if ($tag instanceof CacheTag) {
            return [null, sprintf('%s implements CacheTag, but tags of your own must be string-backed enums', $tag::class)];
        }

        return [null, sprintf('tags must implement CacheTag (PostTypeTag, TaxonomyTag, SettingsTag or an enum of your own), %s given', get_debug_type($tag))];
    }

    /**
     * @param Closure(class-string): ?string $providerError
     * @return array{0: VaryEntry|null, 1: string|null}
     */
    private static function varyEntry(mixed $variant, Closure $providerError): array
    {
        $simple = match (true) {
            $variant instanceof UserVary => 'user',
            $variant instanceof LoggedInVary => 'logged_in',
            $variant instanceof RoleVary => 'role',
            $variant instanceof UrlVary => 'url',
            default => null,
        };
        if ($simple !== null) {
            return [['type' => $simple], null];
        }

        $named = match (true) {
            $variant instanceof QueryParamVary => ['query', 'QueryParamVary', $variant->getName()],
            $variant instanceof HeaderVary => ['header', 'HeaderVary', $variant->getName()],
            $variant instanceof CookieVary => ['cookie', 'CookieVary', $variant->getName()],
            default => null,
        };
        if ($named !== null) {
            [$type, $class, $name] = $named;
            if ($name === '') {
                return [null, sprintf('%s needs a name', $class)];
            }

            return [['type' => $type, 'name' => $name], null];
        }

        if ($variant instanceof ServiceVary) {
            $error = $providerError($variant->getProvider());
            if ($error !== null) {
                return [null, sprintf('ServiceVary(%s::class): %s', $variant->getProvider(), $error)];
            }

            return [['type' => 'service', 'class' => $variant->getProvider()], null];
        }

        return [null, sprintf('variants must be UserVary, LoggedInVary, RoleVary, UrlVary, QueryParamVary, HeaderVary, CookieVary or ServiceVary, %s given', get_debug_type($variant))];
    }

    /**
     * @param VaryEntry $entry
     */
    public static function describeVary(array $entry): string
    {
        return match ($entry['type']) {
            'user' => 'UserVary',
            'logged_in' => 'LoggedInVary',
            'role' => 'RoleVary',
            'url' => 'UrlVary',
            'query' => sprintf('QueryParamVary("%s")', $entry['name']),
            'header' => sprintf('HeaderVary("%s")', $entry['name']),
            'cookie' => sprintf('CookieVary("%s")', $entry['name']),
            'service' => sprintf('ServiceVary(%s)', $entry['class']),
        };
    }
}
