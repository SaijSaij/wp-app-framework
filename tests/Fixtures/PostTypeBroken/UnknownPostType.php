<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\PostTypeBroken;

use Saij\WpAppFramework\Post\PostMeta;

// @phpstan-ignore argument.type (a class that is no post class, on purpose)
#[PostMeta('unknown', title: 'Meta', postTypes: [NotAPost::class])]
final class UnknownPostType
{
    public function __construct(public int $count = 0) {}
}
