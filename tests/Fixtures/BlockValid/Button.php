<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\BlockValid;

use WebCodingCat\WpAppFramework\Value\PlainText;
use WebCodingCat\WpAppFramework\Value\Url;

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
