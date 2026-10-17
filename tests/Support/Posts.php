<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Support;

use Closure;
use WebCodingCat\WpAppFramework\Cache\CacheStore;
use WebCodingCat\WpAppFramework\Editor\RenderContext;
use WebCodingCat\WpAppFramework\Manifest\Manifest;
use WebCodingCat\WpAppFramework\Manifest\ManifestGenerator;
use WebCodingCat\WpAppFramework\Post\PostRegistry;
use WebCodingCat\WpAppFramework\Post\PostStore;
use WebCodingCat\WpAppFramework\Tests\Unit\Post\PostTypeCollectorTest;
use WebCodingCat\WpAppFramework\Value\ValueFactory;
use WP_Post;
use WP_Term;

/**
 * The post store of the PostTypeValid fixture, registered like a booted instance, and the posts, meta and terms of
 * the WordPress test doubles.
 *
 * @phpstan-import-type ManifestData from Manifest
 */
final class Posts
{
    /**
     * @param (Closure(): CacheStore)|null $cache
     * @return array{PostStore, ManifestData}
     */
    public static function store(?RenderContext $context = null, ?Closure $cache = null): array
    {
        $file = (string) tempnam(sys_get_temp_dir(), 'manifest');
        file_put_contents($file, new ManifestGenerator()->generate(PostTypeCollectorTest::buildValid()));
        $manifest = Manifest::load($file);
        unlink($file);

        $store = new PostStore('my-theme', $manifest['posts'], $manifest['taxonomies'], $manifest['meta'], $manifest['values'], new ValueFactory($manifest['values'], $context), $cache);
        PostRegistry::reset();
        PostRegistry::register($store);

        return [$store, $manifest];
    }

    /**
     * Adds a post to the test doubles.
     *
     * @param array<string, mixed> $data
     */
    public static function add(int $id, string $type, array $data = []): WP_Post
    {
        $post = new WP_Post((object) ['ID' => $id, 'post_type' => $type, 'post_title' => 'Post ' . $id, ...$data]);
        /** @var array<int, WP_Post> $posts */
        $posts = $GLOBALS['wp_app_framework_test_posts'] ?? [];
        $posts[$id] = $post;
        $GLOBALS['wp_app_framework_test_posts'] = $posts;

        return $post;
    }

    /**
     * @param array<string, mixed> $meta Stored meta of a post by key.
     */
    public static function addMeta(int $id, array $meta): void
    {
        /** @var array<int, array<string, mixed>> $stored */
        $stored = $GLOBALS['wp_app_framework_test_meta'] ?? [];
        $stored[$id] = [...$stored[$id] ?? [], ...$meta];
        $GLOBALS['wp_app_framework_test_meta'] = $stored;
    }

    /**
     * @return array<string, mixed> Stored meta of a post by key.
     */
    public static function meta(int $id): array
    {
        /** @var array<int, array<string, mixed>> $stored */
        $stored = $GLOBALS['wp_app_framework_test_meta'] ?? [];

        return $stored[$id] ?? [];
    }

    public static function addTerm(int $id, string $taxonomy, string $name): void
    {
        /** @var array<int, WP_Term> $terms */
        $terms = $GLOBALS['wp_app_framework_test_terms'] ?? [];
        $terms[$id] = new WP_Term((object) ['term_id' => $id, 'taxonomy' => $taxonomy, 'name' => $name]);
        $GLOBALS['wp_app_framework_test_terms'] = $terms;
    }

    /**
     * Sets the current screen: the edit screen of a post type.
     */
    public static function screen(string $postType, bool $blockEditor = false): void
    {
        $screen = new \WP_Screen();
        $screen->base = 'post';
        $screen->post_type = $postType;
        $screen->is_block_editor($blockEditor);
        $GLOBALS['wp_app_framework_test_screen'] = $screen;
    }

    public static function reset(): void
    {
        PostRegistry::reset();
        unset(
            $GLOBALS['wp_app_framework_test_posts'],
            $GLOBALS['wp_app_framework_test_meta'],
            $GLOBALS['wp_app_framework_test_terms'],
            $GLOBALS['wp_app_framework_test_object_terms'],
            $GLOBALS['wp_app_framework_test_post_types'],
            $GLOBALS['wp_app_framework_test_taxonomies'],
            $GLOBALS['wp_app_framework_test_registered_meta'],
            $GLOBALS['wp_app_framework_test_flushes'],
            $GLOBALS['wp_app_framework_test_options'],
            $GLOBALS['wp_app_framework_test_meta_boxes'],
            $GLOBALS['wp_app_framework_test_removed_meta_boxes'],
            $GLOBALS['wp_app_framework_test_transients'],
            $GLOBALS['wp_app_framework_test_screen'],
            $GLOBALS['wp_app_framework_test_user'],
            $GLOBALS['wp_app_framework_test_queries'],
            $GLOBALS['wp_app_framework_test_query_clauses'],
            $GLOBALS['wp_app_framework_test_query_result'],
            $GLOBALS['wp_app_framework_test_primed'],
            $_POST['my-theme-meta-nonce'],
            $_POST['wp-app-framework-meta'],
        );
    }
}
