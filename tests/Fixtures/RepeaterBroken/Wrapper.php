<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\RepeaterBroken;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;
use Saij\WpAppFramework\Block\Repeater;

#[Block('wrapper', title: 'Wrapper', category: BlockCategory::Design, excerptWrapper: true)]
final class Wrapper
{
    public function render(Repeater $repeater): string
    {
        return '';
    }
}
