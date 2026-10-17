# Translations

A theme or plugin is written in English, and its editors may work in German, French or any other language of WordPress. The framework translates what editors and visitors read with the text domain of your theme or plugin – the `Text Domain` header of `style.css` or of the main file of the plugin – like WordPress translates any theme or plugin.

There are three kinds of texts:

- **Texts in attributes**: the title of a block, the labels of a post type, the `#[Label]` of a value, the label of an enum case. You write them in English; the framework translates them when it hands them to WordPress or the editor.
- **Texts in templates**: `{{ __('Load map') }}` in Twig.
- **Texts in PHP and JavaScript**: WordPress' own functions, `__('Load map', 'my-plugin')`, as in any plugin.

The translations themselves are PO files in `languages/`, which the framework compiles and loads.

## The text domain

The text domain is the `Text Domain` header, usually the slug of the theme or plugin:

```php
/**
 * Plugin Name: Datebook
 * Text Domain: datebook
 */
```

The header is required: a project without it is a build error. Use the same domain in your own calls of `__()`, so that all texts of the project end up in one translation.

## Texts in attributes

You write the texts of attributes in English, as before. The build keeps them as they are, and the framework translates each one with your text domain where it reaches WordPress or the editor:

| Text | Translated |
|---|---|
| `#[Block]` and `#[Variation]`: `title`, `description`, `keywords` | when the block is registered |
| block categories (`CustomBlockCategory::label()`) | when the categories are added |
| `#[PostType]` and `#[Taxonomy]`: labels (also the derived ones, such as "Add New Event") and `description` | when the type is registered |
| `#[PostMeta]`: `title` | in meta boxes and meta panels |
| `#[SettingsGroup]`: `title`, `description`; the name of the theme or plugin as title of the settings page | on the settings page |
| `#[Label]`, labels derived from parameter names, `Labeled::label()` of enums, `itemLabel` of `#[ListOf]`, `label` of `#[BlockSupport]` | in the inspector, meta boxes, meta panels and the settings page |

The labels the framework derives for a post type are whole sentences – "Add New Event", "No events found." – and are translated as such. That suits languages in which the words change with the type: "Neue Veranstaltung hinzufügen", but "Neuen Ort hinzufügen".

Texts in the attributes and the content of `#[Example]` and `#[InnerBlocks]` templates are content, not labels, and are not translated.

## Texts in templates

Templates translate with `__()`, `_x()`, `_n()` and `_nx()`, which take the arguments of WordPress' functions without the text domain:

```twig
<button type="button">{{ __('Load map') }}</button>
<span>{{ _x('May', 'month') }}</span>
<p>{{ _n('%d event', '%d events', events|length)|format(events|length) }}</p>
```

The result is text; Twig escapes it like any other value. Fill placeholders with Twig's `format` filter.

## Translation files

The translations are PO files in the directory `languages/` of your theme or plugin, named after the text domain and the locale:

```
languages/
└── datebook-de_DE.po
```

The framework loads the translation for the locale of the request (`determine_locale()`, the language of the user in the backend) on `init`, before blocks and post types are registered:

- **In development mode**, it compiles the PO file into the cache directory of the instance (`wp-content/cache/{slug}/languages/`) whenever the PO file changed, so an edited translation shows on the next request.
- **The release build** compiles every PO file into `generated/languages/{domain}-{locale}.l10n.php`, the translation format of WordPress since 6.5, and the release loads it without looking at the file system.

Fuzzy and untranslated entries of a PO file are left out, as gettext's `msgfmt` does. Translations WordPress has for the text domain in `wp-content/languages/` – from translate.wordpress.org or a translation plugin – are loaded as well; an entry of your PO file wins.

## Rules

- The `Text Domain` header is required; without it the build fails.
- A PO file in `languages/` is named `{text domain}-{locale}.po`, for example `datebook-de_DE.po` or `datebook-de_DE_formal.po`; any other name of a PO file is a build error.
- Texts in attributes are English source texts; they are translated at run time with the text domain of the instance that declares them, also when another theme or plugin renders its templates.
- The Twig functions `__()`, `_x()`, `_n()` and `_nx()` use the text domain of the instance whose template calls them.
