<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Cache\Vary;

use Saij\WpAppFramework\Cache\CacheVary;
use Saij\WpAppFramework\Cache\VaryProvider;
use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * A circumstance of your own: the value of a service implementing VaryProvider.
 */
#[NoService]
final readonly class ServiceVary implements CacheVary
{
    /**
     * @param class-string<VaryProvider> $provider
     */
    public function __construct(private string $provider) {}

    /**
     * @return class-string<VaryProvider>
     */
    public function getProvider(): string
    {
        return $this->provider;
    }
}
