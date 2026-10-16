<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\ColorBroken;

use Saij\WpAppFramework\Value\Color;
use Saij\WpAppFramework\Value\PlainText;
use Saij\WpAppFramework\Value\Url;

final readonly class BrokenAttributes
{
    public function __construct(
        #[Color]
        public int $number = 0,
        #[Color]
        public ?string $nullable = null,
        #[Color]
        #[Url]
        public string $url = '',
        #[Color]
        #[PlainText]
        public string $plain = '',
        #[Color]
        public string $named = 'red',
        #[Color]
        public string $noHash = '1e4d8c',
    ) {}
}
