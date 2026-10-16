# Conventions

These rules apply to the framework and to all repositories around it: the test consumers, the benchmark, the starter and the test and development environments. Each repository adds its own in its README and `CLAUDE.md`.

## Code

**PHP.** The code follows the PER Coding Style, with sorted imports and no unused ones (PHP-CS-Fixer, `composer lint`), and passes PHPStan at level `max` with the WordPress stubs of `szepeviktor/phpstan-wordpress`. Every check passes without errors and without warnings; a warning is fixed, not suppressed. Files start with `declare(strict_types=1)`. Classes are `final` unless they are meant to be extended (`AbstractPost`, `AbstractRow`), and `readonly` where they can be.

**Properties are never public.** Access goes through getters named `get…`, for booleans `is…` or `has…`. The exceptions, each because the property is the API:

- PHP attribute classes (`#[Hook]`, `#[Block]` …);
- block attribute classes and the value classes inside them: `readonly`, with public promoted properties, because templates read them;
- meta classes (`#[PostMeta]`): not readonly, with promoted properties, preferably private with getters and setters;
- test doubles of WordPress classes, which mirror the public properties of WordPress.

**JavaScript and styles** follow the configurations of `@wordpress/scripts` (ESLint with Prettier, Stylelint; `npm run lint`, fixes with `npm run format`). The public JavaScript API is exactly what `types/*.d.ts` declares.

**The consumer API is resolvable by IDEs.** Use enums and classes instead of magic strings (`BlockCategory::Text`, `CoreBlock::Paragraph`, `Event::class`), first-class callables instead of method names (`self::check(...)`), and required attribute arguments for required information.

**Language.** Everything in the repositories is English: code, identifiers, comments, commit messages, documentation. Repositories link to each other by GitHub URL (`https://github.com/saijsaij/<repo>`), never by relative path; pages of this website link to each other by relative `.md` paths.

## Errors and messages

A mistake of a consumer is found as early as possible: in the build if it can be, at boot otherwise. The message says what is wrong, where, and how to fix it, with the class, file, method and parameter at fault:

```
Acme\Theme\Setup::init() has #[Hook] and must be public and non-static.
#[Hook] on Acme\Theme\Setup::init() has an empty hook name.
```

Rules for error handling at run time:

- Hooks, REST routes, blocks, cron events and commands resolve their services only when they run.
- With `WP_DEBUG`, exceptions are thrown on; without it, they are logged with `error_log()` and the request continues (a filter returns its original value, a block renders `''`, a route answers 500 without details). The error path itself must not fail.
- Arguments are checked where they enter: a value out of range is an `InvalidArgumentException` when the method is called; a combination or state that does not fit (a call order, a missing prerequisite) is a `LogicException`; a failure of WordPress or the file system is a `RuntimeException`.

## The public API

What consumers may rely on is the **consumer API**: attributes and their parameters, the boot methods, Twig functions, the JavaScript exports of its imports (`@saij/wp-app-framework`, `/view`, `/settings`), the webpack helper and its conventions, the documented script handles, the `release` command and the generator commands, the filter `wp_app_framework/cache/backends/{slug}` and the inputs of `consumer-release.yml`. The [API reference](../api/index.md) lists it: classes marked `@api <group>` (without their `@internal` members), `types/*.d.ts`, the `exports` of `package.json` and the pages on hooks, commands and the workflow. Everything else is internal.

- Every class, public member and parameter of the API has a doc comment, written for the readers of the reference. Members only the framework uses are `@internal`. The documentation build fails on a missing description, on references to internal classes and on `@see` without target.
- **Within a major version, the consumer API only grows**: nothing is renamed, removed or changed in its behavior. Before 1.0, a minor version may still break it; every such change gets a section in [Upgrading](../reference/upgrading.md) with the reason and the steps for consumers.
- The filters `wp_app_framework/api/{slug}` and `wp_app_framework/template/{slug}`, the action `wp_app_framework/init/{slug}`, the action `wp_app_framework/namespace/{namespace}` and the shape of their values are a **contract between framework versions**, because instances of different versions use them together. They may only be extended, also across major versions.
- Internals such as the manifest format may change at any time; `Manifest::FORMAT` is raised with every change of its structure.

## Documentation

Every change of the consumer API or of observable behavior updates the [Guide](../index.md) in the same commit. Every change of conventions, tests or pipelines updates these pages in the same commit.

The guide pages are written for people learning the framework, not as a specification:

- Explanatory prose that addresses the reader ("you"), in active sentences with one idea each. A page starts with what the feature is for and why it exists, then how it works, then an example built up step by step with text between the code blocks.
- WordPress internals go into a sentence of their own, not into parentheses in the middle of a rule.
- Exact rules – signatures, parameters, defaults, build errors – stay complete; they are collected in a "Rules" section or a table at the end of a section. Tables and lists are for reference, not for explanations.
- Shared ideas are explained once in [Concepts](../concepts.md); other pages link there.
- Guides link signatures to the API reference instead of repeating every parameter. Doc comments contain no raw `{`, `}`, `<`, `>` outside backticks, because the reference is MDX.
- Callouts (`:::warning`, `:::tip`) are used sparingly, for pitfalls.

The API reference is generated: `tools/api-reference` writes a page per `@api` class and per Twig function, `tools/api-reference-js.mjs` a page per export of `types/*.d.ts`. Only its overview and the pages on hooks, `composer.json`, commands, the webpack helper and the release workflow are written by hand.

## Commits and releases

- One or two commits per step of work, each one passing lint and build; a later change gets a new commit instead of a rewritten one. Commit messages are English, with a summary line and a body that says what changed and why.
- Pushes come after the tests of their stage (see [Testing](./testing.md#when-to-run-what)).
- Tags are `v<semver>`. Every GitHub release gets release notes written for the people who use it – summary, new features, changes of behavior and breaking changes with a link to Upgrading, fixes, compatibility, documentation – instead of the generated compare link alone; [Pipelines and releases](./pipelines.md#release-notes) has the details.
