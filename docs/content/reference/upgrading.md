# Upgrading

Within a major version the consumer API only grows: nothing is renamed, removed or changed in behavior. Before 1.0, minor versions may break it; such changes are listed here.

The documentation is published per minor version; the version switcher in the navigation bar shows the documentation of other versions.

## 0.7

- Template functions that wrap WordPress functions start with `wp_` (see [WordPress functions](../guides/twig.md#wordpress-functions)): `attachment_image()` → `wp_attachment_image()`, `post_url()` → `wp_post_url()`, `post_title()` → `wp_post_title()`, `term_url()` → `wp_term_url()`, `term_name()` → `wp_term_name()`, `user_name()` → `wp_user_name()`. The old names are removed; templates that still use them fail to compile with an unknown function.

## 0.3

- Child themes override theme templates in `overrides/{theme}/` instead of `src/` (see [Twig templates](../guides/twig.md#lookup)). The `src/` of a child theme belongs to its own framework instance (`ChildTheme::boot()`, see [Child themes](../guides/child-themes.md)).

## 0.2

- The init action is named after the consumer: `wp_app_framework/init/{slug}` instead of `wp_app_framework/init` (see [Boot and modes](../guides/boot.md#boot)).
