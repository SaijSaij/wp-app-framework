<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\Markup;

use WebCodingCat\WpAppFramework\Value\PlainText;

final readonly class Item
{
    public function __construct(
        #[PlainText]
        public string $label,
    ) {}
}
