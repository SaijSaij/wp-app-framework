<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\PostTypeBroken;

use WebCodingCat\WpAppFramework\Post\PostMeta;
use WebCodingCat\WpAppFramework\Value\PostReference;

#[PostMeta('bad_reference', title: 'Meta', postTypes: [Valid::class])]
final class BadReference
{
    public function __construct(
        #[PostReference(postTypes: [UsesPost::class])]
        public ?int $item = null,
        public Valid $required = new Valid(),
    ) {}
}
