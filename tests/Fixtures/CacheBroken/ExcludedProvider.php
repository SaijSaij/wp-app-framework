<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\CacheBroken;

use Saij\WpAppFramework\Cache\VaryProvider;
use Saij\WpAppFramework\DependencyInjection\NoService;

#[NoService]
final class ExcludedProvider implements VaryProvider
{
    public function value(): string
    {
        return '';
    }
}
