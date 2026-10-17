<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\ColorValid;

use WebCodingCat\WpAppFramework\Value\Color;
use WebCodingCat\WpAppFramework\Value\Label;
use WebCodingCat\WpAppFramework\Value\ListOf;

final readonly class BannerAttributes
{
    /**
     * @param list<Stripe> $stripes
     */
    public function __construct(
        #[Color]
        public string $background = '',
        #[Color]
        #[Label('Text color')]
        public string $text = '#1e1e1e80',
        #[ListOf(Stripe::class)]
        public array $stripes = [],
    ) {}
}
