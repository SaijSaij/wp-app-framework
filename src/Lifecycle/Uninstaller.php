<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Lifecycle;

use Closure;
use RuntimeException;
use Saij\WpAppFramework\Cron\CronRegistrar;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Filesystem\FileTree;
use Saij\WpAppFramework\Post\PostStore;
use Saij\WpAppFramework\Project\NamePrefix;
use Saij\WpAppFramework\Table\TableStore;
use WP_Error;

/**
 * Removes the data of a plugin instance when the plugin is uninstalled.
 *
 * Per site: the posts of its post types, the terms of its taxonomies, its meta keys (also on post types of WordPress
 * and other plugins), its settings options, its cron events and tasks, its tables with the state of their sync, and
 * its technical options – the rewrite hash, the versions of cache tags, cache entries and notices stored as transients.
 * Then once: its cache directory `wp-content/cache/{slug}`.
 *
 * Only names the manifest declares or that match the exact form the framework writes are removed: a pattern on the
 * namespace or slug alone would also hit another instance whose name starts the same (`dev_` and `dev_test_`).
 * Entries of a persistent object cache stay until they expire; without the versions of their tags they are never read.
 *
 * @internal
 */
#[NoService]
final readonly class Uninstaller
{
    /**
     * @param non-empty-string       $slug
     * @param list<non-empty-string> $settingsOptions The options of the settings groups.
     * @param non-empty-string       $rewriteOption   The option with the hash of the rewrite rules.
     * @param string                 $cacheDirectory  `wp-content/cache/{slug}`.
     */
    public function __construct(
        private string $slug,
        private PostStore $posts,
        private array $settingsOptions,
        private TableStore $tables,
        private CronRegistrar $cron,
        private string $rewriteOption,
        private string $cacheDirectory,
    ) {}

    /**
     * The steps for the current site by name, in their order; each may fail on its own.
     *
     * @return array<non-empty-string, Closure(): void>
     */
    public function siteSteps(): array
    {
        return [
            'posts' => $this->deletePosts(...),
            'terms' => $this->deleteTerms(...),
            'meta' => $this->deleteMeta(...),
            'settings' => $this->deleteSettings(...),
            'cron' => $this->cron->clear(...),
            'tables' => $this->tables->drop(...),
            'options' => $this->deleteOptions(...),
        ];
    }

    /**
     * Removes the cache directory of the instance, after the steps of all sites.
     */
    public function removeCache(): void
    {
        FileTree::remove($this->cacheDirectory);
    }

    /**
     * Deletes all posts of the post types of the instance in any status, with their meta, comments and revisions.
     */
    private function deletePosts(): void
    {
        $wpdb = TableStore::wpdb();
        $failed = [];
        foreach ($this->posts->getPosts() as $post) {
            if ($post['builtin']) {
                continue;
            }
            $ids = $wpdb->get_col($wpdb->prepare('SELECT `ID` FROM %i WHERE `post_type` = %s', $wpdb->posts, $post['name']));
            foreach ($ids as $id) {
                $deleted = wp_delete_post((int) $id, true);
                if ($deleted === false || $deleted === null) {
                    $failed[] = $post['name'] . ' ' . $id;
                }
            }
        }
        if ($failed !== []) {
            throw new RuntimeException(sprintf('Cannot delete the posts %s of %s.', implode(', ', $failed), $this->slug));
        }
    }

    /**
     * Deletes all terms of the taxonomies of the instance.
     */
    private function deleteTerms(): void
    {
        $wpdb = TableStore::wpdb();
        $failed = [];
        foreach ($this->posts->getTaxonomies() as $taxonomy) {
            if ($taxonomy['builtin']) {
                continue;
            }
            $ids = $wpdb->get_col($wpdb->prepare('SELECT `term_id` FROM %i WHERE `taxonomy` = %s', $wpdb->term_taxonomy, $taxonomy['name']));
            foreach ($ids as $id) {
                $deleted = wp_delete_term((int) $id, $taxonomy['name']);
                if ($deleted !== true) {
                    $failed[] = sprintf('%s %s%s', $taxonomy['name'], (string) $id, $deleted instanceof WP_Error ? ' (' . $deleted->get_error_message() . ')' : '');
                }
            }
        }
        if ($failed !== []) {
            throw new RuntimeException(sprintf('Cannot delete the terms %s of %s.', implode(', ', $failed), $this->slug));
        }
    }

    /**
     * Deletes the meta keys of the meta classes from all posts.
     */
    private function deleteMeta(): void
    {
        foreach ($this->posts->getMeta() as $meta) {
            foreach ($meta['fields'] as $field) {
                delete_post_meta_by_key($field['key']);
            }
        }
    }

    private function deleteSettings(): void
    {
        foreach ($this->settingsOptions as $option) {
            delete_option($option);
        }
    }

    /**
     * Deletes the rewrite hash, the versions of the cache tags, the cache entries and meta box notices stored as
     * transients, and the rewrite rules of WordPress, which still contain the post types of the instance.
     */
    private function deleteOptions(): void
    {
        delete_option($this->rewriteOption);
        $prefix = NamePrefix::template($this->slug);
        $wpdb = TableStore::wpdb();
        $names = $wpdb->get_col($wpdb->prepare(
            'SELECT `option_name` FROM %i WHERE `option_name` LIKE %s OR `option_name` LIKE %s',
            $wpdb->options,
            $wpdb->esc_like($prefix . 'cache_tag_') . '%',
            $wpdb->esc_like('_transient_' . $prefix) . '%',
        ));
        $quoted = preg_quote($prefix, '~');
        foreach ($names as $name) {
            $name = (string) $name;
            if (preg_match('~^' . $quoted . 'cache_tag_[0-9a-f]{32}$~', $name) === 1) {
                delete_option($name);
            } elseif (preg_match('~^_transient_(' . $quoted . '(?:cache_[0-9a-f]{32}|meta_errors_\d+_\d+))$~', $name, $match) === 1) {
                delete_transient($match[1]);
            }
        }
        delete_option('rewrite_rules');
    }
}
