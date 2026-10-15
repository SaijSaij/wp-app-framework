<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Api;

use Attribute;

/**
 * Injects the public API (`#[PublicApi]`) of the parent theme into a
 * constructor parameter of a service of a child theme:
 * `#[ThemeApi] ?SectionsApi $sections = null`.
 *
 * Only child themes use it; the parent theme is the one named by the
 * `Template:` header of their style.css. The parameter must be nullable with
 * the default null, like `#[PluginApi]`. The class is not loaded by the build;
 * require the parent theme as a development dependency for the IDE and static
 * analysis. Resolving the service before `after_setup_theme` is an error,
 * because the parent theme boots after the child theme.
 *
 * @api Attributes/Services and hooks
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class ThemeApi {}
