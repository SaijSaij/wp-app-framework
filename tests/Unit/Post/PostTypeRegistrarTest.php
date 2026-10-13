<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Post;

use PHPUnit\Framework\TestCase;
use Saij\WpAppFramework\Post\PostTypeRegistrar;
use Saij\WpAppFramework\Tests\Support\Posts;
use Saij\WpAppFramework\Tests\Support\Recorded;

/**
 * Registration of post types, taxonomies and meta on init, and the rewrite rules.
 */
final class PostTypeRegistrarTest extends TestCase
{
    protected function tearDown(): void
    {
        Posts::reset();
    }

    public function testRegistersPostTypesTaxonomiesAndOneMetaKeyPerField(): void
    {
        [$store, $manifest] = Posts::store();
        new PostTypeRegistrar($store, 'my_theme_rewrite', $manifest['rewrite'])->register();

        $types = Recorded::postTypes();
        self::assertSame(['fixture_event', 'fixture_venue'], array_keys($types), 'Post and page are registered by WordPress.');
        self::assertSame(['name' => 'Events'], array_intersect_key((array) $types['fixture_event']['labels'], ['name' => true]));
        [$objectTypes, $taxonomyArgs] = Recorded::taxonomies()['fixture_genre'];
        self::assertSame(['fixture_event', 'post'], $objectTypes);
        self::assertSame(['name' => 'Genres'], array_intersect_key((array) $taxonomyArgs['labels'], ['name' => true]));

        $meta = Recorded::registeredMeta();
        self::assertSame(['fixture_event', 'post', 'page'], array_keys($meta));
        $capacity = $meta['fixture_event']['fixture_capacity'];
        self::assertSame(['integer', true, 0, ['schema' => ['type' => 'integer', 'default' => 0]], true], [
            $capacity['type'],
            $capacity['single'],
            $capacity['default'],
            $capacity['show_in_rest'],
            // The post type supports revisions.
            $capacity['revisions_enabled'],
        ]);
        self::assertSame(['fixture_subtitle', 'fixture_featured'], array_keys($meta['post']));

        // Rich text of users without unfiltered_html is filtered.
        $sanitize = $meta['fixture_event']['fixture_note']['sanitize_callback'];
        self::assertIsCallable($sanitize);
        self::assertSame('<b>x</b>', $sanitize('<b>x</b><script>y</script>'));
        $GLOBALS['wp_app_framework_test_caps'] = ['unfiltered_html'];
        self::assertSame('<b>x</b><script>y</script>', $sanitize('<b>x</b><script>y</script>'));
        unset($GLOBALS['wp_app_framework_test_caps']);
    }

    public function testFlushesTheRewriteRulesWhenTheDeclarationsChange(): void
    {
        [$store] = Posts::store();
        $registrar = new PostTypeRegistrar($store, 'my_theme_rewrite', 'hash-1');

        $registrar->flushRewriteRules();
        $registrar->flushRewriteRules();
        self::assertSame(1, $GLOBALS['wp_app_framework_test_flushes'] ?? 0);
        self::assertSame('hash-1', get_option('my_theme_rewrite'));

        new PostTypeRegistrar($store, 'my_theme_rewrite', 'hash-2')->flushRewriteRules();
        self::assertSame(2, $GLOBALS['wp_app_framework_test_flushes']);

        // Switching to the theme or activating the plugin: the next request flushes again.
        $registrar->resetRewriteRules();
        new PostTypeRegistrar($store, 'my_theme_rewrite', 'hash-2')->flushRewriteRules();
        self::assertSame(3, $GLOBALS['wp_app_framework_test_flushes']);

        // Without public post types and taxonomies any more: one flush removes their rules.
        new PostTypeRegistrar($store, 'my_theme_rewrite', null)->flushRewriteRules();
        new PostTypeRegistrar($store, 'my_theme_rewrite', null)->flushRewriteRules();
        self::assertSame(4, $GLOBALS['wp_app_framework_test_flushes']);
        self::assertFalse(get_option('my_theme_rewrite'));
    }
}
