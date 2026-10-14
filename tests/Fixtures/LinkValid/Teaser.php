<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\LinkValid;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;

#[Block('teaser', title: 'Teaser', category: BlockCategory::Design)]
final class Teaser
{
    public function render(TeaserAttributes $attrs): string
    {
        return $attrs->cta->url;
    }
}
