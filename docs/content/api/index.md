# API reference

This part of the documentation lists everything your theme or plugin can use: each attribute, class, Twig function and JavaScript export on a page of its own, with its parameters, types and defaults. The [guides](../guides/boot.md) explain how the parts work together and why; come here when you need the exact signature.

The pages are generated from the code of the framework and its doc comments, so they always match the version you read the documentation for.

## What belongs to the API

The API is what this reference lists. Within a major version it only grows: nothing listed here is renamed, removed or changed in behavior (see [Upgrading](../reference/upgrading.md)).

Everything else in the framework is internal, even if PHP lets you reach it: classes that are not listed, public methods marked `@internal` in the code, and the parts of the JavaScript global `window.wpAppFramework[slug]` other than `api`, `view` and `settingsApi`. Internal code can change in any version. Injecting one of the framework's internal services into a class of your own is a build error.

## Sections

- **Attributes** declare blocks, values, hooks, REST routes, settings, post types, tables, cache, cron, WP-CLI commands, lifecycle methods and Twig functions.
- **Boot classes** start the framework in a theme, child theme or plugin and describe it.
- **Services** are provided by the framework: inject them like your own.
- **Posts and queries**, **Query fields** and **Tables** are the active records and their queries.
- **Cache** lists the tags and variants of cached entries.
- **Values and exceptions** are value objects, interfaces you implement and exceptions you throw.
- **Enums** are the cases attributes and queries take.
- **Twig functions** are available in every template, the framework's own and the wrappers of WordPress functions. Besides them, templates have the global `settings` with your [settings groups](../guides/settings.md).
- **JavaScript** lists the exports of `@saij/wp-app-framework` for editor scripts, of `@saij/wp-app-framework/view` for view scripts and of `@saij/wp-app-framework/settings` for settings scripts.
- **Configuration and commands** covers what is not code: [hooks](./hooks.md), [`composer.json`](./composer.md), [commands](./commands.md), the [webpack helper](./webpack.md) and the [release workflow](./workflow.md).
