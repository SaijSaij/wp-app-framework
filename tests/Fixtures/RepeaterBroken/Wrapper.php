<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\RepeaterBroken;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;
use WebCodingCat\WpAppFramework\Block\Repeater;

#[Block('wrapper', title: 'Wrapper', category: BlockCategory::Design, excerptWrapper: true)]
final class Wrapper
{
    public function render(Repeater $repeater): string
    {
        return '';
    }
}
