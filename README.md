# wp-app-framework

PHP/JS framework for building standalone full site editing themes for WordPress 7 (PHP 8.5).

- Composer package: `saij/wp-app-framework`
- PHP namespace: `Saij\WpAppFramework`
- JS import name: `@saij/wp-app-framework`, runtime global: `window.wpAppFramework[slug]` (one per framework instance)

The framework is a library that themes include via Composer. It is neither a theme nor a plugin and registers no blocks itself.

## Status

Framework core (step 1): boot, container, manifest build, development mode and `#[Hook]`. REST routes (step 2): `#[RestController]`, `#[RestRoute]`, `#[Param]`. Twig (step 3): `TemplateRenderer`, `#[TemplateFunction]`, `#[TemplateFilter]`; development mode only so far (release mode follows with the release build). Server-side blocks (step 4): `#[Block]`, `#[Supports]`, typed attribute classes, `#[ListOf]` also for REST arguments. Editor runtime (step 5): inline editing in the canvas, generated inspector, batch render route, webpack helper.

## Usage

### Boot

The theme's `functions.php` loads the autoloader and boots the framework:

```php
<?php

declare(strict_types=1);

// Release builds ship prefixed dependencies in vendor-prefixed/; source checkouts use Composer's vendor/.
require is_file(__DIR__ . '/vendor-prefixed/autoload.php')
    ? __DIR__ . '/vendor-prefixed/autoload.php'
    : __DIR__ . '/vendor/autoload.php';

Saij\WpAppFramework\Theme::boot();
```

`Theme::boot()` may be called once. At its end it fires the action `wp_app_framework/init` (currently without arguments). Child themes attach to this action, because WordPress loads their `functions.php` before the parent's.

### Modes

- **Release mode:** the theme contains `generated/manifest.php`, which only the release build creates. The manifest is loaded as is: no reflection, no file checks, no writes.
- **Development mode:** otherwise. The manifest is built by reflection and cached in `wp-content/cache/{template}/manifest/` (never in the theme). Like Symfony's container cache in debug mode, a meta file records the resources the build depended on, using relative paths only. Each request compares modification times; only for newer files the class signature (attributes, parents, interfaces, traits and the signatures of the constructor and public methods, not method bodies) or file content is recomputed, and only a difference triggers a rebuild.

### Services

Every concrete class under the theme's PSR-4 directories (`autoload.psr-4` in its `composer.json`) and under the framework's `src/` is a service candidate. Interfaces, abstract classes, traits, enums, attribute classes and classes marked `#[NoService]` (`Saij\WpAppFramework\DependencyInjection\NoService`) are not. Services are shared and autowired by constructor parameter types; scalar parameters must have a default value.

Only services reachable from entry points (`#[Hook]`, `#[RestRoute]`, `#[TemplateFunction]` and `#[TemplateFilter]` methods, including permission callbacks, and `#[Block]` classes) are built and checked, so value objects that are never injected need no marking. Aliases and tags are not supported. The container is internal; theme code receives services through constructor and hook parameters only. `Saij\WpAppFramework\Theme` is a predefined instance: `Theme::boot()` creates it (its constructor is private) and hands it to the container, which never builds it. Services can inject it to read the paths via `getTemplateDirectory()`, `getTemplateDirectoryUri()`, `getStylesheetDirectory()`, `getStylesheetDirectoryUri()` and `getFrameworkDirectory()`. `Saij\WpAppFramework\Twig\TemplateRenderer` is predefined as well; the framework creates it on first use.

### Hooks

```php
use Saij\WpAppFramework\Hook\Hook;

final class TitleFilter
{
    #[Hook('the_title', priority: 20)]
    public function filter(string $title, Formatter $formatter, int $postId = 0): string
    {
        return $formatter->format($title, $postId);
    }
}
```

- The method must be public and non-static and declare a return type: `void` registers an action, any other type a filter. `#[Hook]` is repeatable.
- Parameters typed with a service candidate are injected (`Formatter`). All other parameters receive the WordPress arguments in their order (`$title`, `$postId`); `accepted_args` is derived from them. Missing optional arguments use the parameter default.
- Services are created when the hook fires. Exceptions are rethrown if `WP_DEBUG` is enabled; otherwise they are logged with `error_log()`, and filters return their original value.

