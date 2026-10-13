<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\PostTypeBroken;

use Saij\WpAppFramework\Block\Content;
use Saij\WpAppFramework\Post\PostMeta;

#[PostMeta('content', title: 'Meta', postTypes: [Valid::class])]
final class MetaContent
{
    public function __construct(#[Content] public string $content = '') {}
}
