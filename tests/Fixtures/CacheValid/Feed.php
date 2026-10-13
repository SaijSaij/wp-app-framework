<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\CacheValid;

use Saij\WpAppFramework\Cache\CacheTag;

enum Feed: string implements CacheTag
{
    case Weather = 'weather';
}
