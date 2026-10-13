# Build

The framework reads your attributes in a build step and turns them into a manifest (see [Concepts](../concepts.md#build-and-run-time)). During development it runs that step itself on each request that needs it. You can also run it from the command line, which is useful in two situations:

- **To check your code.** The build reports every problem it finds: a constructor parameter the container cannot fill, a REST route without permission, an invalid attribute, a typo in a block template. Running it before a commit or in CI catches mistakes without opening WordPress.
- **To write a release manifest.** The release build uses it internally.

```sh
vendor/bin/wp-app-framework build [--output=<file>]
```

In a consumer created as in [Getting started](../getting-started.md), `composer build-manifest` runs the same command.

## Checking

Without `--output`, the command builds and checks the manifest and writes nothing. It builds the project whose `vendor/autoload.php` the Composer bin proxy loads, which is the project you run it in. Problems are listed with class and parameter names, and the exit code is 1.

The command also lists the templates of the project that override templates of the framework.

## Checking templates, parts and patterns

A theme writes blocks into files as well: block templates in `templates/`, template parts in `parts/` and patterns in `patterns/`. WordPress reads these files as they are. A misspelled attribute, a value the block does not know or HTML in a block that stores none would only show up when the page renders, or not at all, because WordPress drops what it cannot use without a word. The build therefore checks these files against the blocks of your theme.

Take a pattern with a card block whose attribute class has a `title` and a `tone` enum with the values `light` and `dark`:

```php
<?php
/**
 * Title: Teaser
 * Slug: my-theme/teaser
 */
?>
<!-- wp:my-theme/card {"titel":"Welcome","tone":"pink"} /-->
```

The build stops with two errors, each with the file, the line and the block:

```
patterns/teaser.php:6: block my-theme/card: unknown attribute "titel".
patterns/teaser.php:6: block my-theme/card: attribute "tone": "pink" is not one of "light", "dark".
```

### What is checked

The build reads every `*.html` below `templates/` and `parts/` and every `*.php` below `patterns/`, the files WordPress uses for a block theme. Each block of your theme is checked against its definition. The JSON in the block comment may only contain the attributes of the attribute class and of the block supports, each with a value that fits its schema: the right type, a value of the enum, the required properties of a value class and no others.

The block's structure is checked too. HTML between the block comments is only allowed in a block with a [`postContent` field](../guides/blocks.md#text-in-the-post-content). Inner blocks are only allowed in a block whose `render()` takes `$content`, and only blocks from its `allowedBlocks`.

Template parts and patterns with a single top-level block open in contentOnly mode in the editor (see [Content in contentOnly mode](../guides/blocks.md#content-in-contentonly-mode)). There, a block can only be inserted into a container that is itself a content block. A container of your theme without content that holds content blocks of your theme is therefore an error in such a template part or pattern; give it `#[Supports(contentRole: true)]`.

### Blocks of others

The build knows only the blocks of your own theme. Core blocks, blocks of plugins and, in a child theme, the blocks of the parent theme are not checked, because their definitions are not part of your build. Only their JSON must be valid.

A block name in the namespace of your theme (`extra.wp-app-framework.namespace`) must be one of your blocks: with the namespace `my-theme`, `my-theme/crad` is reported as an unknown block. Each theme and plugin has a namespace of its own, so the build can tell your blocks from those of others.

Plugins are not checked, because WordPress reads block templates, template parts and patterns from themes only. A child theme without code has no build, so nothing checks its files.

### PHP in patterns

WordPress runs a pattern file as PHP and uses its output. The build cannot run it, so it reads the file as it is and skips the PHP code. PHP in the text of a block, such as a translated sentence in a core paragraph, is fine. PHP inside the block comment of one of your blocks is an error, because the attributes are then only known at run time. Write them as plain JSON.

### Rules

The build reports these errors in block templates, template parts and patterns:

- A block comment that WordPress would treat as HTML, such as `<!--wp:my-theme/card /-->` without a space.
- A closing comment without an opening one, a closing comment for another block than the open one, and a block that is never closed. WordPress repairs these silently.
- Invalid JSON in a block comment, for any block, unless the comment contains PHP. WordPress ignores all attributes of such a block.
- An unknown block in the namespace of your own blocks.
- In your own blocks:
  - an attribute that is neither in the attribute class, nor one of the block's [supports](../guides/supports.md), nor one of WordPress' attributes allowed by `#[Supports]` (`lock` and `metadata` always; `className` unless `customClassName: false`; `anchor` with `anchor: true`; `align` with one of the allowed alignments or `""`; `style` unless `customCSS: false`);
  - a value with the wrong type, a value outside the enum, a missing required property or an unknown property of a value class;
  - the `postContent` field in the JSON instead of between the block comments;
  - HTML between the block comments without a `postContent` field;
  - inner blocks in a block without `$content`, and inner blocks missing from `allowedBlocks`;
  - PHP code in the block comment (patterns);
  - in a template part or a pattern with a single top-level block, a container that is not a content block but holds content blocks of your theme.
- A directory `block-templates/` or `block-template-parts/`. WordPress reads these deprecated directories instead of `templates/` and `parts/` as soon as one of them exists.

During development, the framework rebuilds the manifest when one of these files changes, so an error shows up on the next request.

## Generating fields for queries

```sh
vendor/bin/wp-app-framework meta-fields <meta class> [--force]
```

This command writes the class with `#[MetaFields]` that [queries](../guides/queries.md#meta-fields-in-queries) use to filter and sort by the fields of a meta class: `{MetaClass}Fields.php`, next to the meta class. It does not replace an existing file unless you pass `--force`. Unlike the build, it writes into your source code; the result is yours to change and commit.

## Writing a manifest

With `--output`, the command writes the manifest to the given file. A written manifest is a release manifest and carries the template version, which names the directory of the release's Twig cache.

:::warning
Do not write `generated/manifest.php` into a source checkout of a theme or plugin. Its presence switches the project to release mode (see [Boot and modes](../guides/boot.md#modes)), and the framework stops noticing changes to your code. The [release build](./release.md) writes it into its own copy of the project.
:::
