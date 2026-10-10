<?php

declare(strict_types=1);

// PHPUnit only: the test doubles of WordPress are not part of the autoloader, so
// static analysis sees the real WordPress signatures (php-stubs/wordpress-stubs).
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/Stubs/wordpress.php';
