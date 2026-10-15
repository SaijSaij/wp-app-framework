<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\ContainerValid;

use Saij\WpAppFramework\Block\ProvidesContext;
use Saij\WpAppFramework\Value\PlainText;

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
