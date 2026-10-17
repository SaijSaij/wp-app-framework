<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\PostTypeBroken;

use WebCodingCat\WpAppFramework\Post\PostMeta;

#[PostMeta('empty', title: 'Meta', postTypes: [])]
final class EmptyPostTypes
{
    public function __construct(public int $count = 0) {}
}
