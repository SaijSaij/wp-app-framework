<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\BlockView;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;
use WebCodingCat\WpAppFramework\Block\BlockTemplateFunctions;
use WebCodingCat\WpAppFramework\Block\Support\UseSupport;

/** No view script of its own, only the one of the Reveal support. */
#[Block('note', title: 'Note', category: BlockCategory::Text)]
#[UseSupport(Reveal::class, default: true)]
final class Note
{
    public function __construct(private readonly BlockTemplateFunctions $functions) {}

    public function render(): string
    {
        return sprintf('<p %s>Note</p>', $this->functions->blockWrapperAttributes());
    }
}