### REST routes

```php
use Saij\WpAppFramework\Rest\Format;
use Saij\WpAppFramework\Rest\HttpMethod;
use Saij\WpAppFramework\Rest\Param;
use Saij\WpAppFramework\Rest\Permission\Callback;
use Saij\WpAppFramework\Rest\Permission\Capability;
use Saij\WpAppFramework\Rest\Permission\PublicAccess;
use Saij\WpAppFramework\Rest\RestController;
use Saij\WpAppFramework\Rest\RestException;
use Saij\WpAppFramework\Rest\RestRoute;

#[RestController('sections/v1', base: '/items')]
final class ItemController
{
    #[RestRoute('/{id}', HttpMethod::Get, new PublicAccess())]
    public function show(int $id, ItemRepository $items, Variant $variant = Variant::Short): array
    {
        return $items->find($id) ?? throw new RestException('sections_item_not_found', 'Item not found.', 404);
    }

    #[RestRoute('/{id}', [HttpMethod::Put, HttpMethod::Patch], new Callback(self::canEdit(...)))]
    public function update(int $id, #[Param(maxLength: 200)] string $title, #[Param(format: Format::Email)] ?string $contact = null): WP_REST_Response
    {
        // …
        return new WP_REST_Response($data, 201);
    }

    #[RestRoute('/{id}', HttpMethod::Delete, new Capability('delete_posts'))]
    public function delete(int $id): void
    {
        // … responds with 204 No Content
    }

    public static function canEdit(int $id, ItemRepository $items): bool
    {
        return current_user_can('edit_post', $items->getPostId($id));
    }
}
```

