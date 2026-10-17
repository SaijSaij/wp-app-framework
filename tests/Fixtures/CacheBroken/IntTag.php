<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\CacheBroken;

use WebCodingCat\WpAppFramework\Cache\CacheTag;

enum IntTag: int implements CacheTag
{
    case One = 1;
}
