<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\BlockValid;

use Saij\WpAppFramework\Value\Url;

final readonly class Link
{
    public function __construct(
        #[Url]
        public string $href = '',
        public bool $external = false,
    ) {}
}