- `#[RestController(namespace, base: '')]` on the class defines the REST namespace (no leading or trailing slash) and an optional base path prepended to every route of the class. The value may be a class constant. A `#[RestRoute]` in a class without it is a build error.
- `#[RestRoute(path, methods, permission)]` is repeatable; all three arguments are required, so a missing permission is reported by the IDE and by the build. `path` is empty or starts with `/`; `methods` is an `HttpMethod` case or a list of them.
- **Permission:** `new PublicAccess()` (everybody), `new Capability('…')` (`current_user_can()`) or `new Callback(self::method(...))`. A callback is a first-class callable of a public **static** method returning `bool` – PHP allows first-class callables in attribute arguments only for static methods. Its parameters follow the route rules below; request arguments must exist in the route with the same name and type. Before calling it, the framework validates and sanitizes these arguments against the route's own schema and denies access if one is invalid, because WordPress also calls permission callbacks for the `Allow` header with a request validated for another handler.
- **Path placeholders** `{name}` match the parameter of the same name, which must be required and of type `int` (`\d+`), `string` (`[^/]+`) or a backed enum (its values). Their values are taken from the URL parameters, never from a query argument of the same name. Route paths may only contain letters, digits, `.`, `_`, `~`, `-`, `/` and placeholders.
- **Parameters:** service candidates are injected, `WP_REST_Request` receives the request, all other parameters are request arguments of the same name: `int`, `float`, `string`, `bool`, a backed enum or `array` with `#[ListOf(Enum::class)]` / `#[ListOf(ValueClass::class)]` (see [Values](#values)), optionally nullable with a default value. A single value class without `#[ListOf]` is not supported: a class type is injected as service. The argument schema (`type`, `enum`, `items`, `required`, `default`) is derived from the signature; WordPress validates and sanitizes the arguments, answers 400 on errors and shows the schema on `OPTIONS`. Missing optional arguments use the parameter default; enums, value objects and lists are converted by the value factory.
- `#[Param(description:, minimum:, maximum:, minLength:, maxLength:, pattern:, format: Format::…)]` adds schema keywords; the build checks that they apply to the parameter type.
- **Responses:** a `void` method responds with 204 No Content; any other value goes through `rest_ensure_response()` (200). Return a `WP_REST_Response` for another status or headers.
- **Errors:** throw `RestException(code, message, status, data)` (status 400–599) for an error response. Any other exception is logged with `error_log()` and answered with 500 `wp_app_framework_internal_error`; class, message, file, line and stack trace are included only if `WP_DEBUG` is enabled.
- Services are created when the route is called.

### Twig templates

Templates live in the `src/` directory of each level, next to the code that uses them (`src/Hook/Mail.twig`) or in shared directories (`src/Component/Button.twig`). A template name is always the full path relative to `src/`, including the extension – in PHP and in templates alike; there are no relative names.

```php
use Saij\WpAppFramework\Hook\Hook;
use Saij\WpAppFramework\Twig\TemplateFilter;
use Saij\WpAppFramework\Twig\TemplateFunction;
use Saij\WpAppFramework\Twig\TemplateRenderer;

final class Mail
{
    public function __construct(private readonly TemplateRenderer $renderer)
    {
    }

    #[Hook('sections_mail_body')]
    public function body(string $body, string $name): string
    {
        return $this->renderer->render('Hook/Mail.twig', ['body' => $body, 'name' => $name]);
    }
}

final class Formatting
{
    #[TemplateFunction('price')]
    public function price(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '.') . ' €';
    }

    #[TemplateFilter('highlight', html: true)]
    public function highlight(string $value): string
    {
        return '<mark>' . $value . '</mark>';
    }
}
```

```twig
{% include 'Component/Button.twig' %}      {# child → theme → framework #}
{% include '@framework/Component/Button.twig' %} {# exactly this level #}
{{ price(total) }} {{ name|highlight }}
```

- **Lookup:** names without a namespace are searched in the child theme's `src/` (only if a child theme is active and has one), then the theme's, then the framework's. A higher level overrides a template by providing the same path. `@theme/…` and `@framework/…` address exactly one level and cannot be overridden – use them where the exact template matters, e.g. when an override extends the original. `wp-app-framework build` lists theme templates that override framework templates.
- **Environment:** autoescaping (`html`) and `strict_variables` are always on; undefined variables are errors. In development mode, debug, auto reload and `dump()` are enabled. Compiled templates are cached in `wp-content/cache/{template}/twig/`. Rendering in release mode is not supported yet: without auto reload the cache needs a directory versioned by the release build. Twig exceptions reach the caller, so the error handling of hooks and REST routes applies.
- **`#[TemplateFunction(name, html: false)]` / `#[TemplateFilter(name, html: false)]`** register public, non-static methods of services; the service is created when a template first calls them. Names must be valid identifiers, unique per kind and must not reuse a function or filter Twig provides (Twig would silently replace it); parameters must not be services (inject them through the constructor), variadic or by reference. A filter receives the filtered value as its first parameter.
- **Escaping:** results are escaped unless `html: true`; such a method must return `string`. An HTML filter receives the filtered value HTML-escaped unless it is already safe (e.g. `|raw`, like Twig's `nl2br`). Other arguments are passed unescaped: an HTML function or filter must escape every argument it puts into its result.

### Blocks

A block is a service class with `#[Block]` and a `render()` method; its attributes are a typed attribute class. There is no `block.json`: the build derives the attribute schema from the attribute class, and WordPress passes it to the editor (`get_block_editor_server_block_settings()`). The post content stores only the block comment with the attributes as JSON, e.g. `<!-- wp:sections/hero {"topline":"Summer"} /-->`.

```php
use Saij\WpAppFramework\Block\Align;
use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;
use Saij\WpAppFramework\Block\Content;
use Saij\WpAppFramework\Block\Image;
use Saij\WpAppFramework\Block\Label;
use Saij\WpAppFramework\Block\PlainText;
use Saij\WpAppFramework\Block\RichText;
use Saij\WpAppFramework\Block\Supports;
use Saij\WpAppFramework\Twig\TemplateRenderer;
use Saij\WpAppFramework\Value\ListOf;
use Saij\WpAppFramework\Value\Url;

#[Block('sections/hero', title: 'Hero', category: BlockCategory::Design, icon: 'cover-image')]
#[Supports(anchor: true, align: [Align::Wide, Align::Full])]
final class Hero
{
    public function __construct(private readonly TemplateRenderer $renderer)
    {
    }

    public function render(HeroAttributes $attrs, string $content, WP_Block $block): string
    {
        return $this->renderer->render('Block/Hero/Hero.twig', ['attrs' => $attrs, 'content' => $content]);
    }
}

final readonly class HeroAttributes
{
    public function __construct(
        #[PlainText] public string $topline = '',
        #[RichText(allowedFormats: ['core/bold', 'core/italic'])] public string $headline = '',
        #[Image] #[Label('Image')] public ?int $imageId = null,
        public Gradient $gradient = Gradient::Black,
        #[ListOf(Button::class)] public array $buttons = [],
    ) {
    }
}

final readonly class Button
{
    public function __construct(
        #[PlainText] public string $label,
        #[Content] #[Url] public string $url = '',
    ) {
    }
}
```

```twig
<section {{ block_wrapper_attributes({class: 'hero hero--' ~ attrs.gradient.value}) }}>
    {% if attrs.topline or is_editor() %}
        <p{{ field('topline') }}>{{ attrs.topline }}</p>
    {% endif %}
    <h1{{ field('headline') }}>{{ attrs.headline|raw }}</h1>
    <figure{{ field('imageId') }}>{{ attachment_image(attrs.imageId, 'large') }}</figure>
    {% for button in attrs.buttons %}
        <a href="{{ button.url }}"{{ field(['buttons', loop.index0, 'label']) }}>{{ button.label }}</a>
    {% endfor %}
    <div{{ inner_blocks() }}>{{ content|raw }}</div>
</section>
```

`field()`, `inner_blocks()` and `is_editor()` only matter in the editor; on the front end the marks are empty (see [Editor](#editor)).

- **`#[Block(name, title, category, icon: '', description: '', keywords: [], usesContext: [], allowedBlocks: null)]`** on a service class. The name is `namespace/name` (lowercase letters, digits, `-`); the namespace `core` and duplicate names are build errors, and the framework itself declares no blocks. `category` is a `BlockCategory` case. `usesContext` lists the context values the block reads from `WP_Block::$context`, e.g. `postId` in a query loop. `allowedBlocks` limits the blocks that can be inserted as inner blocks (`allowed_blocks`); it requires `render()` to accept `string $content`. Blocks are registered on `init` with `api_version` 3.
- **`#[Supports(contentRole: false, anchor: false, align: [], customClassName: true, multiple: true, reusable: true, inserter: true, lock: true, renaming: true, customCSS: true)]`** with WordPress' defaults; only differing values are registered. `html` is always disabled. Container blocks set `contentRole: true`, so inner blocks can be inserted in contentOnly mode. `customCSS` (enabled by default in WordPress 7) lets users with `edit_css` store free CSS per block instance; the theme decides whether it allows it – per block with `customCSS: false`, or for all blocks with its own `register_block_type_args` hook.
- **`render()`** is public, non-static and returns `string`. Its parameters are matched by type, each at most once and in any order: the attribute class, `string` (the rendered inner blocks) and `WP_Block`. Services come through the constructor; the block service is created when the block is first rendered.
- **Attribute class:** a value class (see [Values](#values)) whose parameters all have a default value, because an attribute can always be missing in the content. The names `lock`, `metadata`, `className`, `anchor`, `align` and `style` are reserved for the attributes WordPress adds itself (`style` comes with the `customCSS` support, enabled by default in WordPress 7). The attribute schema gets `type`, `enum`, `properties`/`items`, `default` and `role: content` (below).
- **Fields:** `#[PlainText]` (inline text, strings only), `#[RichText(allowedFormats: null)]` (inline HTML, strings only; `allowedFormats` lists the formats the editor offers, e.g. `['core/bold']`, `null` allows all, `[]` none) and `#[Image]` (an attachment ID, `?int` only) mark the fields edited in the canvas; `#[Content]` marks other content such as links. With `inspector: true` (`#[RichText(inspector: true)]`, also on `#[PlainText]` and `#[Image]`) a field is edited in the block inspector instead of the canvas – for values the markup does not show, or that are better edited in a form. An attribute gets `role: content` if it or a nested field is marked, so the block stays editable in contentOnly mode (patterns and template parts open in it in WordPress 7). The marks have no effect on REST arguments.
- **Editor labels:** `#[Label('…')]` labels a value in the inspector and is the placeholder of an inline field; without it the label is derived from the parameter name (`imageId` → "Image id"). A backed enum implements `Block\Labeled` (`label(): string`, evaluated in the build) to label its cases; otherwise the editor shows the case values. `#[Hidden]` keeps a value out of the generated inspector (not allowed on inline fields).
- **Attributes at render time:** WordPress validates the attributes against the schema before rendering (an invalid value falls back to the default) but does not sanitize them. The value factory sanitizes each attribute against its schema (`rest_sanitize_value_from_schema()`) and creates the attribute object; attributes of block supports are ignored.
- **Errors:** exceptions in `render()` or in creating the attributes are rethrown if `WP_DEBUG` is enabled; otherwise they are logged with `error_log()` and the block renders nothing.
- **Core blocks:** the framework disables none. A theme decides itself which core blocks it allows, e.g. with a hook:

```php
final class AllowedBlocks
{
    /**
     * @param bool|list<string> $allowed
     * @return bool|list<string>
     */
    #[Hook('allowed_block_types_all')]
    public function filter(bool|array $allowed): bool|array
    {
        return ['core/post-content', 'core/template-part', 'sections/hero' /* … */];
    }
}
```

#### Escaping

| Value | Output |
|---|---|
| `#[PlainText]` and other strings | `{{ attrs.topline }}` – Twig's autoescaping |
| `#[RichText]` | `{{ attrs.headline\|raw }}` – exactly as stored; WordPress filters it with `wp_kses` when a user without `unfiltered_html` saves |
| `#[Url]` strings | `{{ button.url }}` – the schema has `format: uri`, so the value is sanitized with `sanitize_url()` (disallowed protocols such as `javascript:` become empty) and then autoescaped |
| Images | `#[Image]` attachment ID with `{{ attachment_image(attrs.imageId, size = 'full', attributes = {}) }}` (`wp_get_attachment_image()`; empty for `null`) |
| Inner blocks | `{{ content\|raw }}` |
| Root element | `{{ block_wrapper_attributes(extra = {}) }}` (`get_block_wrapper_attributes()`: classes and anchor of the supports) |

No string filters such as `upper` on HTML (uppercase via CSS).

### Editor

The editor runtime renders every block of the theme in the block editor on the server, from the same Twig template as the front end, and replaces the marked elements with editors. Markup exists only once, in Twig.

**Templates.** In editor renders the template functions mark the editable elements:

- `{{ field('topline') }}`, `{{ field('caption.text') }}`, `{{ field(['buttons', loop.index0, 'label']) }}` on the element of an inline field. The path must name a `#[PlainText]`, `#[RichText]` or `#[Image]` field of the block (list items by index); anything else throws an exception, in the editor and on the front end. Text fields become `RichText` with the element's tag; an image field keeps the server markup and offers select, replace and reset – mark an element around the image (e.g. `<figure>`), not the `<img>`.
- Fields with `inspector: true` are printed without `field()` (marking them throws an exception); the template shows their value like any other attribute.
- `{{ inner_blocks() }}` on the element that contains the inner blocks (blocks whose `render()` accepts `string $content`). It may be the root element.
- `is_editor()` for a structure that does not depend on inline values: render inline fields in the editor even when they are empty, `{% if attrs.topline or is_editor() %}`. In development mode the runtime warns about fields the markup does not mark.
- The template renders exactly one root element with `block_wrapper_attributes()`; the runtime adds the editor's block props to it (no extra wrapper).
- In the canvas, the fields edited there are outlined while their block is hovered or selected (dashed, in the admin color; solid while focused). Fields of inner blocks are outlined only for their own block. Outlines do not change the layout.

**Rendering.** All blocks render through the batch route of the instance, `POST /wp-app-framework/v1/{slug}/render` (`edit_posts`, and `edit_post` for a block's `postId`), like WordPress' block renderer (`render_block()` with empty inner blocks, block supports apply). Each block gets its own result, so one failing block shows an error and the others render. Requests of the same moment are sent together, including the framework blocks inside a container. Text values edited in the canvas are blanked in the request: typing in the canvas never re-renders, every other change does – images, enums, list items and fields with `inspector: true` (applied after a short pause). Results are cached for the editor session by block name, attributes and post, so undo/redo and duplicates need no request. While new markup loads, the old markup stays (dimmed).

**Inspector.** The runtime generates controls from the attribute classes: enum → select, `bool` → toggle, `int`/`float` → number, `string` → text (`#[Url]`: URL), value classes → their values, fields with `inspector: true` → text, rich text or image selection, lists → one card per item with its title (its first text value, else "Item n"), an actions menu (move up, move down, remove) and the item's controls, plus "Add item". Fields of items edited in the canvas stay in the canvas. Typed text is applied after a short pause. The controls use stable components of `@wordpress/components` only; their styles (`js/editor.scss`) are part of the runtime build. Content attributes (`role: content`) appear in the "Content" group, which stays available in contentOnly mode; all others in "Settings".

**Editor scripts.** A block can extend the editor with a script next to its class, `src/Block/Hero/Hero.editor.js`, loaded with the runtime:

```js
import { registerControl, registerPanel } from '@saij/wp-app-framework';
import { PanelBody, RangeControl } from '@wordpress/components';

// Replaces the generated control of an attribute.
registerControl( 'sections/hero', 'count', ( { value, onChange } ) => (
	<RangeControl label="Count" value={ value } onChange={ onChange } min={ 1 } max={ 6 } />
) );

// Adds a panel to the "Settings" group.
registerPanel( 'sections/hero', ( { attributes, setAttributes } ) => <PanelBody title="Hero">…</PanelBody> );
```

Both warn in the console about unknown blocks and attributes. `@saij/wp-app-framework` maps to the runtime of the theme's own framework instance.

**Build.** The theme builds the runtime and its editor scripts with `@wordpress/scripts` and the framework's webpack helper; the framework ships only sources (`js/`). In development the theme's watcher also picks up changes of a symlinked framework checkout.

```json
{
	"scripts": { "build": "wp-scripts build", "start": "wp-scripts start" },
	"devDependencies": {
		"@saij/wp-app-framework": "file:vendor/saij/wp-app-framework",
		"@wordpress/scripts": "^36.1.0"
	}
}
```

```js
// webpack.config.js of the theme
module.exports = require( './vendor/saij/wp-app-framework/webpack.config.js' )( {
	slug: 'theme-sections', // get_template()
	root: __dirname,
} );
```

The output goes to `build/`: the runtime to `build/vendor/wp-app-framework/editor.js` with the styles of its inspector controls (`editor.css`, `editor-rtl.css`), the styles of the editor canvas to `build/vendor/wp-app-framework/canvas.css` (loaded on `enqueue_block_assets` in the editor only, so they reach the canvas iframe but never the front end), editor scripts mirrored from `src/` (`build/Block/Hero/Hero.editor.js`), each with its `.asset.php`. Without a build the editor fails with an exception naming the missing file; a theme without blocks loads no runtime and needs no build. Run `composer install` before `npm install`: the `file:` dependency points into `vendor/`.

**Instances.** Everything global is derived from the slug of the instance (`get_template()`): the render route, the script handles (`{slug}-wp-app-framework`, `{slug}-{block}-editor`) and the JS global `window.wpAppFramework[slug]`, so several framework instances in different versions can run side by side.

### Values

Block attribute classes, nested value classes and `#[ListOf]` REST arguments share one schema derivation (build) and one value factory (runtime, without reflection).

- **Value class:** a `readonly` class whose values are all public promoted constructor parameters and that declares no other properties. Value classes are the exception to the rule that properties are never public.
- **Types:** `int`, `float`, `string`, `bool`, backed enums (schema `enum` with the case values), value classes (schema `object` with `properties`) and `array` with `#[ListOf(Enum::class)]` or `#[ListOf(ValueClass::class)]` (schema `array` with `items`), each optionally nullable (`type: [..., 'null']`). Lists of scalars are not supported yet. Enums and value classes must be in the scanned PSR-4 directories; recursive value classes are build errors.
- **Defaults** become the schema `default` (enums as their value, value objects as arrays). Inside a nested value class a parameter without default is `required`. Unknown keys in nested objects are ignored, like WordPress ignores unknown block attributes.
- **`#[Url]`** on a string adds `format: uri` (sanitized with `sanitize_url()`).
- **Conversion:** missing values use the constructor default; a value that does not match its type throws an `InvalidArgumentException` naming the path, e.g. `buttons[1].url` – values are never coerced silently.

### Build

```sh
vendor/bin/wp-app-framework build [--output=<file>]
```

Builds and checks the manifest of the project whose `vendor/autoload.php` the Composer bin proxy loads; problems are listed with class and parameter names, and the exit code is 1. Without `--output` nothing is written. Do not write `generated/manifest.php` into a theme source checkout: its presence selects release mode. The release build writes it.

## Source layout

`src/` is organized by feature: each directory holds everything of one area – the attributes themes use, the build-time collectors and the runtime classes.

| Directory | Contents |
|---|---|
| `Theme.php` | Boot and paths of the active theme |
| `Hook/` | `#[Hook]` attribute, collector (build), definition, registrar (runtime) |
| `Rest/` | `#[RestController]`, `#[RestRoute]`, `#[Param]` attributes, `HttpMethod`/`Format` enums, `Permission/` (`PublicAccess`, `Capability`, `Callback`), `RestException`, collector (build), definition, registrar (runtime) |
| `Twig/` | `#[TemplateFunction]`, `#[TemplateFilter]` attributes, collector (build), definition, `TemplateRenderer` and runtime loader (runtime), override finder (build) |
| `Block/` | `#[Block]`, `#[Supports]`, `#[PlainText]`, `#[RichText]`, `#[Image]`, `#[Content]`, `#[Label]`, `#[Hidden]` attributes, `Labeled` interface, `BlockCategory`/`Align` enums, collector (build), definition, registrar and template functions (runtime) |
| `Editor/` | Editor template functions (`field()`, `inner_blocks()`, `is_editor()`), render context, batch render route and editor assets (runtime) |
| `Value/` | `#[ListOf]`, `#[Url]` attributes, schema derivation of typed values (build), value factory (runtime) |
| `DependencyInjection/` | `#[NoService]` attribute, service candidates and resolver (build), container (runtime) |
| `Manifest/` | Manifest builder, generator and loader, checks shared by entry point collectors; `Discovery/` (PSR-4 class discovery), `DevCache/` (development mode cache) |
| `Console/` | Command line entry point of `bin/wp-app-framework` |

New areas (settings, block cache) get their own directory following the same pattern. Tests in `tests/Unit/` mirror this layout.

The editor runtime lives in `js/` (`editor.js` entry, `editor.scss` styles of the inspector controls, `canvas.scss` styles of the editor canvas, `runtime/` modules, tests in `runtime/test/`); `webpack.config.js` is the helper for the theme's build.

## Conventions

- The theme API must be fully resolvable by IDEs: enums and classes instead of magic strings, first-class callables instead of method names, required information as required attribute arguments.
- Class properties are never public; access goes through getters with a `get` prefix (`is`/`has` for booleans). Attribute classes such as `#[Hook]` and value classes (block attribute classes) are the exceptions and expose public readonly properties.
- Code, comments and documentation are in English.

## Development

Themes include this repository during development through a Composer `path` repository (symlinked), so changes take effect immediately without a release build. The theme checkout and this checkout must therefore share the same parent directory; see [theme-sections](https://github.com/saijsaij/theme-sections) for an example.

A local WordPress for testing is provided by [wp-dev-env](https://github.com/saijsaij/wp-dev-env).

Tests (PHPUnit) run in the `tools` container of [wp-dev-env](https://github.com/saijsaij/wp-dev-env); WordPress is not loaded, minimal test doubles of the WordPress classes and functions live in `tests/Stubs/`:

```sh
composer install
composer test
```

JavaScript unit tests (Vitest) and linting of the runtime, also in the `tools` container:

```sh
npm ci
npm test
npm run lint
npm run format
```

`package-lock.json` pins these development tools. Themes do not use it: npm ignores the lock files of dependencies, so the theme's own lock file decides which versions it builds with.
