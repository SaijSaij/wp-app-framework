<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\MetaFieldsBroken;

use WebCodingCat\WpAppFramework\Post\PostMeta;

#[PostMeta('extras', title: 'Extras', postTypes: [Show::class])]
final class ShowExtras
{
    public function __construct(
        public string $subtitle = '',
    ) {}
}
