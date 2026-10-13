<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\ContainerValid;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;
use Saij\WpAppFramework\Block\CoreBlock;
use Saij\WpAppFramework\Block\Example;

#[Block('note', title: 'Note', category: BlockCategory::Text, ancestor: [Tabs::class, CoreBlock::Group])]
#[Example(false)]
final class Note
{
    public function render(): string
    {
        return '<p>note</p>';
    }
}
