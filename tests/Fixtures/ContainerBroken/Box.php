<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\ContainerBroken;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\CoreBlock;
use Saij\WpAppFramework\Block\InnerBlocks;
use Saij\WpAppFramework\Block\TemplateBlock;
use Saij\WpAppFramework\Block\Variation;

#[Block('box', title: 'Box', category: BadCategory::Good)]
#[InnerBlocks(
    allowed: [Item::class, CoreBlock::Heading],
    template: [
        new TemplateBlock(Stray::class),
        new TemplateBlock(Item::class, ['missing' => 1]),
        new TemplateBlock(Item::class, [], [new TemplateBlock(CoreBlock::Heading)]),
    ],
    defaultBlock: new TemplateBlock(CoreBlock::Heading, [], [new TemplateBlock('acme/x')]),
    prioritized: ['acme/other'],
)]
#[Variation('Bad name', 'Bad')]
#[Variation('twice', 'A')]
#[Variation('twice', 'B')]
#[Variation('inactive', 'Inactive', isActive: ['tone'])]
#[Variation('scope', 'Scope', scope: [])]
final class Box
{
    public function render(BoxAttributes $attributes, string $content): string
    {
        return $content;
    }
}
