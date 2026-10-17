<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\ColorValid;

use WebCodingCat\WpAppFramework\Value\Color;

final readonly class Stripe
{
    public function __construct(
        #[Color]
        public string $color = '#fff',
    ) {}
}
