<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Rest\Permission;

use WebCodingCat\WpAppFramework\DependencyInjection\NoService;

/**
 * Everybody may call the route, including anonymous visitors.
 *
 * @api Values and exceptions
 */
#[NoService]
final readonly class PublicAccess implements Permission {}
