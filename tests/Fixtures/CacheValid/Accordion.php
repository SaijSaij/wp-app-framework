<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\CacheValid;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;
use Saij\WpAppFramework\Block\BlockTemplateFunctions;
use Saij\WpAppFramework\Cache\Cache;

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
