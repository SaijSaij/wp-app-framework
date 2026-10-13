<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\ContainerValid;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\Context;

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
