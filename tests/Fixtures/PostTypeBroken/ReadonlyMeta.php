<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\PostTypeBroken;

use WebCodingCat\WpAppFramework\Post\PostMeta;

#[PostMeta('readonly_meta', title: 'Meta', postTypes: [Valid::class])]
final readonly class ReadonlyMeta
{
    public function __construct(public int $count = 0) {}
}
