<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\Markup;

use Saij\WpAppFramework\Block\PlainText;

final readonly class Item
{
    public function __construct(
        #[PlainText]
        public string $label,
    ) {}
}
