<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\PostTypeBroken;

use WebCodingCat\WpAppFramework\Post\PostMeta;
use WebCodingCat\WpAppFramework\Value\Content;

#[PostMeta('content', title: 'Meta', postTypes: [Valid::class])]
final class MetaContent
{
    public function __construct(#[Content] public string $content = '') {}
}
