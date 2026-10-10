# Child themes

A child theme overrides templates of its parent theme in `overrides/{parent-slug}/` (see [Twig templates](./twig.md#lookup)) and adjusts `theme.json`, block templates, template parts and patterns as WordPress allows; for that it needs no Composer, no boot and no build.

A child theme with code of its own – blocks, hooks, REST routes – is a framework instance like a plugin: its own `composer.json` (`type: wordpress-theme`, its `style.css` names the parent theme in `Template:`), its own PSR-4 namespace and block namespace, its own release with its own prefix, and `ChildTheme::boot()` in its `functions.php` (see [Boot and modes](./boot.md)). WordPress loads that file before the parent theme's; the instances share nothing. The build records the parent theme in the manifest, and the boot fails if another parent theme is active. Block names that collide with the parent's are errors, as between any two instances.

The child theme reaches its parent theme only through the parent's public interface (see [Plugins](./plugins.md)): its public template callables, without declaring the parent, and its public API through a constructor parameter with `#[ThemeApi]` (`Saij\WpAppFramework\Api\ThemeApi`):

```php
use My\Theme\Api\SectionsApi;
use Saij\WpAppFramework\Api\ThemeApi;

final class Teasers
{
    public function __construct(
        #[ThemeApi]
        private readonly ?SectionsApi $sections = null,
    ) {}
}
```

The parent theme boots after the child theme, so the service must not be created before `after_setup_theme`. Require the parent theme as a development dependency for the IDE and static analysis. Child themes offer no public interface themselves.
