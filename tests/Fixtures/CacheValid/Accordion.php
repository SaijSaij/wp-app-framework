<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\CacheValid;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;
use WebCodingCat\WpAppFramework\Block\BlockTemplateFunctions;
use WebCodingCat\WpAppFramework\Cache\Cache;

/** A cached block with a view script (Accordion.view.js). */
#[Block('accordion', title: 'Accordion', category: BlockCategory::Design)]
#[Cache(ttl: 60)]
final class Accordion
{
    public function __construct(private readonly BlockTemplateFunctions $functions) {}

    public function render(): string
    {
        return sprintf('<div %s></div>', $this->functions->blockWrapperAttributes());
    }
}
