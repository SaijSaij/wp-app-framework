<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\PostTypeBroken;

use Saij\WpAppFramework\Post\PostMeta;

#[PostMeta('readonly_meta', title: 'Meta', postTypes: [Valid::class])]
final readonly class ReadonlyMeta
{
    public function __construct(public int $count = 0) {}
}
