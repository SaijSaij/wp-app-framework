<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Cache\Vary;

use WebCodingCat\WpAppFramework\Cache\CacheVary;
use WebCodingCat\WpAppFramework\Cache\VaryProvider;
use WebCodingCat\WpAppFramework\DependencyInjection\NoService;

/**
 * A circumstance of your own: the value of a service implementing VaryProvider.
 *
 * @api Cache
 */
#[NoService]
final readonly class ServiceVary implements CacheVary
{
    /**
     * @param class-string<VaryProvider> $provider A service of yours that implements VaryProvider; its `value()` is
     *                                             part of the cache key.
     */
    public function __construct(private string $provider) {}

    /**
     * The class of the service that provides the value.
     *
     * @return class-string<VaryProvider>
     */
    public function getProvider(): string
    {
        return $this->provider;
    }
}
