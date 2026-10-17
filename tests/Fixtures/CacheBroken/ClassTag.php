<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\CacheBroken;

use WebCodingCat\WpAppFramework\Cache\CacheTag;
use WebCodingCat\WpAppFramework\DependencyInjection\NoService;

#[NoService]
final class ClassTag implements CacheTag {}
