<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\MetaFieldsBroken;

use Saij\WpAppFramework\Post\PostMeta;

#[PostMeta('extras', title: 'Extras', postTypes: [Show::class])]
final class ShowExtras
{
    public function __construct(
        public string $subtitle = '',
    ) {}
}
