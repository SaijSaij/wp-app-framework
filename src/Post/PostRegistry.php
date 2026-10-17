<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Post;

use LogicException;
use WebCodingCat\WpAppFramework\DependencyInjection\NoService;

/**
 * Which instance a post, meta or taxonomy class belongs to. Post objects load and
 * save themselves (AbstractPost::get(), save()), so they reach the store of their
 * instance through this registry, filled when an instance boots.
 *
 * The keys are classes of the consumers, unique to one instance also in development
 * mode, when all consumers share the framework classes. Post and Page belong to the
 * framework: every instance registers them with the same name. Not a service: nothing
 * outside the framework reads the registry.
 *
 * @internal
 */
#[NoService]
final class PostRegistry
{
    /** @var array<class-string, PostStore> */
    private static array $posts = [];

    /** @var array<class-string, PostStore> */
    private static array $meta = [];

    /** @var array<class-string, PostStore> */
    private static array $taxonomies = [];

    public static function register(PostStore $store): void
    {
        foreach (array_keys($store->getPosts()) as $class) {
            self::$posts[$class] ??= $store;
        }
        foreach (array_keys($store->getMeta()) as $class) {
            self::$meta[$class] = $store;
        }
        foreach (array_keys($store->getTaxonomies()) as $class) {
            self::$taxonomies[$class] = $store;
        }
    }

    /**
     * @param class-string $class
     */
    public static function postStore(string $class): PostStore
    {
        return self::$posts[$class] ?? throw new LogicException(sprintf(
            '%s is no post class of a booted theme or plugin: post classes need #[PostType] and are available once the theme or plugin has booted (rebuild the manifest after code changes).',
            $class,
        ));
    }

    /**
     * @param class-string $class
     */
    public static function metaStore(string $class): PostStore
    {
        return self::$meta[$class] ?? throw new LogicException(sprintf(
            '%s is no meta class of a booted theme or plugin: meta classes need #[PostMeta] (rebuild the manifest after code changes).',
            $class,
        ));
    }

    /**
     * @param class-string $class
     */
    public static function taxonomyName(string $class): string
    {
        $store = self::$taxonomies[$class] ?? throw new LogicException(sprintf(
            '%s is no taxonomy class of a booted theme or plugin: taxonomy classes need #[Taxonomy] or are Category and Tag (rebuild the manifest after code changes).',
            $class,
        ));

        return $store->getTaxonomies()[$class]['name'];
    }

    /**
     * Forgets all registrations; for tests only.
     */
    public static function reset(): void
    {
        self::$posts = [];
        self::$meta = [];
        self::$taxonomies = [];
    }
}
