<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\CacheValid;

use WebCodingCat\WpAppFramework\Cache\VaryProvider;

/** The region of the visitor: $GLOBALS['wp_app_framework_test_region']. */
final class Region implements VaryProvider
{
    public function value(): ?string
    {
        $region = $GLOBALS['wp_app_framework_test_region'] ?? null;

        return is_string($region) ? $region : null;
    }
}
