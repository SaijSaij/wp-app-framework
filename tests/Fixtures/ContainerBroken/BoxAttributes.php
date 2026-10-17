<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\ContainerBroken;

use WebCodingCat\WpAppFramework\Block\ProvidesContext;

final readonly class BoxAttributes
{
    public function __construct(
        #[ProvidesContext]
        public string $tone = 'light',
        public int $size = 0,
    ) {}
}
