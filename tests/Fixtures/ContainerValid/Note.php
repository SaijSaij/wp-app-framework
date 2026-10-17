<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\ContainerValid;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;
use WebCodingCat\WpAppFramework\Block\CoreBlock;
use WebCodingCat\WpAppFramework\Block\Example;

#[Block('note', title: 'Note', category: BlockCategory::Text, ancestor: [Tabs::class, CoreBlock::Group])]
#[Example(false)]
final class Note
{
    public function render(): string
    {
        return '<p>note</p>';
    }
}
