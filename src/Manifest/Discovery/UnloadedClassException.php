<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Manifest\Discovery;

use RuntimeException;
use WebCodingCat\WpAppFramework\DependencyInjection\NoService;

/**
 * The build tried to load a class it does not load (see ExternalParents); becomes a build error.
 */
#[NoService]
final class UnloadedClassException extends RuntimeException {}
