<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Release;

use RuntimeException;
use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * A release build step failed; the message names the step's file, command or setting.
 */
#[NoService]
final class ReleaseException extends RuntimeException {}
