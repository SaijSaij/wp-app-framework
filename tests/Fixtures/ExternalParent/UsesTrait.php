<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\ExternalParent;

// Only a trait of WordPress, written as a fully qualified name.
final class UsesTrait
{
    use \WordPress\AiClient\Common\Traits\WithDataCachingTrait;

    protected function getCachedKeys(): array
    {
        return [];
    }

    protected function getBaseCacheKey(): string
    {
        return 'trait';
    }
}
