<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Post;

use Saij\WpAppFramework\Asset\BuildAssets;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\I18n\FrameworkTranslations;
use Saij\WpAppFramework\I18n\Translator;
use Saij\WpAppFramework\Value\FormAssets;
use WP_Post;
use WP_Screen;

/**
 * Meta boxes of the meta classes on the classic edit screen: for post types
 * without the block editor (data types) and posts edited in the classic editor.
 * The block editor shows panels instead (editor runtime) and hides these boxes,
 * because they are marked `__back_compat_meta_box`.
 *
 * Each box is a form of the meta class's controls; the script writes its
 * values as JSON into a hidden field, saved with the post form. Invalid values
 * are not saved and shown as an admin notice after the redirect. On the screens
 * of post types with meta, the "Custom Fields" box of WordPress is removed: it
 * would edit the meta keys as raw strings, past the schema.
 *
 * @internal
 *
 * @phpstan-import-type MetaEntry from PostTypeCollector
 */
#[NoService]
final class MetaBoxes
{
    public const string SCRIPT = 'vendor/wp-app-framework/meta-box';

    private const string FIELD = 'wp-app-framework-meta';

    private readonly BuildAssets $assets;

    /** @var array<string, list<class-string>> Meta classes by post type name. */
    private array $metaByType = [];

    public function __construct(
        private readonly string $slug,
        private readonly PostStore $store,
        string $buildDirectory,
        string $buildUri,
        private readonly ?Translator $translator = null,
    ) {
        $this->assets = new BuildAssets($slug, $buildDirectory, $buildUri);
        $posts = $store->getPosts();
        foreach ($store->getMeta() as $class => $meta) {
            foreach ($meta['postTypes'] as $postClass) {
                $this->metaByType[$posts[$postClass]['name']][] = $class;
            }
        }
    }

    /**
     * @return non-empty-string
     */
    public static function handle(string $slug): string
    {
        return $slug . '-wp-app-framework-meta-box';
    }

    /**
     * Adds the boxes and removes the "Custom Fields" box (`add_meta_boxes`, also in the block editor).
     */
    public function add(string $postType): void
    {
        $classes = $this->metaByType[$postType] ?? [];
        if ($classes === []) {
            return;
        }
        remove_meta_box('postcustom', $postType, 'normal');
        foreach ($classes as $class) {
            $meta = $this->store->getMeta()[$class];
            add_meta_box(
                $this->boxId($meta['name']),
                $this->translator?->translate($meta['title']) ?? $meta['title'],
                fn(WP_Post $post): null => $this->render($post, $class),
                $postType,
                'normal',
                'default',
                ['__back_compat_meta_box' => true],
            );
        }
    }

    /**
     * @param class-string $class
     */
    private function render(WP_Post $post, string $class): null
    {
        $meta = $this->store->getMeta()[$class];
        [, $raw] = $this->store->loadMeta($class, $post->post_status === 'auto-draft' ? null : $post->ID);
        if ($this->metaByType[$post->post_type][0] === $class) {
            wp_nonce_field($this->nonceAction(), $this->nonceName());
        }
        printf(
            '<div class="wp-app-framework-meta-box" data-instance="%1$s" data-meta="%2$s"></div><input type="hidden" name="%3$s[%1$s][%2$s]" value="%4$s">',
            esc_attr($this->slug),
            esc_attr($meta['name']),
            esc_attr(self::FIELD),
            esc_attr((string) wp_json_encode((object) $raw)),
        );

        return null;
    }

    /**
     * Saves the values of the boxes (`save_post`); invalid values are left out and reported.
     */
    public function save(int $postId, WP_Post $post): void
    {
        $nonce = $_POST[$this->nonceName()] ?? null;
        if (!is_string($nonce) || wp_verify_nonce(wp_unslash($nonce), $this->nonceAction()) === false) {
            return;
        }
        if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($postId) !== false || !current_user_can('edit_post', $postId)) {
            return;
        }
        $all = $_POST[self::FIELD] ?? null;
        $posted = is_array($all) ? $all[$this->slug] ?? null : null;
        if (!is_array($posted)) {
            return;
        }

