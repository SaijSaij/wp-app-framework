<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\CacheBroken;

use WebCodingCat\WpAppFramework\Cache\VaryProvider;
use WebCodingCat\WpAppFramework\DependencyInjection\NoService;

#[NoService]
final class ExcludedProvider implements VaryProvider
{
    public function value(): string
    {
        return '';
    }
}
