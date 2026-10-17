<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Asset;

use WebCodingCat\WpAppFramework\Twig\TemplateFunction;

/**
 * Twig functions for the static files in `assets/` (see Assets).
 */
final readonly class AssetTemplateFunctions
{
    public function __construct(
        private Assets $assets,
    ) {}

    /**
     * The URL of a static asset of the theme or plugin, for a link, a video or any other file. The URL carries
     * `?ver=` with a hash of the file's content (for a file of an override or of `@child/`, its modification time),
     * so browsers load a changed file anew. The result is text, escaped by Twig like any value. An unknown or
     * invalid name is an error.
     *
     * ```twig
     * <a href="{{ asset_url('docs/price-list.pdf') }}">Price list</a>
     * ```
     *
     * @param string $name Path of the asset below `assets/`, e.g. `images/logo.svg`; it may start with `@child/` or
     *                     with the instance's own prefix (`@theme/` or `@plugin/`).
     */
    #[TemplateFunction('asset_url')]
    public function url(string $name): string
    {
        return $this->assets->url($name);
    }

    /**
     * An `img` element for an image asset with `src`, `width` and `height` (if known, so the page does not jump
     * while it loads), `alt`, `decoding="async"` and the given attributes. With `widths` and `sizes` the element
     * gets a `srcset` of smaller versions; with `formats` it is wrapped in a `picture` element with a `source` per
     * format. The build creates these variants from the calls in the templates, so the name, `widths` and `formats`
     * must be literals there. The output is HTML and is printed unescaped. An unknown or invalid name, or variants
     * the build did not create, are errors.
     *
     * ```twig
     * {{ asset_image('images/hero.jpg', 'Our team', {
     *     widths: [640, 1280, 1920],
     *     sizes: '(min-width: 1200px) 1200px, 100vw',
     *     formats: ['avif', 'webp'],
     *     class: 'w-full h-auto',
     * }) }}
     * ```
     *
     * @param string                                                $name       Path of the asset below `assets/`, e.g.
     *                                                                          `images/logo.svg`; it may start with
     *                                                                          `@child/` or with the instance's own
     *                                                                          prefix (`@theme/` or `@plugin/`).
     * @param string                                                $alt        The alternative text; `''` for a
     *                                                                          decorative image.
     * @param array<string, string|int|bool|list<int>|list<string>> $attributes Further attributes; `true` adds one
     *                                                                          without value, `false` leaves it out.
     *                                                                          `src`, `srcset` and `alt` cannot be
     *                                                                          set. `widths` (list of pixel widths,
     *                                                                          needs `sizes`) and `formats` (`avif`,
     *                                                                          `webp`, `jpeg`, `png`) ask for variants.
     */
    #[TemplateFunction('asset_image', html: true)]
    public function image(string $name, string $alt, array $attributes = []): string
    {
        return $this->assets->image($name, $alt, $attributes);
    }

    /**
     * The markup of an SVG asset, to place it inline, e.g. an icon styled with CSS. The XML declaration and doctype
     * are removed and the given attributes are set on the root `svg` element. The output is HTML and is printed
     * unescaped. An unknown or invalid name, a file that is no `.svg` or has no root element `svg` are errors.
     *
     * ```twig
     * {{ asset_svg('icons/menu.svg', { class: 'size-6 fill-current', 'aria-hidden': 'true' }) }}
     * ```
     *
     * @param string                         $name       Path of the SVG asset below `assets/`, e.g. `icons/menu.svg`;
     *                                                   it may start with `@child/` or with the instance's own prefix
     *                                                   (`@theme/` or `@plugin/`).
     * @param array<string, string|int|bool> $attributes Attributes for the root element; classes are added to those
     *                                                   of the file, `true` sets an attribute without value, `false`
     *                                                   removes it.
     */
    #[TemplateFunction('asset_svg', html: true)]
    public function svg(string $name, array $attributes = []): string
    {
        return $this->assets->svg($name, $attributes);
    }
}
