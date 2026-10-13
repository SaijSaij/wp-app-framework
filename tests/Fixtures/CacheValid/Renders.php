<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\CacheValid;

use Saij\WpAppFramework\DependencyInjection\NoService;

/** Counts renders in $GLOBALS['wp_app_framework_test_renders'] by name. */
#[NoService]
final class Renders
{
    public static function count(string $name): int
    {
        $renders = $GLOBALS['wp_app_framework_test_renders'] ?? [];
        $renders = is_array($renders) ? $renders : [];
        $count = is_int($renders[$name] ?? null) ? $renders[$name] + 1 : 1;
        $renders[$name] = $count;
        $GLOBALS['wp_app_framework_test_renders'] = $renders;

        return $count;
    }

    public static function get(string $name): int
    {
        $renders = $GLOBALS['wp_app_framework_test_renders'] ?? [];

        return is_array($renders) && is_int($renders[$name] ?? null) ? $renders[$name] : 0;
    }
}
