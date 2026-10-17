<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Lifecycle;

use Attribute;

/**
 * Runs the method when the plugin is activated or the site switches to the
 * theme (or child theme).
 *
 * A plugin runs it in the request that activates it (`activate_{plugin}`);
 * network-wide activation runs it once per site. A theme runs it on the first
 * request after the switch (`after_switch_theme`), unless the previous theme
 * was this theme or its child theme. The method is public, returns void and
 * takes services only.
 *
 * @api Attributes/Lifecycle
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class OnActivation {}
