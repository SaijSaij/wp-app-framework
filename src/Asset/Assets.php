<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Asset;

use InvalidArgumentException;
use LogicException;
use WP_HTML_Tag_Processor;

/**
 * The static files of the instance's `assets/` directory: their URL, path,
 * an `<img>` element or an inline SVG. The Twig functions `asset_url()`,
 * `asset_image()` and `asset_svg()` call this service; inject it where PHP
 * needs a file, for example in a pattern or a mail.
 *
 * An asset is named by its path below `assets/`, e.g. `images/logo.svg`. A
 * higher layer replaces it with a file of the same name in its overrides
 * directory: a child theme in `overrides/{theme}/assets/`, a theme or child
 * theme in `overrides/{plugin}/assets/`. `@child/…` names a file of the
 * active child theme's own `assets/` directory, which needs no counterpart in
 * the theme; `@theme/…` or `@plugin/…` names the instance's own file and
 * ignores overrides.
 *
 * @phpstan-import-type AssetEntry from AssetCollector
 */
final class Assets
{
    /** Prefix of the files of the active child theme's own asset directory. */
    public const string CHILD_PREFIX = '@child/';

    /** @var array<string, string> Contents of inline SVGs by path, read once per request. */
    private array $svgs = [];

    /** @var array<string, bool> Whether an override directory exists, checked once per request. */
    private array $directories = [];

    /**
     * @param string                    $slug          Slug of the instance, for messages.
     * @param string                    $directory     The instance's asset directory.
     * @param string                    $uri           URL of the instance's asset directory.
     * @param array<string, AssetEntry> $assets        The instance's assets from the manifest, by name.
     * @param string                    $exactPrefix   Prefix that names the instance's own file, e.g. "@theme/".
     * @param list<array{string, string}> $overrides   Override directories with their URLs, highest layer first.
     * @param array{string, string}|null  $child       The active child theme's asset directory with its URL; null
     *                                                 without a child theme.
     */
    public function __construct(
        private readonly string $slug,
        private readonly string $directory,
        private readonly string $uri,
        private readonly array $assets,
        private readonly string $exactPrefix,
        private readonly array $overrides = [],
        private readonly ?array $child = null,
    ) {}

    /**
     * The URL of an asset, versioned with a hash of its content (or, for a file of a higher layer, its
     * modification time).
     */
    public function url(string $name): string
    {
        $asset = $this->resolve($name);

        return $asset['url'] . '?ver=' . rawurlencode($asset['version']);
    }

    /**
     * The path of an asset in the file system.
     */
    public function path(string $name): string
    {
        return $this->resolve($name)['path'];
    }

    /**
     * An `<img>` element for an image: `src`, `width` and `height` (so the page does not jump while it loads),
     * `alt`, `decoding="async"` and further attributes.
     *
     * @param string                          $alt        The alternative text; '' for a decorative image.
     * @param array<string, string|int|bool>  $attributes Further attributes; true adds one without value, false leaves
     *                                                    it out.
     */
    public function image(string $name, string $alt, array $attributes = []): string
    {
        $asset = $this->resolve($name);
        $html = '<img src="' . esc_url($asset['url'] . '?ver=' . rawurlencode($asset['version'])) . '"';
        if ($asset['width'] !== null && $asset['height'] !== null) {
            $html .= ' width="' . $asset['width'] . '" height="' . $asset['height'] . '"';
        }
        $html .= ' alt="' . esc_attr($alt) . '"';

        return $html . self::attributes(['decoding' => 'async', ...$attributes], ['src', 'alt']) . '>';
    }

    /**
     * The markup of an SVG image, to place it inline (for example icons styled with CSS), with further attributes
     * on its root element; classes are added to its own.
     *
     * @param array<string, string|int|bool> $attributes
     * @throws InvalidArgumentException If the asset is no SVG image or has no root element `<svg>`.
     */
    public function svg(string $name, array $attributes = []): string
    {
        if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'svg') {
            throw new InvalidArgumentException(sprintf('asset_svg() needs an SVG image, %s of %s is none.', $name, $this->slug));
        }
        $path = $this->resolve($name)['path'];
        $svg = $this->svgs[$path] ??= (string) preg_replace('#^\s*(<\?xml[^>]*\?>\s*)?(<!DOCTYPE[^>]*>\s*)?#i', '', (string) file_get_contents($path));

