<?php

declare(strict_types=1);

/*
 * Writes one image variant in a fresh PHP process; the test starts it with `php -n`, so neither GD nor Imagick is
 * loaded, to check the error for a PHP without image support.
 *
 * Usage: php -n image-variants-probe.php <source image> <target file>
 * Prints "written" or the message of the exception.
 */

use WebCodingCat\WpAppFramework\Asset\ImageVariants;

require __DIR__ . '/../../vendor/autoload.php';

/** @var list<string> $argv Defined by PHP for scripts run from the command line. */
[, $source, $target] = $argv;

try {
    ImageVariants::resize($source, $target, 1, 'webp');
    echo 'written';
} catch (RuntimeException $exception) {
    echo $exception->getMessage();
}
