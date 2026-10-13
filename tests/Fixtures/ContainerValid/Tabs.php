<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\ContainerValid;

use Saij\WpAppFramework\Block\Appender;
use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\CoreBlock;
use Saij\WpAppFramework\Block\Example;
use Saij\WpAppFramework\Block\InnerBlocks;
use Saij\WpAppFramework\Block\Orientation;
use Saij\WpAppFramework\Block\TemplateBlock;
use Saij\WpAppFramework\Block\TemplateLock;
use Saij\WpAppFramework\Block\Variation;
use Saij\WpAppFramework\Block\VariationScope;

#[Block('tabs', title: 'Tabs', category: Category::Sections)]
#[InnerBlocks(
    allowed: [Tab::class, CoreBlock::Paragraph, 'acme/slider'],
    template: [new TemplateBlock(Tab::class, ['title' => 'One'], [new TemplateBlock(CoreBlock::Paragraph, ['content' => 'Text'])]), new TemplateBlock(Tab::class)],
    templateLock: TemplateLock::Insert,
    orientation: Orientation::Horizontal,
    defaultBlock: new TemplateBlock(Tab::class, ['title' => 'New']),
    directInsert: true,
    prioritized: [Tab::class],
    appender: Appender::Button,
)]
#[Example(attributes: ['active' => 1, 'tone' => Tone::Dark], innerBlocks: [new TemplateBlock(Tab::class, ['title' => 'A'])], viewportWidth: 800)]
#[Variation('dark', 'Dark tabs', attributes: ['tone' => Tone::Dark], scope: [VariationScope::Inserter, VariationScope::Transform], isActive: ['tone'])]
#[Variation('three', 'Three tabs', description: 'Three tabs at once.', icon: 'columns', keywords: ['many'], innerBlocks: [new TemplateBlock(Tab::class), new TemplateBlock(Tab::class), new TemplateBlock(Tab::class)])]
final class Tabs
{
    public function render(TabsAttributes $attributes, string $content): string
    {
        return '<div>' . $content . '</div>';
    }
}
