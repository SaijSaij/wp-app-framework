<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\CacheBroken;

use Saij\WpAppFramework\Cache\CacheTag;
use Saij\WpAppFramework\DependencyInjection\NoService;

#[NoService]
final class ClassTag implements CacheTag {}
