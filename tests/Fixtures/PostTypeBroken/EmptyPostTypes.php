<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\PostTypeBroken;

use Saij\WpAppFramework\Post\PostMeta;

#[PostMeta('empty', title: 'Meta', postTypes: [])]
final class EmptyPostTypes
{
    public function __construct(public int $count = 0) {}
}
