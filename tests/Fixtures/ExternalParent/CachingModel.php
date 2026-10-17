<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\ExternalParent;

use WordPress\AiClient\Common\{Contracts\CachesDataInterface, Traits\WithDataCachingTrait as Caching};

// Implements an interface and uses a trait of WordPress (php-ai-client) through a group import.
final class CachingModel implements \Countable, CachesDataInterface
{
    use Caching;

    public function count(): int
    {
        return 0;
    }

    protected function getCachedKeys(): array
    {
        return [];
    }

    protected function getBaseCacheKey(): string
    {
        return 'model';
    }
}
