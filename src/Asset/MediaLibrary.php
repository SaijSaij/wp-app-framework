<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Asset;

use InvalidArgumentException;
use RuntimeException;

/**
 * Imports static assets of the instance into the media library, for content
 * that needs attachments: an `#[Image]` field takes the ID of an attachment,
 * not a file of the theme. Call it where content is created, for example in a
 * method with `#[OnActivation]` or in a WP-CLI command.
 *
 * The import is idempotent per site: an asset already imported with the same
 * content returns its attachment. A changed file becomes a new attachment; the
 * old one stays, because content may use it. Attachments stay in the media
 * library when the theme or plugin is removed.
 *
 * @api Services
 */
final readonly class MediaLibrary
{
    /**
     * Meta key of an imported attachment: slug, name and hash of the asset.
     *
     * @internal
     */
    public const string META_KEY = '_wp_app_framework_asset';

    /**
     * @param string $slug Slug of the instance; part of the key of each imported asset.
     *
     * @internal Created by the framework; inject the service.
     */
    public function __construct(
        private string $slug,
        private Assets $assets,
    ) {}

    /**
     * The attachment of an asset of the instance, imported on the first call.
     *
     * @param string $name The asset, e.g. images/hero.jpg; files of overrides and of the child theme cannot be imported.
     * @return int The ID of the attachment.
     * @throws InvalidArgumentException If the name names no asset of the instance.
     * @throws RuntimeException         If WordPress cannot store the file or create the attachment.
     */
    public function import(string $name): int
    {
        [$path, $hash, $relative] = $this->assets->ownFile($name);
        $key = $this->slug . ':' . $relative . ':' . $hash;

        $existing = get_posts([
            'post_type' => 'attachment',
            'post_status' => 'any',
            'meta_key' => self::META_KEY,
            'meta_value' => $key,
            'fields' => 'ids',
            'numberposts' => 1,
            'suppress_filters' => false,
        ]);
        if ($existing !== [] && is_int($existing[0])) {
            return $existing[0];
        }

        $contents = file_get_contents($path);
        $file = basename($path);
        if ($file === '') {
            throw new InvalidArgumentException(sprintf('The asset %s of %s has no file name.', $name, $this->slug));
        }
        $upload = wp_upload_bits($file, null, $contents === false ? '' : $contents);
        if ($contents === false || $upload['error'] !== false) {
            throw new RuntimeException(sprintf('The asset %s of %s cannot be stored in the uploads: %s', $name, $this->slug, $contents === false ? 'the file cannot be read' : (string) $upload['error']));
        }

        $type = wp_check_filetype($upload['file']);
        $id = wp_insert_attachment([
            'post_mime_type' => is_string($type['type']) ? $type['type'] : 'application/octet-stream',
            'post_title' => pathinfo($name, PATHINFO_FILENAME),
            'post_content' => '',
            'post_status' => 'inherit',
        ], $upload['file'], 0, true);
        if (is_wp_error($id)) {
            throw new RuntimeException(sprintf('The attachment of the asset %s of %s cannot be created: %s', $name, $this->slug, $id->get_error_message()));
        }

        if (!function_exists('wp_generate_attachment_metadata')) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }
        wp_update_attachment_metadata($id, wp_generate_attachment_metadata($id, $upload['file']));
        update_post_meta($id, self::META_KEY, $key);

        return $id;
    }
}
