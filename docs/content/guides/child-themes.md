# Child themes

Child themes are where customer projects adapt a theme: different markup for a block, adjusted colors, an extra block or two. The framework supports two kinds of child theme, and you pick the one that fits the job.

## Child themes without code

Most adaptations need no PHP at all. A child theme without code overrides templates of its parent theme and adjusts `theme.json`, block templates, template parts and patterns, as WordPress allows for any child theme. It needs no Composer, no boot and no build. Without a build, nothing checks its block templates, template parts and patterns against the blocks of the parent theme (see [Checking templates, parts and patterns](../build-and-release/build.md#checking-templates-parts-and-patterns)).

To change the markup of a block, put a template with the same path into `overrides/{parent-slug}/`. For the parent theme `my-theme`, the file `overrides/my-theme/Block/Hero/Hero.twig` replaces the theme's `src/Block/Hero/Hero.twig`. The block keeps its attributes and its editor; only the markup changes. [Twig templates](./twig.md#lookup) describes the full lookup order.

Files work the same way: `overrides/my-theme/assets/images/logo.svg` replaces the theme's `assets/images/logo.svg`, and files that replace nothing go into the child theme's own `assets/` and are named `@child/…` (see [Static assets](./assets.md#overrides-and-the-child-themes-own-files)).

The overrides can use the classes in the parent theme's stylesheet, or plain CSS in the child theme's `style.css`. If they need further Tailwind utilities, the child theme needs code and a build of its own (see [Styles](./styles.md#plugins-and-child-themes)).

## Child themes with code

As soon as a child theme brings code of its own – blocks, hooks, REST routes – it becomes a framework instance of its own, just like a plugin. It has:

- its own `composer.json` with `type: wordpress-theme`, while its `style.css` names the parent theme in `Template:`;
- its own PSR-4 namespace and its own namespace for blocks, REST routes and settings (`extra.wp-app-framework.namespace`);
- its own release, prefixed with its own namespace;
- `ChildTheme::boot()` in its `functions.php` (see [Boot and modes](./boot.md)).

Why a separate instance instead of extending the parent's? WordPress loads the child theme's `functions.php` before the parent's, and in a release the parent's framework carries the parent's prefix. The child theme cannot rely on the parent's framework classes, so it brings its own. The two instances share nothing.

The build records the parent theme in the manifest, and the boot fails if another parent theme is active. Block names that collide with the parent's are errors, as between any two instances.

The `src/` directory of a child theme with code belongs to its own instance. Its templates are searched there and in the framework, and they never override the theme's templates. Overrides of the parent still go into `overrides/{parent-slug}/`.

## Using the parent theme

The child theme reaches its parent theme only through the parent's public interface (see [Plugins](./plugins.md)).

**Template callables.** The child theme's templates call the parent's public template functions and filters directly. Unlike with plugins, the child theme doesn't need to list the parent in its `composer.json`.

**Public API.** The child theme receives the parent's public API through a constructor parameter with `#[ThemeApi]` (`WebCodingCat\WpAppFramework\Api\ThemeApi`):

```php
use My\Theme\Api\SectionsApi;
use WebCodingCat\WpAppFramework\Api\ThemeApi;

final class Teasers
{
    public function __construct(
        #[ThemeApi]
        private readonly ?SectionsApi $sections = null,
    ) {}
}
```

The parent theme boots after the child theme, so the service must not be created before `after_setup_theme`. Require the parent theme as a development dependency for your IDE and static analysis.

Child themes offer no public interface of their own.
