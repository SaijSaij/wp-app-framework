<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\PostTypeBroken;

use Saij\WpAppFramework\Post\PostMeta;

#[PostMeta('no_default', title: 'Meta', postTypes: [Valid::class])]
final class MetaNoDefault
{
    public function __construct(public int $count) {}
}
