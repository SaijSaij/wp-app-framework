<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Post;

use PHPUnit\Framework\TestCase;
use Saij\WpAppFramework\Post\MetaBoxes;
use Saij\WpAppFramework\Post\PostTypeRegistrar;
use Saij\WpAppFramework\Tests\Support\Files;
use Saij\WpAppFramework\Tests\Support\Posts;
use Saij\WpAppFramework\Tests\Support\Recorded;

/**
 * Meta boxes of the classic edit screen.
 */
final class MetaBoxesTest extends TestCase
{
    private string $build;

    protected function setUp(): void
    {
        $this->build = sys_get_temp_dir() . '/wpaf-meta-boxes-' . bin2hex(random_bytes(4));
        mkdir($this->build . '/vendor/wp-app-framework', 0777, true);
    }

    protected function tearDown(): void
    {
        Files::remove($this->build);
        Posts::reset();
        unset($GLOBALS['wp_app_framework_test_caps'], $GLOBALS['wp_app_framework_test_scripts'], $GLOBALS['wp_app_framework_test_styles'], $GLOBALS['wp_app_framework_test_media'], $GLOBALS['wp_app_framework_test_editor']);
    }

    private function boxes(): MetaBoxes
    {
        [$store] = Posts::store();
        new PostTypeRegistrar($store, 'my_theme_rewrite', null)->register();

        return new MetaBoxes('my-theme', $store, $this->build, 'https://example.test/build');
    }

    public function testAddsABackCompatBoxPerMetaClassAndRemovesCustomFields(): void
    {
        $this->boxes()->add('fixture_event');

        $boxes = Recorded::metaBoxes();
        self::assertSame(['my-theme-meta-details'], array_keys($boxes));
        self::assertSame(['Event details', 'fixture_event', ['__back_compat_meta_box' => true]], [$boxes['my-theme-meta-details']['title'], $boxes['my-theme-meta-details']['screen'], $boxes['my-theme-meta-details']['args']]);
        self::assertSame(['postcustom fixture_event normal'], $GLOBALS['wp_app_framework_test_removed_meta_boxes']);

        unset($GLOBALS['wp_app_framework_test_meta_boxes'], $GLOBALS['wp_app_framework_test_removed_meta_boxes']);
        $this->boxes()->add('fixture_venue');
        self::assertArrayNotHasKey('wp_app_framework_test_meta_boxes', $GLOBALS, 'No meta, no box.');
        self::assertArrayNotHasKey('wp_app_framework_test_removed_meta_boxes', $GLOBALS);
    }

    public function testRendersTheStoredValuesIntoTheHiddenField(): void
    {
        $post = Posts::add(10, 'fixture_event');
        Posts::addMeta(10, ['fixture_capacity' => '9']);
        $this->boxes()->add('fixture_event');
        $render = Recorded::metaBoxCallback('my-theme-meta-details');

        ob_start();
        $render($post);
        $html = (string) ob_get_clean();

        self::assertStringContainsString('<input type="hidden" name="my-theme-meta-nonce" value="nonce-my-theme-meta">', $html);
        self::assertStringContainsString('<div class="wp-app-framework-meta-box" data-instance="my-theme" data-meta="details"></div>', $html);
        self::assertMatchesRegularExpression('~name="wp-app-framework-meta\[my-theme\]\[details\]" value="[^"]*&quot;capacity&quot;:9,~', $html);
    }

    public function testSavesValidValuesAndReportsInvalidOnes(): void
    {
        $post = Posts::add(10, 'fixture_event');
        Posts::addMeta(10, ['fixture_capacity' => 1, 'fixture_venue' => 20]);
        $GLOBALS['wp_app_framework_test_caps'] = ['edit_post:10'];
        $_POST['my-theme-meta-nonce'] = 'nonce-my-theme-meta';
        $_POST['wp-app-framework-meta'] = ['my-theme' => ['details' => wp_slash((string) json_encode([
            'capacity' => 30,
            'density' => 'huge',
            'venue' => null,
            'note' => 'It\'s <b>"bold"</b><script>x</script>',
        ]))]];
        $boxes = $this->boxes();

        $boxes->save(10, $post);

        $meta = Posts::meta(10);
        self::assertSame(30, $meta['fixture_capacity']);
        self::assertArrayNotHasKey('fixture_density', $meta, 'Invalid: not saved.');
        self::assertArrayNotHasKey('fixture_venue', $meta, 'null deletes a reference.');
        self::assertSame('It\'s <b>"bold"</b>', $meta['fixture_note']);

        $GLOBALS['post'] = $post;
        Posts::screen('fixture_event');
        ob_start();
        $boxes->notices();
        self::assertSame(
            '<div class="notice notice-error wp-app-framework-meta-errors"><p>These values were not saved:</p><ul><li>Event details: density is not one of compact, normal.</li></ul></div>',
            ob_get_clean(),
        );
        ob_start();
        $boxes->notices();
        self::assertSame('', ob_get_clean(), 'Shown once.');
        unset($GLOBALS['post']);
    }

    public function testReadsNoErrorsOnScreensOfPostTypesWithoutMeta(): void
    {
        $GLOBALS['post'] = Posts::add(11, 'nav_menu_item');
        Posts::screen('nav_menu_item');
        unset($GLOBALS['wp_app_framework_test_transient_reads']);

        ob_start();
        $this->boxes()->notices();
        self::assertSame('', ob_get_clean());
        self::assertSame([], $GLOBALS['wp_app_framework_test_transient_reads'] ?? [], 'No transient is read.');
        unset($GLOBALS['post']);
    }

    public function testIgnoresRequestsWithoutNonceOrCapability(): void
    {
        $post = Posts::add(10, 'fixture_event');
        $_POST['wp-app-framework-meta'] = ['my-theme' => ['details' => '{"capacity":3}']];
        $boxes = $this->boxes();

        $boxes->save(10, $post);
        $_POST['my-theme-meta-nonce'] = 'nonce-my-theme-meta';
        $boxes->save(10, $post);

        self::assertSame([], Posts::meta(10));
    }

    public function testLoadsTheFormScriptOnTheClassicScreenOnly(): void
    {
        file_put_contents($this->build . '/vendor/wp-app-framework/meta-box.asset.php', "<?php return ['dependencies' => ['wp-components'], 'version' => 'v1'];");
        file_put_contents($this->build . '/vendor/wp-app-framework/meta-box.css', '');
        file_put_contents($this->build . '/vendor/wp-app-framework/meta-box-rtl.css', '');
        $boxes = $this->boxes();

        Posts::screen('fixture_event', true);
        $boxes->enqueue('post.php');
        self::assertSame([], Recorded::scripts(), 'The block editor shows panels.');

        Posts::screen('fixture_event');
        $boxes->enqueue('post.php');
        $script = Recorded::scripts()['my-theme-wp-app-framework-meta-box'];
        self::assertSame('https://example.test/build/vendor/wp-app-framework/meta-box.js', $script['src']);
        self::assertStringContainsString('window.wpAppFramework["my-theme"].metaBoxes = {"details":{"name":"details","title":"Event details","fields":{"capacity":{"key":"fixture_capacity"', $script['before'][0] ?? '');
        // The image and rich text controls of the form.
        self::assertSame(1, $GLOBALS['wp_app_framework_test_media'] ?? 0);
        self::assertSame(1, $GLOBALS['wp_app_framework_test_editor'] ?? 0);
    }
}
