<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\BlockValid;

use Saij\WpAppFramework\Block\PlainText;
use Saij\WpAppFramework\Value\Url;

final readonly class Button
{
    public function __construct(
        #[PlainText]
        public string $label,
        #[Url]
        public string $url = '',
        public ?Size $size = null,
    ) {}
}
