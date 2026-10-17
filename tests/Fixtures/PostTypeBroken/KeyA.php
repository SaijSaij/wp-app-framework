<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\PostTypeBroken;

use WebCodingCat\WpAppFramework\Post\PostMeta;

#[PostMeta('key_a', title: 'Meta', postTypes: [Valid::class])]
final class KeyA
{
    public function __construct(public int $shared = 0) {}
}
