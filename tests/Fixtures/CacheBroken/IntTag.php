<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\CacheBroken;

use Saij\WpAppFramework\Cache\CacheTag;

enum IntTag: int implements CacheTag
{
    case One = 1;
}
