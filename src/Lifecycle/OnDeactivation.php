<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Lifecycle;

use Attribute;

/**
 * Runs the method when the plugin is deactivated or the site switches away
 * from the theme (or child theme).
 *
 * A plugin runs it in the request that deactivates it (`deactivate_{plugin}`);
 * network-wide deactivation runs it once per site. A theme runs it in the
 * request that switches (`switch_theme`), unless the new theme is this theme
 * or its child theme. Afterwards the framework removes the cron events and
 * tasks of the instance. The method is public, returns void and takes services
 * only.
 *
 * @api Attributes/Lifecycle
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class OnDeactivation {}
