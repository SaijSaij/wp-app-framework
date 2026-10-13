<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\PostTypeBroken;

use Saij\WpAppFramework\Post\PostMeta;
use Saij\WpAppFramework\Value\PostReference;

#[PostMeta('bad_reference', title: 'Meta', postTypes: [Valid::class])]
final class BadReference
{
    public function __construct(
        #[PostReference(postTypes: [UsesPost::class])]
        public ?int $item = null,
        public Valid $required = new Valid(),
    ) {}
}
