<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\ContainerBroken;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;
use WebCodingCat\WpAppFramework\Block\Example;
use WebCodingCat\WpAppFramework\Block\TemplateBlock;

#[Block('stray', title: 'Stray', category: BlockCategory::Text, parent: [Box::class])]
#[Example(false, innerBlocks: [new TemplateBlock('acme/x')])]
final class Stray
{
    public function render(): string
    {
        return '';
    }
}
