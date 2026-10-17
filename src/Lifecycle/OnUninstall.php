<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Lifecycle;

use Attribute;

/**
 * Runs the method when the plugin is deleted, before the framework removes the
 * data of the plugin (posts of its post types, terms, meta, settings, tables,
 * cron events, caches); on a multisite network once per site.
 *
 * Only plugins can be uninstalled: WordPress loads no code of a theme that is
 * deleted, so the attribute in a theme or child theme is a build error. The
 * method is public, returns void and takes services only.
 *
 * @api Attributes/Lifecycle
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class OnUninstall {}
