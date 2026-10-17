<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\ContainerValid;

use WebCodingCat\WpAppFramework\Block\ProvidesContext;
use WebCodingCat\WpAppFramework\Value\PlainText;

final readonly class TabsAttributes
{
    public function __construct(
        #[ProvidesContext]
        public int $active = 0,
        #[ProvidesContext]
        public Tone $tone = Tone::Light,
        #[PlainText]
        public string $label = '',
    ) {}
}
