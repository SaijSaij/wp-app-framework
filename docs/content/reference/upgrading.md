# Upgrading

From 1.0 on, the consumer API only grows within a major version: nothing is renamed, removed or changed in behavior, and a theme built against 1.0 keeps working with 1.9. Before 1.0, a minor version may still break the API. This page lists every such change, why it was made, and what you need to change in your theme or plugin.

The documentation is published for each minor version. The version switcher in the navigation bar shows the documentation of other versions, so you can compare the old and the new state.

## 0.7

**Template functions that wrap WordPress functions start with `wp_`.** Before 0.7 a template could call `post_title()` or `attachment_image()`, and nothing showed that these were WordPress functions rather than framework functions or functions of the theme. 0.7 gives all of them the prefix `wp_` and adds many more (see [WordPress functions](../guides/twig.md#wordpress-functions)).

The old names are removed without aliases. A template that still uses one fails to compile with an unknown function, so you won't miss any. Rename them in your templates:

| Before | Since 0.7 |
|---|---|
| `attachment_image()` | `wp_attachment_image()` |
| `post_url()` | `wp_post_url()` |
| `post_title()` | `wp_post_title()` |
| `term_url()` | `wp_term_url()` |
| `term_name()` | `wp_term_name()` |
| `user_name()` | `wp_user_name()` |

If your own template functions use one of the new `wp_` names, rename them as well: a name the framework already uses is a build error.

## 0.3

**Child themes override theme templates in `overrides/{theme}/` instead of `src/`.** Since 0.3 a child theme can have code of its own and boot its own framework instance with `ChildTheme::boot()` (see [Child themes](../guides/child-themes.md)). Its `src/` directory now belongs to that instance and can no longer hold overrides of the parent theme.

Move each override from the child theme's `src/` to `overrides/{theme}/`, keeping the path. For the parent theme `my-theme`, `src/Block/Hero/Hero.twig` becomes `overrides/my-theme/Block/Hero/Hero.twig` (see [Twig templates](../guides/twig.md#lookup)).

## 0.2

**The init action is named after the consumer.** Since 0.2 plugins can use the framework next to a theme, and each runs its own instance. The single action `wp_app_framework/init` could not tell them apart, so it became `wp_app_framework/init/{slug}` (see [Boot and modes](../guides/boot.md#slug-and-init-action)).

Replace `add_action('wp_app_framework/init', …)` with the action of the instance you mean, for example `wp_app_framework/init/my-theme`.
