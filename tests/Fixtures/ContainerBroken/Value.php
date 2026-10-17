<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\ContainerBroken;

use WebCodingCat\WpAppFramework\Block\ProvidesContext;

final readonly class Value
{
    public function __construct(
        #[ProvidesContext]
        public string $tone = '',
    ) {}
}
