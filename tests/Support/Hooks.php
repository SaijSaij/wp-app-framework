<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Support;

/**
 * Resets the hooks and actions recorded by the WordPress test doubles.
 */
final class Hooks
{
    public static function reset(): void
    {
        unset(
            $GLOBALS['wp_app_framework_test_filters'],
            $GLOBALS['wp_app_framework_test_filter_args'],
            $GLOBALS['wp_app_framework_test_actions'],
        );
    }
}
