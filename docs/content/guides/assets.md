# Static assets

A theme ships more than code: a logo, icons, placeholder images, a font, a PDF to download. These files live in the `assets/` directory of the theme or plugin, next to `src/`. The framework gives each a URL that changes with its content, prints images with their size and in smaller versions and modern formats, inlines SVG icons, preloads fonts, imports files into the media library and lets a child theme replace a file without code.

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

## Responsive images and formats

A large photo should not reach a phone at full size. With `widths` and `sizes`, `asset_image()` adds a `srcset` of smaller versions, and the browser picks the one that fits. With `formats`, it wraps the image in a `<picture>` with a `<source>` per format, so browsers that understand AVIF or WebP load the smaller file:

```twig
{{ asset_image('images/hero.jpg', 'Our team', {
    widths: [640, 1280, 1920],
    sizes: '(min-width: 1200px) 1200px, 100vw',
    formats: ['avif', 'webp'],
    class: 'w-full h-auto',
}) }}
```

The variants are created by the build, before any template renders. The build therefore reads the templates in `src/` and looks for calls of `asset_image()` with `widths` or `formats`: the name of the image, `widths` and `formats` must be written as literals in the call, and `sizes` must be there. Each variant is a file named after the image, the hash of its content, its width and format, so its URL never needs a version. A width larger than the image is left out, and the build says so. The original image stays the largest candidate of the `srcset`.

The build writes the variants with the PHP extension Imagick, or with GD where Imagick cannot write a format. In development mode that is the PHP of your web server, which writes them to `wp-content/cache/{slug}/assets/` when the manifest is rebuilt. The release build runs on the command line and writes them to `generated/assets/` of the release. If neither extension can write a requested format, the build fails and names the format and what to install. Only JPEG, PNG, WebP and AVIF images have variants; an image of an override or of the child theme is printed without them.

## Preloading fonts

A font the stylesheet needs early, such as the font of the headings, should start loading with the page instead of after the stylesheet. Mark its `@font-face` in `assets/styles.css` with a comment:

```css
@font-face {
	/* wp-app-framework: preload */
	font-family: "Inter";
	src: url("./fonts/inter-var.woff2") format("woff2");
	font-display: swap;
}
```

The webpack helper finds the marked rules, takes the first `woff2` file of each and lists its built file in `build/styles.preload.php`. The framework adds a `<link rel="preload" as="font" crossorigin>` for each to the head of the front end, through WordPress' filter `wp_preload_resources`. The preload uses the same URL as the built stylesheet, so the browser loads the font once. Fonts declared in `theme.json` cannot be marked; preload only fonts the first view of the page needs.

## In the media library

Some content needs an attachment rather than a file of the theme: an `#[Image]` field stores the ID of an attachment, and so does content a theme creates on activation. The service `MediaLibrary` imports an asset into the media library and returns the ID of its attachment:

```php
use Saij\WpAppFramework\Asset\MediaLibrary;
use Saij\WpAppFramework\Lifecycle\OnActivation;

final readonly class StarterContent
{
    public function __construct(private MediaLibrary $media) {}

    #[OnActivation]
    public function create(): void
    {
        $image = $this->media->import('images/hero.jpg');
        // … create a page whose hero block uses $image
    }
}
```

The import is idempotent per site: an asset that was imported with the same content returns its attachment. A changed file becomes a new attachment, and the old one stays, because content may still use it. WordPress creates the intermediate sizes as for any upload. Imported attachments stay in the media library when the theme or plugin is removed, like all attachments (see [Activation and uninstallation](./lifecycle.md)).

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

`url()`, `image()` and `svg()` work like the Twig functions. `path()` returns the file's path in the file system, for example to attach it to a mail. `image()` with variants needs the same variants as a call in a template: the build only reads the templates, not PHP code.

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
| `asset_image(name, alt, attributes = {})` | `image(string $name, string $alt, array $attributes = []): string` | `<img>` with `src`, `width` and `height` (if known), `alt`, `decoding="async"` and the attributes; `true` adds an attribute without value, `false` leaves it out. `src`, `srcset` and `alt` cannot be set as attributes. With `widths` (list of pixel widths) and `sizes` the `<img>` gets a `srcset`; with `formats` (`avif`, `webp`, `jpeg`, `png`) a `<picture>` with a `<source>` per format around it. |
| `asset_svg(name, attributes = {})` | `svg(string $name, array $attributes = []): string` | The SVG markup without XML declaration and doctype, the attributes on its root element (`class` added, `false` removes). |

- A name is a path below `assets/`, without `..` segments, without a leading `/` and without `\`. It may start with `@child/` or with the instance's own prefix (`@theme/` in themes and child themes, `@plugin/` in plugins).
- Static assets are all files in `assets/` except hidden files (a name or directory starting with `.`) and `.js`, `.jsx`, `.ts`, `.tsx`, `.css` and `.scss`.
- The build lists each asset with the first 12 characters of the `xxh128` hash of its content, raster images with the width and height from their header, SVG images with `width` and `height` of the root element in pixels or without unit, otherwise with the size of its `viewBox`.
- An unknown name, an invalid name, `@child/` without an active child theme, `asset_svg()` with a file that is no `.svg` or has no root element `<svg>` are errors. In a block they behave like [render errors](./blocks.md#errors): thrown with `WP_DEBUG`, otherwise logged, and the block renders nothing.
- Overrides are searched in the order of the template lookup: for a theme the child theme's `overrides/{theme-slug}/assets/`, for a plugin those of the child theme and the theme. An override needs an asset of the same name in the instance.
- The release ships `assets/`. A listed asset missing in the release – for example removed by `export-ignore` – stops the release build, and so does a missing image variant or a missing `build/styles.preload.php` for a stylesheet with marked fonts.

**Image variants**

- The build reads every template in `src/` with Twig's parser. A call of `asset_image()` whose literal attribute map has `widths` or `formats` asks for variants. A template that does not parse is skipped with a remark in the output of the build.
- Build errors: a name that is no literal string, a name that is no static asset of the project or no JPEG, PNG, WebP or AVIF image, `widths` that are no literal list of positive integers (also missing with `formats`), `widths` without `sizes`, and `formats` that are no literal list of `jpeg`, `png`, `webp` and `avif`.
- All calls of an image add up: the build creates every requested width in the format of the image and in every requested format. Widths larger than the image are left out.
- A variant is `{path without extension}.{hash}.{width}.{extension}`, below `generated/assets/` of a release and `wp-content/cache/{slug}/assets/` in development mode, which also removes variants no template asks for any more. JPEG variants have quality 82, WebP 80, AVIF 60; metadata is removed.
- At run time, a call that asks for variants the build did not create is an error. An image of an override or of the child theme is printed without variants.

**Fonts and the media library**

- `/* wp-app-framework: preload */` in an `@font-face` of `assets/styles.css` marks its first `woff2` file of the project for preloading; a marked rule without one is a build error of the webpack helper. The preloads are printed on the front end only.
- `MediaLibrary::import(string $name): int` imports an asset of the instance itself, also named with `@theme/` or `@plugin/`; `@child/` is an error. The attachment carries the meta key `_wp_app_framework_asset` with slug, name and hash of the asset.
