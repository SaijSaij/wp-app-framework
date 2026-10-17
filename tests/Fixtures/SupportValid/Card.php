<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\SupportValid;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;
use WebCodingCat\WpAppFramework\Block\BlockTemplateFunctions;
use WebCodingCat\WpAppFramework\Block\Support\UseSupport;

#[Block('card', title: 'Card', category: BlockCategory::Design)]
#[UseSupport(Surface::class, default: Tone::Dark)]
#[UseSupport(Framed::class)]
#[UseSupport(Accent::class)]
final class Card
{
    public function __construct(private readonly BlockTemplateFunctions $functions) {}

    public function render(CardAttributes $attrs): string
    {
        return sprintf('<div %s>%s</div>', $this->functions->blockWrapperAttributes(['class' => 'card']), $attrs->title);
    }
}
