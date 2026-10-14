# Static assets

A theme ships more than code: a logo, icons, placeholder images, a font, a PDF to download. These files live in the `assets/` directory of the theme or plugin, next to `src/`. The framework gives each a URL that changes with its content, prints images with their size, inlines SVG icons, and lets a child theme replace a file without code.

## How it works

Every file in `assets/` is a static asset, except hidden files and the sources of the script build – `.js`, `.jsx`, `.ts`, `.tsx`, `.css` and `.scss` (see [Concepts](../concepts.md#src-and-assets)). You name an asset by its path below `assets/`: `images/logo.svg`, `fonts/inter.woff2`.

The build lists the assets in the manifest, each with a short hash of its content and, for images, its width and height. At run time the framework reads everything it needs from there and never touches the file system for an asset of the instance. The hash goes into the URL as `?ver=…`, so browsers and CDNs load a changed file anew while they cache an unchanged one for as long as they like. In development mode, a new, removed or changed file rebuilds the manifest on the next request.

## In templates

Three Twig functions cover the usual cases. `asset_url()` returns the URL of any asset, for a link or a `<video>`:

```twig
<a href="{{ asset_url('docs/price-list.pdf') }}">Price list</a>
<video src="{{ asset_url('videos/intro.mp4') }}" muted autoplay></video>
```

`asset_image()` prints an `<img>` element. It sets `width` and `height` from the manifest, so the page does not jump while the image loads, and `decoding="async"`. The alternative text is a required argument; pass `''` for a decorative image. Further attributes follow as a map:

```twig
{{ asset_image('images/logo.svg', site_name, {class: 'h-10 w-auto', loading: 'lazy'}) }}
```

`asset_svg()` puts the markup of an SVG image into the page, so CSS can style it – an icon that takes the color of the text, for example. It drops the XML declaration and adds the given attributes to the root element; classes are added to those the file has:

```twig
<button class="menu-toggle">
    {{ asset_svg('icons/menu.svg', {class: 'size-6 fill-current', 'aria-hidden': 'true'}) }}
    Menu
</button>
```

Prefer `asset_svg()` over Twig's own `source()`: it checks that the file is an SVG image of the instance and changes its root element without string operations.

## In PHP

The service `Assets` offers the same as methods, for a pattern written in PHP, a REST response or a mail. Inject it like any service:

```php
use Saij\WpAppFramework\Asset\Assets;

final readonly class Newsletter
{
    public function __construct(private Assets $assets) {}

    public function header(): string
    {
        return $this->assets->image('images/logo.png', 'My Shop');
    }

    public function attachment(): string
    {
        return $this->assets->path('docs/terms.pdf');
    }
}
```

`url()`, `image()` and `svg()` work like the Twig functions. `path()` returns the file's path in the file system, for example to attach it to a mail.

## Overrides and the child theme's own files

A child theme replaces an asset of its parent theme with a file of the same name in `overrides/{theme-slug}/assets/`. The customer's logo needs no code: `overrides/my-theme/assets/images/logo.svg` in the child theme replaces `assets/images/logo.svg` of `my-theme` wherever the theme prints it. In the same way, a theme or child theme replaces an asset of a plugin in `overrides/{plugin-slug}/assets/`. The search order is the one of [Twig templates](./twig.md#lookup), in the `assets/` subdirectory of each override directory, so asset names never collide with template names.

A child theme can also bring files that replace nothing, for example in an override of a parent template. They live in the child theme's own `assets/` directory, and their name starts with `@child/`:

```twig
{# overrides/my-theme/Block/Team/Team.twig in the child theme #}
{{ asset_image('@child/images/team.jpg', 'Our team') }}
```

`@theme/…` (in a plugin: `@plugin/…`) names the instance's own file and skips the overrides, like the prefixes of [Twig templates](./twig.md#lookup).

No manifest knows the files of the overrides directories and of `@child/`, because they belong to another project. The framework therefore looks them up in the file system, only in override directories that exist, and versions their URL with their modification time.

## Files the build references

A stylesheet or script can reference an asset itself: `url('./fonts/inter.woff2')` in `assets/styles.css`, or `import logo from './images/logo.png'` in a script. These references belong to the build of `@wordpress/scripts`, which copies such files with a hash in their name to `build/images/` and `build/fonts/` and writes their URL into the built file. They need nothing from the framework, and an override does not replace them.

Fonts are a common case. A plugin declares them with `@font-face` in its `assets/styles.css`. A theme can do the same, or declare them in `theme.json`, where WordPress resolves `file:./assets/fonts/…` relative to the theme. WordPress looks for such a file in the child theme first, so there a file of the same name in the child theme's `assets/` replaces the theme's file without `overrides/`.

## Rules

| Function | Method of `Assets` | Returns |
|---|---|---|
| `asset_url(name)` | `url(string $name): string` | The URL with `?ver=` and the hash of the content, or the modification time for files of overrides and `@child/`. |
| – | `path(string $name): string` | The path in the file system. |
| `asset_image(name, alt, attributes = {})` | `image(string $name, string $alt, array $attributes = []): string` | `<img>` with `src`, `width` and `height` (if known), `alt`, `decoding="async"` and the attributes; `true` adds an attribute without value, `false` leaves it out. `src` and `alt` cannot be set as attributes. |
| `asset_svg(name, attributes = {})` | `svg(string $name, array $attributes = []): string` | The SVG markup without XML declaration and doctype, the attributes on its root element (`class` added, `false` removes). |

- A name is a path below `assets/`, without `..` segments, without a leading `/` and without `\`. It may start with `@child/` or with the instance's own prefix (`@theme/` in themes and child themes, `@plugin/` in plugins).
- Static assets are all files in `assets/` except hidden files (a name or directory starting with `.`) and `.js`, `.jsx`, `.ts`, `.tsx`, `.css` and `.scss`.
- The build lists each asset with the first 12 characters of the `xxh128` hash of its content, raster images with the width and height from their header, SVG images with `width` and `height` of the root element in pixels or without unit, otherwise with the size of its `viewBox`.
- An unknown name, an invalid name, `@child/` without an active child theme, `asset_svg()` with a file that is no `.svg` or has no root element `<svg>` are errors. In a block they behave like [render errors](./blocks.md#errors): thrown with `WP_DEBUG`, otherwise logged, and the block renders nothing.
- Overrides are searched in the order of the template lookup: for a theme the child theme's `overrides/{theme-slug}/assets/`, for a plugin those of the child theme and the theme. An override needs an asset of the same name in the instance.
- The release ships `assets/`. A listed asset missing in the release – for example removed by `export-ignore` – stops the release build.
