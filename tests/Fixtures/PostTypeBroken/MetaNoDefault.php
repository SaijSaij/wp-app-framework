<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\PostTypeBroken;

use WebCodingCat\WpAppFramework\Post\PostMeta;

#[PostMeta('no_default', title: 'Meta', postTypes: [Valid::class])]
final class MetaNoDefault
{
    public function __construct(public int $count) {}
}
