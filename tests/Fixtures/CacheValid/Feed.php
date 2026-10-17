<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\CacheValid;

use WebCodingCat\WpAppFramework\Cache\CacheTag;

enum Feed: string implements CacheTag
{
    case Weather = 'weather';
}
