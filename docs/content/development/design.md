# Design decisions

The framework grew out of several earlier frameworks and themes. This page records the principles it follows, the decisions that are fixed and why, the alternatives that were considered and rejected, and the mistakes of the earlier projects that it must not repeat. Read it before you propose a larger change: many obvious ideas have been tried.

## Principles

1. **No markup in post content.** Every block renders on the server. `post_content` holds only block comments with their attributes as JSON; block supports are attributes too. The single exception is one `#[RichText(postContent: true)]` field per block, stored as inline HTML between the comments, so that the text stays readable in the database and for search. Markup lives in Twig and nowhere else, so a change of a template changes every page at once and no content has to be migrated.
2. **Inline editing in the editor canvas.** Content editors type into the blocks themselves, not into forms in the sidebar.
3. **Full site editing.** Pages consist of block templates and template parts. Twig renders block markup and fragments (mails, HTML responses), never page layouts.
4. **Twig for markup, Tailwind CSS for styles.** The framework provides the build and loads the result; design tokens belong to the theme.
5. **Plain WordPress on the server.** Consumers need neither Composer nor Bedrock on the server; a release is a ZIP with everything prefixed.
6. **Everything that is a value is configurable** in the backend (colors, spacing, variants, order); new block types, roles and layout structures stay code.

The rules for working on the framework follow from these:

- **Verify before you rely.** Behavior of WordPress, Twig, Strauss, Composer or a browser is checked in their source or official documentation, with file and line, before code depends on it. What is not verified is said to be unverified.
- **Fix the cause.** No workaround on top of a workaround.
- **Explicit over implicit.** No silent fallbacks, no silently failing `file_exists()`, no unmarked placeholders, no undocumented behavior sold as reliable. A missing prerequisite is an exception or a build error that names class, file and parameter.
- **Minimal.** No dependency and no abstraction without a demonstrated need.

## Fixed decisions

- **A library, not a plugin or parent theme.** Consumers include the framework with Composer and ship it inside their release. A plugin could be deactivated and break the theme; a parent theme would take away the child theme that client projects need for their customizations.
- **No `block.json`.** A block is a PHP class; its attribute schema is derived from the constructor of its attribute class. Schema and the object the template receives can never diverge, and IDEs resolve every name.
- **Compile only in the build.** Attributes are read in the build; the manifest is plain data; run time uses no reflection and never writes into the consumer. A container compiled at run time into the theme directory, or file checks of all sources on every request, are what made earlier frameworks slow and fragile.
- **An own, minimal container.** Earlier projects used Symfony's DI container or Laravel's, but only ever for constructor autowiring of concrete classes. Their run time, the prefixing patches they needed and their reflection at run time are not worth it.
- **Every consumer is an isolated instance**, named by its namespace (content and data) and its slug (technical names); instances talk only through filters with plain types. A theme and plugins built with different framework versions run side by side.
- **The namespace is required.** It has no default and is not derived from the directory name: the directory name is decided when a theme or plugin is installed, and content must not silently depend on it.
- **Prefixing is mandatory** in every release, for the framework and all dependencies, Twig included (with Strauss, chosen over PHP-Scoper because it prefixes only the symbols of the dependencies and brings its own autoloader).
- **The server renders the editor.** The editor shows the markup of the same Twig templates as the front end, through a batch render route of the instance.
- **Settings are stored in options of the instance**, not in theme mods or global styles: those belong to the stylesheet, and a child theme without code would lose all values.
- **The API is resolvable by IDEs**: enums and classes instead of strings, first-class callables instead of method names, required information as required arguments.

## Rejected alternatives

Do not propose these again without new arguments:

| Alternative | Why not |
|---|---|
| The framework as a plugin | Plugins can be deactivated; the theme would break. |
| The framework as a parent theme | It would block child themes for client customizations. |
| Rendering Twig in the editor with twig.js | twig.js is not compatible with Twig for PHP in all details and has no WordPress data; the server render guarantees identical markup. |
| PHP-only blocks with `autoRegister` (WordPress 7.0) | No inline editing, no inner blocks. |
| `block.json` | Only tooling comfort; PHP classes with a derived schema keep schema and object in sync. |
| Symfony DI or the Laravel container | Only constructor autowiring was ever used; run-time dependencies, prefixing patches and reflection at run time are not needed. |
| Compiling the container at run time into the theme directory | Writes into the consumer and costs every request. |
| Twig components looked up as services | Not needed; templates receive what they need. |
| Theme mods or global styles as storage of settings | They belong to the stylesheet; a child theme without code would lose everything. |
| A bundled copy of WordPress' DataForm for the settings page | A frozen bundle can break on private APIs after WordPress updates; the page uses stable components. |
| `role: content` as the signal for re-rendering in the editor | It describes editing modes, not rendering; the runtime decides by inline fields instead. |
| WordPress' `/wp/v2/block-renderer` route for the editor | It cannot render a batch and has no editor flag; a shared route would also be a contract between framework versions. |
| The Interactivity API and script modules for front-end scripts | Hardly used outside of core; modules can import almost no WordPress package, and the editor canvas does not load them. |
| Declarative targets and actions in the style of Stimulus | Not resolvable by IDEs. |
| The slug as default namespace | The directory name is decided at installation; data must not depend on it silently. |
| Template parts from bundles through WordPress' template filters | There is no registration API for them; bundles would deliver patterns instead. |

## Lessons from earlier projects

The earlier frameworks and themes made these mistakes; the framework is built so that they cannot happen again:

- The editor preview depended on calling a manual re-render, so previews went stale. The runtime now re-renders automatically on every attribute change that is not an inline text field.
- Each block sent its own render request after a 500 ms debounce, and a spinner replaced the content, so layouts jumped. Renders are batched, cached in the client, and the old HTML stays until the new one arrives.
- Blocks had no `apiVersion` and a wrapper `div` without `useBlockProps`. Every block now uses API version 3, and the block props go onto the root element of the rendered markup.
- `React.memo` had no effect because every render created new callbacks, props and contexts.
- `{{ title | upper | raw }}` changed tags, URLs and entities. String filters do not belong on HTML.
- `href` attributes were printed without `esc_url()`; Twig's escaping does not stop `javascript:` URLs.
- The template lookup order was parent before child, so overrides of the child theme had no effect.
- Twig ran without cache and with debug mode always on.
- Twig was left out of prefixing, and the DI container needed patches of the prefixer.
- REST routes were public by default, parameters were read by hand instead of through the `args` schema, output buffers stayed open on missing parameters, errors went out as HTTP 200 or with a stack trace, and services were resolved eagerly.
- The error path of a hook failed itself, and exceptions were swallowed.
- The container was built at run time into the theme directory, every request checked the modification time of every source file, `composer.json` was needed at run time, and parent and child theme registered hooks twice.
- All block services were instantiated when the blocks were registered, and the `WP_Block` context was thrown away.
