<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\ContainerBroken;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;
use Saij\WpAppFramework\Block\Example;
use Saij\WpAppFramework\Block\TemplateBlock;

#[Block('stray', title: 'Stray', category: BlockCategory::Text, parent: [Box::class])]
#[Example(false, innerBlocks: [new TemplateBlock('acme/x')])]
final class Stray
{
    public function render(): string
    {
        return '';
    }
}
