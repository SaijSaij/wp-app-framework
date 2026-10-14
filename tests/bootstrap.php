<?php

declare(strict_types=1);

// PHPUnit only: the test doubles of WordPress are not part of the autoloader, so
// static analysis sees the real WordPress signatures (php-stubs/wordpress-stubs).
// The WP-CLI double is also the signature file of the static analysis.
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/Stubs/wordpress.php';
require __DIR__ . '/Stubs/wp-cli.php';
