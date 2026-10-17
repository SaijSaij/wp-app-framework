<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Cache;

use WebCodingCat\WpAppFramework\DependencyInjection\NoService;
use WP_Post;
use WP_Term;

/**
 * Invalidates the framework's tags on the WordPress actions that signal a change:
 *
 * - PostTypeTag: clean_post_cache (WordPress calls clean_post_cache() when a post
 *   is inserted, updated, trashed, published or deleted) and the actions of
 *   post meta, except for the edit lock of the block editor.
 * - TaxonomyTag: clean_term_cache (terms created, changed or deleted),
 *   set_object_terms and delete_term_relationships (assignments) and the actions
 *   of term meta.
 * - SettingsTag: the actions of the group's option.
 *
 * Only tags that were read before get a new version (TagVersions).
 *
 * @internal
 */
#[NoService]
final readonly class TagHooks
{
    /** Post meta that changes without changing the post: the lock while a user edits it. */
    private const array IGNORED_POST_META = ['_edit_lock'];

    /**
     * @param array<string, string> $settingsOptions Group name by option of the settings groups.
     */
    public function __construct(
        private TagVersions $versions,
        private array $settingsOptions,
    ) {}

    public function register(): void
    {
        add_action('clean_post_cache', function (int $postId, WP_Post $post): void {
            $this->invalidate('post_type:' . $post->post_type);
        }, 10, 2);
        foreach (['added', 'updated', 'deleted'] as $change) {
            add_action($change . '_post_meta', function (mixed $metaId, int $postId, string $metaKey): void {
                $postType = in_array($metaKey, self::IGNORED_POST_META, true) ? false : get_post_type($postId);
                if (is_string($postType)) {
                    $this->invalidate('post_type:' . $postType);
                }
            }, 10, 3);
            add_action($change . '_term_meta', function (mixed $metaId, int $termId): void {
                $term = get_term($termId);
                if ($term instanceof WP_Term) {
                    $this->invalidate('taxonomy:' . $term->taxonomy);
                }
            }, 10, 2);
        }

        add_action('clean_term_cache', function (mixed $ids, string $taxonomy): void {
            $this->invalidate('taxonomy:' . $taxonomy);
        }, 10, 2);
        add_action('set_object_terms', function (int $objectId, mixed $terms, mixed $termTaxonomyIds, string $taxonomy): void {
            $this->invalidate('taxonomy:' . $taxonomy);
        }, 10, 4);
        add_action('delete_term_relationships', function (int $objectId, mixed $termTaxonomyIds, string $taxonomy): void {
            $this->invalidate('taxonomy:' . $taxonomy);
        }, 10, 3);

        foreach ($this->settingsOptions as $option => $group) {
            $invalidate = function () use ($group): void {
                $this->invalidate('settings:' . $group);
            };
            // The first save adds the option and fires only add_option_{option} (update_option() → add_option()).
            add_action('add_option_' . $option, $invalidate);
            add_action('update_option_' . $option, $invalidate);
            add_action('delete_option_' . $option, $invalidate);
        }
    }

    private function invalidate(string $tag): void
    {
        if ($tag !== '') {
            $this->versions->invalidate($tag);
        }
    }
}
