<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\ColorValid;

use Saij\WpAppFramework\Value\Color;

final readonly class Stripe
{
    public function __construct(
        #[Color]
        public string $color = '#fff',
    ) {}
}
