<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\PostTypeBroken;

use Saij\WpAppFramework\Post\PostMeta;

#[PostMeta('extra_property', title: 'Meta', postTypes: [Valid::class])]
final class ExtraProperty
{
    public int $computed = 0;

    public function __construct(public int $count = 0) {}
}