        $errors = [];
        foreach ($this->metaByType[$post->post_type] ?? [] as $class) {
            $meta = $this->store->getMeta()[$class];
            $json = $posted[$meta['name']] ?? null;
            $values = is_string($json) ? json_decode(wp_unslash($json), true) : null;
            if (!is_array($values)) {
                continue;
            }
            foreach ($meta['fields'] as $name => $field) {
                if (!array_key_exists($name, $values)) {
                    continue;
                }
                $value = $values[$name];
                if ($value === null && $field['id']) {
                    delete_post_meta($postId, $field['key']);
                    continue;
                }
                $valid = rest_validate_value_from_schema($value, $field['schema'], ($this->translator?->translate($meta['title']) ?? $meta['title']) . ': ' . $name);
                if (is_wp_error($valid)) {
                    $errors[] = $valid->get_error_message();
                    continue;
                }
                // update_metadata() unslashes the value; the registered sanitize callback filters rich text.
                update_post_meta($postId, $field['key'], wp_slash(rest_sanitize_value_from_schema($value, $field['schema'], $field['key'])));
            }
        }
        if ($errors !== []) {
            set_transient($this->errorKey($postId), $errors, 5 * MINUTE_IN_SECONDS);
        }
    }

    /**
     * Shows the errors of the last save on the edit screen of the post (`admin_notices`). Only post types with meta
     * classes have them: reading the transient costs a database query without a persistent object cache.
     */
    public function notices(): void
    {
        $post = get_post();
        $screen = get_current_screen();
        if (!$post instanceof WP_Post || !$screen instanceof WP_Screen || $screen->base !== 'post' || !isset($this->metaByType[$post->post_type])) {
            return;
        }
        $errors = get_transient($this->errorKey($post->ID));
        if (!is_array($errors)) {
            return;
        }
        delete_transient($this->errorKey($post->ID));
        printf(
            '<div class="notice notice-error wp-app-framework-meta-errors"><p>%s</p><ul>%s</ul></div>',
            esc_html__('These values were not saved:', 'wp-app-framework'),
            implode('', array_map(static fn(mixed $error): string => '<li>' . esc_html(is_string($error) ? $error : '') . '</li>', $errors)),
        );
    }

    /**
     * Loads the form script on the classic edit screen of a post type with meta (`admin_enqueue_scripts`).
     */
    public function enqueue(string $hookSuffix): void
    {
        $screen = get_current_screen();
        if (!in_array($hookSuffix, ['post.php', 'post-new.php'], true) || !$screen instanceof WP_Screen || $screen->is_block_editor()) {
            return;
        }
        $classes = $this->metaByType[$screen->post_type] ?? [];
        if ($classes === []) {
            return;
        }

        $handle = self::handle($this->slug);
        $version = $this->assets->registerScript($handle, self::SCRIPT, ['in_footer' => true]);
        wp_add_inline_script($handle, $this->configScript($classes), 'before');
        FrameworkTranslations::addTo($handle);
        wp_enqueue_script($handle);
        $this->assets->registerStyle($handle, self::SCRIPT, ['wp-components'], $version);
        wp_enqueue_style($handle);
        FormAssets::enqueue(array_map(fn(string $class): array => $this->store->getMeta()[$class]['controls'], $classes), $handle, $this->slug);
    }

    /**
     * The forms of the boxes in the JS global `wpAppFramework[slug].metaBoxes`, by meta name.
     *
     * @param list<class-string> $classes
     */
    public function configScript(array $classes): string
    {
        $boxes = [];
        foreach ($classes as $class) {
            $meta = $this->store->getMeta()[$class];
            $boxes[$meta['name']] = self::form($meta, $this->translator);
        }

        return sprintf(
            'window.wpAppFramework = window.wpAppFramework || {}; window.wpAppFramework[%1$s] = window.wpAppFramework[%1$s] || {}; window.wpAppFramework[%1$s].metaBoxes = %2$s;',
            wp_json_encode($this->slug),
            wp_json_encode((object) $boxes, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP),
        );
    }

    /**
     * The form of a meta class for the scripts: schema, default and ID flag of each field, and the controls; title and
     * labels translated with the text domain of the instance.
     *
     * @param MetaEntry $meta
     * @return array<string, mixed>
     */
    public static function form(array $meta, ?Translator $translator = null): array
    {
        $fields = [];
        foreach ($meta['fields'] as $name => $field) {
            $fields[$name] = ['key' => $field['key'], 'schema' => $field['schema'], 'default' => $field['default'], 'id' => $field['id']];
        }

        return [
            'name' => $meta['name'],
            'title' => $translator?->translate($meta['title']) ?? $meta['title'],
            'fields' => (object) $fields,
            'controls' => (object) ($translator?->translateLabels($meta['controls']) ?? $meta['controls']),
        ];
    }

    private function boxId(string $name): string
    {
        return $this->slug . '-meta-' . $name;
    }

    private function nonceName(): string
    {
        return $this->slug . '-meta-nonce';
    }

    private function nonceAction(): string
    {
        return $this->slug . '-meta';
    }

    private function errorKey(int $postId): string
    {
        return sprintf('%s_meta_errors_%d_%d', str_replace('-', '_', $this->slug), get_current_user_id(), $postId);
    }
}
