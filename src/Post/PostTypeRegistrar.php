<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Post;

use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Value\HtmlFilter;
use Saij\WpAppFramework\Value\Identifiers;

/**
 * Registers the post types, taxonomies and meta fields of an instance on `init`
 * and keeps the rewrite rules up to date.
 *
 * WordPress builds the rewrite rules only when they are flushed, so a new post
 * type or a changed slug has no URL until then. The registrar stores a hash of
 * what affects the rules (from the build) in the option `{slug}_rewrite` and
 * flushes the rules at the end of `init` when the hash differs. Switching to
 * the theme and activating the plugin delete the option: another theme or
 * plugin may have flushed the rules in between without these post types.
 *
 * The check costs no database query: the option is autoloaded, and without
 * rewrite-relevant declarations only the autoloaded options are looked at –
 * get_option() would query the database for a missing option on every request
 * without a persistent object cache. The option exists then only if the
 * declarations were removed; one flush removes their rules.
 *
 * @internal
 */
#[NoService]
final readonly class PostTypeRegistrar
{
    /**
     * @param non-empty-string      $option  Option holding the hash of the rewrite rules last flushed for.
     * @param non-empty-string|null $rewrite Hash of the rewrite-relevant declarations; null without public types.
     */
    public function __construct(
        private PostStore $store,
        private string $option,
        private ?string $rewrite,
    ) {}

    public function register(): void
    {
        $posts = $this->store->getPosts();
        foreach ($posts as $post) {
            if (!$post['builtin'] && $post['args'] !== null) {
                register_post_type($post['name'], $post['args']);
            }
        }
        foreach ($this->store->getTaxonomies() as $taxonomy) {
            if (!$taxonomy['builtin'] && $taxonomy['args'] !== null) {
                register_taxonomy($taxonomy['name'], $taxonomy['postTypes'], $taxonomy['args']);
            }
        }
        foreach ($this->store->getMeta() as $meta) {
            foreach ($meta['postTypes'] as $postClass) {
                $type = $posts[$postClass]['name'];
                foreach ($meta['fields'] as $field) {
                    $richText = $field['richText'];
                    $identifiers = $field['identifiers'];
                    register_post_meta($type, $field['key'], [
                        'type' => $field['type'],
                        'single' => true,
                        'default' => $field['default'],
                        'show_in_rest' => ['schema' => $field['schema']],
                        // Like block attributes (filter_block_kses()): HTML of users without unfiltered_html is
                        // filtered. List items get missing and duplicate identifiers (#[Identifier]).
                        'sanitize_callback' => static fn(mixed $value): mixed => Identifiers::fill(HtmlFilter::filter($value, $richText), $identifiers),
                        // register_meta() requires revision support of the type for revisioned meta (meta.php).
                        'revisions_enabled' => post_type_supports($type, 'revisions'),
                    ]);
                }
            }
        }
    }

    /**
     * Flushes the rewrite rules if the declarations changed since the last flush; runs at the end of `init`, after
     * all post types are registered.
     */
    public function flushRewriteRules(): void
    {
        if ($this->rewrite === null && !array_key_exists($this->option, wp_load_alloptions())) {
            return;
        }
        $stored = get_option($this->option);
        if (($stored === false ? null : $stored) === $this->rewrite) {
            return;
        }
        flush_rewrite_rules(false);
        if ($this->rewrite === null) {
            delete_option($this->option);
        } else {
            update_option($this->option, $this->rewrite, true);
        }
    }

    /**
     * Makes the next request flush the rewrite rules (theme switched, plugin activated).
     */
    public function resetRewriteRules(): void
    {
        delete_option($this->option);
    }
}
