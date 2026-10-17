<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\LinkValid;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;

#[Block('teaser', title: 'Teaser', category: BlockCategory::Design)]
final class Teaser
{
    public function render(TeaserAttributes $attrs): string
    {
        return $attrs->cta->url;
    }
}
