# Styles

Block markup is styled with [Tailwind CSS](https://tailwindcss.com) 4. The framework provides the build and loads the result; the design – which Tailwind tokens exist and which WordPress values they point to – belongs to the theme.

## Stylesheet

A theme, child theme or plugin with a stylesheet `src/styles.css` gets it compiled by the [webpack helper](./editor.md#build) to `build/styles.css`. Only this file goes through Tailwind (with the official loader `@tailwindcss/webpack`); all other stylesheets of the build keep the PostCSS setup of `@wordpress/scripts`. A consumer without `src/styles.css` needs no Tailwind.

Install Tailwind next to `@wordpress/scripts`:

```sh
npm install --save-dev tailwindcss@4 @tailwindcss/webpack@4
```

The build fails with a message naming these packages if they are missing. The helper checks for `src/styles.css` when it starts: after adding or removing the file, restart `npm run start`.

A stylesheet for a theme:

```css
@layer theme, base, components, utilities;
@import "tailwindcss/theme.css" layer(theme);
@import "tailwindcss/utilities.css" layer(utilities) source(none);

/* Where the class names are: the Twig templates of the theme and its overrides. */
@source "./**/*.twig";
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

- **Tokens:** `@theme inline` maps Tailwind tokens to the CSS variables WordPress generates from `theme.json` (`--wp--preset--color--{slug}`, `--wp--preset--font-size--{slug}`, `--wp--preset--spacing--{slug}`, `--wp--style--global--content-size` …): `bg-primary` becomes `background-color: var(--wp--preset--color--primary)`. Use `inline`, so the utility refers to the WordPress variable itself. Values set in the backend therefore only change variables; the stylesheet never needs a rebuild. To allow only your own tokens, reset Tailwind's defaults, e.g. `--color-*: initial;`.
- **Sources:** `source(none)` turns off Tailwind's automatic detection, and `@source` lists the files with class names, relative to the stylesheet. Name Twig files explicitly: scanning PHP files produces classes from words in the code (`static`, `block`, `hidden`, `container`). Words in templates that are no utilities – a class `container` of the theme's own, which Tailwind would style as its `container` utility – are excluded with `@source not inline("container")`. Templates in `overrides/` are rendered with the stylesheet of the theme, so they belong to its sources.
- **Class names** are written out in full in the templates; never assemble them from attribute values (`gap-{{ gap }}`). Map values to complete class names, or list generated ones with `@source inline(…)` – such as the class names of [block supports](./supports.md#styling), which are built at run time.
- **Preflight** (Tailwind's reset, `tailwindcss/preflight.css`) is not imported above: it would reset the styles of core blocks on the front end and in the editor canvas. Import it deliberately if the theme styles everything itself.

## Loading

If the consumer has `src/styles.css`, the build records it in the manifest, and the framework loads `build/styles.css` on `enqueue_block_assets` with the handle `{slug}-styles` – on the front end and in the canvas iframes of the post and site editor, but not in the main document of the editor, so the admin interface stays unstyled. A missing `build/styles.css` is an exception naming the file. `add_editor_style()` is not needed.

The `styles-rtl.css` written by `@wordpress/scripts` is not used: Tailwind handles the writing direction with logical properties (`ms-4`, `ps-4`) and the `rtl:` variant.

## Editor and front end

The editor canvas renders a block from the same server markup as the front end, and loads the same stylesheet: every element of a block gets the same size, spacing and type in the canvas as on the page, so content editors see the real spacing. The framework's canvas styles keep text wrapping as on the front end (WordPress lets text in blocks break anywhere in the editor); they live in the cascade layer `base`, so the consumer's utilities still win.

What a theme has to do itself, and what stays different:

- **Space between blocks:** set `settings.spacing.blockGap` in `theme.json` (with a value in `styles.spacing.blockGap`). Without it, WordPress 7.1 puts its default gap between blocks in the editor, but none on the front end.
- **Width of blocks:** `settings.layout.contentSize` in `theme.json` is optional. Without it, WordPress still constrains blocks to the (undefined) content size in the editor – no maximum width, the whole canvas – but not on the front end, so a maximum width set with a utility on the block's root element applies on the front end only. Set a content size if blocks need the same width in both places.
- **WordPress' layout wins:** Tailwind's utilities live in cascade layers, WordPress' layout styles do not, and unlayered styles win regardless of specificity. The width of a block in a constrained layout (`max-width`, centering margins) and the space between blocks come from `theme.json`, not from utilities on the block's root element; utilities style the inside of a block.
- **Empty fields** are rendered in the editor, with a placeholder, so they can be filled in; on the front end the template leaves them out (`{% if attrs.title or is_editor() %}`).
- **White space:** fields edited in the canvas keep typed spaces and line breaks visible (`white-space: pre-wrap`, set by WordPress' RichText), the front end collapses them.
- **The page around the blocks** is WordPress' editor: in the post editor without the template, the content is not inside `.wp-site-blocks`, and the root padding sits on the canvas body.

## Plugins and child themes

Class names are global, and every instance builds its own stylesheet. A child theme with code shares the design of its parent theme and uses the same tokens without a prefix. A plugin uses a prefix (lowercase letters a–z) on both imports, so its classes and variables never meet those of the theme:

```css
@import "tailwindcss/theme.css" layer(theme) prefix(note);
@import "tailwindcss/utilities.css" layer(utilities) source(none) prefix(note);
```

```twig
<div class="note:p-4 note:bg-accent">…</div>
```

Tailwind's internal variables (`--tw-*`, registered with `@property`) carry no prefix; every Tailwind 4 stylesheet on the page shares them.

A child theme without code builds no stylesheet: its overrides use classes that the stylesheet of the parent theme already contains, or plain CSS in its `style.css`. If it needs further utilities, it becomes a child theme with code and a build of its own (see [Child themes](./child-themes.md)).
