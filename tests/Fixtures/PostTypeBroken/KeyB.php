<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\PostTypeBroken;

use Saij\WpAppFramework\Post\PostMeta;

#[PostMeta('key_a', title: 'Meta', postTypes: [Valid::class])]
final class KeyB
{
    public function __construct(public int $shared = 0) {}
}
