<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\DependencyInjection;

use Attribute;

/**
 * Excludes a class from the container.
 *
 * Every concrete class under the scanned PSR-4 directories is a service candidate
 * unless it carries this attribute. Parameters typed with an excluded class are
 * never injected; in hook methods they receive arguments passed by WordPress.
 *
 * @api Attributes/Services and hooks
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class NoService {}
