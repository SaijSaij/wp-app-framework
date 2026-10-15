<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\PostTypeBroken;

use Saij\WpAppFramework\Post\PostMeta;
use Saij\WpAppFramework\Value\Content;

#[PostMeta('content', title: 'Meta', postTypes: [Valid::class])]
final class MetaContent
{
    public function __construct(#[Content] public string $content = '') {}
}
