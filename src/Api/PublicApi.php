<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Api;

use Attribute;

/**
 * Marks a service of a plugin or theme as its public API: other consumers of
 * the framework receive it through a constructor parameter with `#[PluginApi]`
 * (the API of a plugin) or `#[ThemeApi]` (child themes, the API of their parent).
 *
 * Plugins and themes declare public APIs, child themes do not. The service is
 * created when a consumer first needs it. Its public methods and the project
 * classes they use must not use classes of the framework or of other
 * dependencies: a release prefixes them, so their names differ in every
 * consumer (build error). The provider keeps its API compatible itself.
 *
 * @api Attributes/Services and hooks
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class PublicApi {}
