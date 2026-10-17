<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\CacheBroken;

use WebCodingCat\WpAppFramework\Cache\Cache;

#[Cache(ttl: 60)]
final class NotABlock
{
    /**
     * @return list<string>
     */
    #[Cache(ttl: 60)]
    public function data(): array
    {
        return [];
    }
}
