<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Post;

use PHPUnit\Framework\TestCase;
use Saij\WpAppFramework\I18n\Translator;
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
        unset($GLOBALS['wp_app_framework_test_option_misses'], $GLOBALS['wp_app_framework_test_autoload']);
    }

    public function testRegistersPostTypesTaxonomiesAndOneMetaKeyPerField(): void
    {
        [$store, $manifest] = Posts::store();
        new PostTypeRegistrar($store, 'my_theme_rewrite', $manifest['rewrite'])->register();

        $types = Recorded::postTypes();
        self::assertSame(['fixture_event', 'fixture_venue'], array_keys($types), 'Post and page are registered by WordPress.');
        self::assertSame(['name' => 'Events'], array_intersect_key((array) $types['fixture_event']['labels'], ['name' => true]));
        // WordPress registers its own taxonomies.
        self::assertSame(['fixture_genre'], array_keys(Recorded::taxonomies()));
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

    public function testTranslatesTheLabelsWithTheTextDomain(): void
    {
        [$store, $manifest] = Posts::store();
        $GLOBALS['wp_app_framework_test_translations'] = ['my-theme' => ['Events' => 'Veranstaltungen', 'Genres' => 'Genres (de)']];
        try {
            new PostTypeRegistrar($store, 'my_theme_rewrite', $manifest['rewrite'], new Translator('my-theme'))->register();
        } finally {
            unset($GLOBALS['wp_app_framework_test_translations']);
        }

        self::assertSame('Veranstaltungen', ((array) Recorded::postTypes()['fixture_event']['labels'])['name']);
        self::assertSame('Genres (de)', ((array) Recorded::taxonomies()['fixture_genre'][1]['labels'])['name']);
    }

    public function testFlushesTheRewriteRulesWhenTheDeclarationsChange(): void
    {
        [$store] = Posts::store();
        $registrar = new PostTypeRegistrar($store, 'my_theme_rewrite', 'hash-1');

        $registrar->flushRewriteRules();
        $registrar->flushRewriteRules();
        self::assertSame(1, $GLOBALS['wp_app_framework_test_flushes'] ?? 0);
        self::assertSame('hash-1', get_option('my_theme_rewrite'));
        self::assertArrayHasKey('my_theme_rewrite', wp_load_alloptions(), 'The hash is autoloaded, so reading it costs no query.');

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

    public function testWithoutDeclarationsOnlyTheAutoloadedOptionsAreChecked(): void
    {
        [$store] = Posts::store();

        // get_option() would query the database for the missing option on every request.
        new PostTypeRegistrar($store, 'my_theme_rewrite', null)->flushRewriteRules();
        self::assertSame(0, $GLOBALS['wp_app_framework_test_flushes'] ?? 0);
        self::assertSame([], $GLOBALS['wp_app_framework_test_option_misses'] ?? [], 'No missing option was read.');

        // A hash that is not autoloaded is not looked at either.
        add_option('my_theme_rewrite', 'hash-1', '', false);
        new PostTypeRegistrar($store, 'my_theme_rewrite', null)->flushRewriteRules();
        self::assertSame(0, $GLOBALS['wp_app_framework_test_flushes'] ?? 0);
    }
}