        $processor = new WP_HTML_Tag_Processor($svg);
        if (!$processor->next_tag() || $processor->get_tag() !== 'SVG') {
            throw new InvalidArgumentException(sprintf('The asset %s of %s (%s) has no root element <svg>.', $name, $this->slug, $path));
        }
        foreach ($attributes as $attribute => $value) {
            if ($attribute === 'class' && is_string($value)) {
                foreach (preg_split('/\s+/', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $class) {
                    $processor->add_class($class);
                }
            } elseif ($value === false) {
                $processor->remove_attribute($attribute);
            } else {
                $processor->set_attribute($attribute, $value === true ? true : (string) $value);
            }
        }

        return trim($processor->get_updated_html());
    }

    /**
     * @return array{path: string, url: string, version: string, width: int|null, height: int|null}
     * @throws InvalidArgumentException If the name is invalid or names no asset.
     */
    private function resolve(string $name): array
    {
        if (str_starts_with($name, self::CHILD_PREFIX)) {
            return $this->child(substr($name, strlen(self::CHILD_PREFIX)), $name);
        }
        $exact = str_starts_with($name, $this->exactPrefix);
        $relative = $exact ? substr($name, strlen($this->exactPrefix)) : $name;
        $this->checkName($relative, $name);
        $asset = $this->assets[$relative] ?? throw new InvalidArgumentException(sprintf(
            'The asset %s of %s does not exist: %s/%s is not in the manifest. Static assets are the files in assets/ except hidden files and script and style sources (%s).',
            $name,
            $this->slug,
            $this->directory,
            $relative,
            implode(', ', AssetCollector::SOURCE_EXTENSIONS),
        ));

        if (!$exact) {
            foreach ($this->overrides as [$directory, $uri]) {
                $this->directories[$directory] ??= is_dir($directory);
                if ($this->directories[$directory] && is_file($directory . '/' . $relative)) {
                    return self::file($directory . '/' . $relative, $uri . '/' . self::encode($relative));
                }
            }
        }

        return [
            'path' => $this->directory . '/' . $relative,
            'url' => $this->uri . '/' . self::encode($relative),
            'version' => $asset['hash'],
            'width' => $asset['width'],
            'height' => $asset['height'],
        ];
    }

    /**
     * @return array{path: string, url: string, version: string, width: int|null, height: int|null}
     */
    private function child(string $relative, string $name): array
    {
        $this->checkName($relative, $name);
        if ($this->child === null) {
            throw new LogicException(sprintf('The asset %s of %s names a file of the child theme, but no child theme is active.', $name, $this->slug));
        }
        [$directory, $uri] = $this->child;
        if (!is_file($directory . '/' . $relative)) {
            throw new InvalidArgumentException(sprintf('The asset %s of %s does not exist: %s/%s is missing.', $name, $this->slug, $directory, $relative));
        }

        return self::file($directory . '/' . $relative, $uri . '/' . self::encode($relative));
    }

    /**
     * A file of a higher layer, which no manifest lists.
     *
     * @return array{path: string, url: string, version: string, width: int|null, height: int|null}
     */
    private static function file(string $path, string $url): array
    {
        [$width, $height] = AssetCollector::size($path);

        return ['path' => $path, 'url' => $url, 'version' => (string) filemtime($path), 'width' => $width, 'height' => $height];
    }

    private function checkName(string $relative, string $name): void
    {
        if ($relative === '' || str_starts_with($relative, '/') || str_contains($relative, '\\') || preg_match('#(^|/)\.\.?(/|$)#', $relative) === 1
            || (str_starts_with($relative, '@'))) {
            throw new InvalidArgumentException(sprintf(
                'The asset name %s of %s is invalid: name a file by its path below assets/, e.g. images/logo.svg, optionally with %s or %s.',
                $name,
                $this->slug,
                self::CHILD_PREFIX,
                $this->exactPrefix,
            ));
        }
    }

    private static function encode(string $relative): string
    {
        return implode('/', array_map(rawurlencode(...), explode('/', $relative)));
    }

    /**
     * @param array<string, string|int|bool> $attributes
     * @param list<string>                   $reserved   Attributes the element already has.
     */
    private static function attributes(array $attributes, array $reserved): string
    {
        $html = '';
        foreach ($attributes as $name => $value) {
            if (in_array($name, $reserved, true) || preg_match('/^[a-zA-Z_:][a-zA-Z0-9_.:-]*$/', $name) !== 1) {
                throw new InvalidArgumentException(sprintf('The attribute %s cannot be set on an asset image.', json_encode($name)));
            }
            if ($value === false) {
                continue;
            }
            $html .= ' ' . $name . ($value === true ? '' : '="' . esc_attr((string) $value) . '"');
        }

        return $html;
    }
}
