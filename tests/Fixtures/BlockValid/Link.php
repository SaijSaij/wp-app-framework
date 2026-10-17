<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\BlockValid;

use WebCodingCat\WpAppFramework\Value\Url;

final readonly class Link
{
    public function __construct(
        #[Url]
        public string $href = '',
        public bool $external = false,
    ) {}
}
