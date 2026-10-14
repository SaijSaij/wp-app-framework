<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Asset;

use Saij\WpAppFramework\Twig\TemplateFunction;

/**
 * Twig functions for the static files in `assets/` (see Assets).
 */
final readonly class AssetTemplateFunctions
{
    public function __construct(
        private Assets $assets,
    ) {}

    /**
     * The URL of an asset, versioned with a hash of its content; escape it like any URL (`|e('html_attr')` is not
     * needed in a quoted attribute).
     */
    #[TemplateFunction('asset_url')]
    public function url(string $name): string
    {
        return $this->assets->url($name);
    }

    /**
     * An `<img>` element for an image asset with its width and height; with `widths`, `sizes` and `formats` a
     * `srcset` of variants or a `<picture>` (see Assets::image()).
     *
     * @param array<string, string|int|bool|list<int>|list<string>> $attributes
     */
    #[TemplateFunction('asset_image', html: true)]
    public function image(string $name, string $alt, array $attributes = []): string
    {
        return $this->assets->image($name, $alt, $attributes);
    }

    /**
     * An SVG asset inline, with further attributes on its root element.
     *
     * @param array<string, string|int|bool> $attributes
     */
    #[TemplateFunction('asset_svg', html: true)]
    public function svg(string $name, array $attributes = []): string
    {
        return $this->assets->svg($name, $attributes);
    }
}
