# Styles

Block markup is styled with [Tailwind CSS](https://tailwindcss.com) 4. The work is split between the framework and the theme. The framework compiles the stylesheet and loads it on the front end and in the editor. The design – which Tailwind tokens exist and which WordPress values they point to – belongs to the theme.

The framework ships no stylesheet for the front end of its own, because design tokens differ from theme to theme.

## Setting up the stylesheet

A theme, child theme or plugin that has a stylesheet `assets/styles.css` gets it compiled to `build/styles.css` by the [webpack helper](./editor.md#build). The stylesheet lives in `assets/`, the directory for everything that belongs to no PHP class (see [Concepts](../concepts.md#src-and-assets)). Only this file goes through Tailwind, using Tailwind's official loader `@tailwindcss/webpack`. All other stylesheets of the build keep the PostCSS setup of `@wordpress/scripts`. A consumer without `assets/styles.css` doesn't need Tailwind at all.

Install Tailwind next to `@wordpress/scripts`:

```sh
npm install --save-dev tailwindcss@4 @tailwindcss/webpack@4
```

If the packages are missing, the build fails with a message that names them. The helper looks for `assets/styles.css` when it starts, so restart `npm run start` after adding or removing the file.

Here is a stylesheet for a theme:

```css
@layer theme, base, components, utilities;
@import "tailwindcss/theme.css" layer(theme);
@import "tailwindcss/utilities.css" layer(utilities) source(none);

/* Where the class names are: the Twig templates of the theme and its overrides. */
@source "../src/**/*.twig";
@source "../overrides/**/*.twig";

/* Tokens on the values of theme.json. */
@theme inline {
	--color-primary: var(--wp--preset--color--primary);
	--color-contrast: var(--wp--preset--color--contrast);
	--spacing-section: var(--wp--preset--spacing--50);
	--container-content: var(--wp--style--global--content-size);
	--container-wide: var(--wp--style--global--wide-size);
}
```

The following sections go through it.

### Tokens

`theme.json` is where a block theme defines its colors, font sizes and spacing. WordPress turns these into CSS variables such as `--wp--preset--color--{slug}`, `--wp--preset--font-size--{slug}`, `--wp--preset--spacing--{slug}` and `--wp--style--global--content-size`. Users can change many of these values in the site editor.

`@theme inline` maps Tailwind tokens to these variables, so `bg-primary` becomes `background-color: var(--wp--preset--color--primary)`. The keyword `inline` makes the utility refer to the WordPress variable itself rather than to a copy. When someone changes a color in the backend, only the variable changes, and the stylesheet never needs a rebuild.

To allow only your own tokens, reset Tailwind's defaults, for example with `--color-*: initial;`.

### Sources

Tailwind generates only the classes it finds in your files. `source(none)` turns off its automatic detection, and `@source` lists the files to scan, relative to the stylesheet.

Name the Twig files explicitly. Scanning PHP files would produce classes from words in the code, such as `static`, `block`, `hidden` or `container`. A word in a template that is not meant as a utility can be excluded with `@source not inline("container")` – for example a class `container` of the theme's own, which Tailwind would otherwise style as its `container` utility.

Templates in `overrides/` are rendered with the theme's stylesheet, so they belong to its sources.

### Class names

Write class names out in full in the templates. Never assemble them from attribute values, as in `gap-{{ gap }}`: Tailwind cannot find `gap-4` in that template, so the class would be missing. Map values to complete class names instead:

```twig
{% set gaps = { small: 'gap-2', large: 'gap-8' } %}
<div class="flex {{ gaps[attrs.gap.value] }}">…</div>
```

Class names generated at run time, such as those of [block supports](./supports.md#styling), can be listed with `@source inline(…)`.

### Preflight

The example does not import Preflight, Tailwind's reset (`tailwindcss/preflight.css`). It would reset the styles of core blocks on the front end and in the editor canvas. Import it deliberately if the theme styles everything itself.

## Loading

If the consumer has `assets/styles.css`, the build records it in the manifest. The framework then loads `build/styles.css` on `enqueue_block_assets` with the handle `{slug}-styles`. That reaches the front end and the canvas iframes of the post and site editor, but not the main document of the editor, so the admin interface stays unstyled. `add_editor_style()` is not needed.

A missing `build/styles.css` is an exception naming the file. A stylesheet in its former place, `src/styles.css`, is a build error, both in the manifest build and in the webpack helper.

The `styles-rtl.css` that `@wordpress/scripts` writes is not used. Tailwind handles the writing direction itself, with logical properties (`ms-4`, `ps-4`) and the `rtl:` variant.

## Editor and front end

The editor canvas renders a block from the same server markup as the front end and loads the same stylesheet. Every element of a block gets the same size, spacing and type in the canvas as on the page, so content editors see the real spacing.

The framework's canvas styles help with one difference: WordPress lets text in blocks break anywhere in the editor, and the canvas styles restore the wrapping of the front end. They live in the cascade layer `base`, so your utilities still win.

Some things a theme has to do itself, and some stay different on purpose.

**Space between blocks.** Set `settings.spacing.blockGap` in `theme.json`, with a value in `styles.spacing.blockGap`. Without it, WordPress 7.1 puts its default gap between blocks in the editor, but none on the front end.

**Width of blocks.** `settings.layout.contentSize` in `theme.json` is optional. Without it, WordPress still constrains blocks to the undefined content size in the editor – which means no maximum width, the whole canvas – but not on the front end. A maximum width set with a utility on the block's root element then applies on the front end only. Set a content size if blocks need the same width in both places.

**WordPress' layout wins.** Tailwind's utilities live in cascade layers, WordPress' layout styles do not, and styles outside layers win regardless of specificity. The width of a block in a constrained layout (`max-width`, centering margins) and the space between blocks therefore come from `theme.json`, not from utilities on the block's root element. Use utilities to style the inside of a block.

**Empty fields** are rendered in the editor, with a placeholder, so they can be filled in. On the front end the template leaves them out (`{% if attrs.title or is_editor() %}`).

**White space.** Fields edited in the canvas keep typed spaces and line breaks visible (`white-space: pre-wrap`, set by WordPress' RichText); the front end collapses them.

**The page around the blocks** is WordPress' editor. In the post editor without the template, the content is not inside `.wp-site-blocks`, and the root padding sits on the canvas body.

## Plugins and child themes

Class names are global, and every instance builds its own stylesheet. If a plugin used the same class names as the theme with different values, whichever stylesheet loads last would win.

A **plugin** therefore uses a prefix of lowercase letters a–z on both imports, so its classes and variables never meet those of the theme:

```css
@import "tailwindcss/theme.css" layer(theme) prefix(note);
@import "tailwindcss/utilities.css" layer(utilities) source(none) prefix(note);
```

```twig
<div class="note:p-4 note:bg-accent">…</div>
```

Tailwind's internal variables (`--tw-*`, registered with `@property`) carry no prefix. Every Tailwind 4 stylesheet on the page shares them.

A **child theme with code** shares the design of its parent theme and uses the same tokens without a prefix.

A **child theme without code** builds no stylesheet. Its overrides use classes that the parent theme's stylesheet already contains, or plain CSS in its `style.css`. If it needs further utilities, it becomes a child theme with code and gets a build of its own (see [Child themes](./child-themes.md)).
