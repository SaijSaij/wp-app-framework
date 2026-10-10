<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Api;

use Attribute;

/**
 * Marks a service of a plugin as its public API: other consumers of the
 * framework (the theme, other plugins) receive it through a constructor
 * parameter with `#[PluginApi]`.
 *
 * Only plugins declare public APIs. The service is created when a consumer
 * first needs it. Its public methods and the plugin classes they use must not
 * use classes of the framework or of other dependencies: a release prefixes
 * them, so their names differ in every consumer (build error). The plugin
 * keeps its API compatible itself.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class PublicApi {}
