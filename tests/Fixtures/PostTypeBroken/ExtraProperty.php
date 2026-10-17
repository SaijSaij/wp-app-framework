<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\PostTypeBroken;

use WebCodingCat\WpAppFramework\Post\PostMeta;

#[PostMeta('extra_property', title: 'Meta', postTypes: [Valid::class])]
final class ExtraProperty
{
    public int $computed = 0;

    public function __construct(public int $count = 0) {}
}
