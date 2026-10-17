<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\ContainerValid;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\Context;

#[Block('tab', title: 'Tab', category: Category::Extras, parent: [Tabs::class])]
final class Tab
{
    public function render(
        TabAttributes $attributes,
        string $content,
        #[Context(Tabs::class, 'active')]
        int $active = 7,
        #[Context(Tabs::class, 'tone')]
        ?Tone $tone = null,
    ): string {
        return sprintf('[%s|%d|%s]', $attributes->title, $active, $tone === null ? 'none' : $tone->value);
    }
}
