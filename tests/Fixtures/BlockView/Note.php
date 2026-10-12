<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\BlockView;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;
use Saij\WpAppFramework\Block\BlockTemplateFunctions;
use Saij\WpAppFramework\Block\Support\UseSupport;

/** No view script of its own, only the one of the Reveal support. */
#[Block('fixture/note', title: 'Note', category: BlockCategory::Text)]
#[UseSupport(Reveal::class, default: true)]
final class Note
{
    public function __construct(private readonly BlockTemplateFunctions $functions) {}

    public function render(): string
    {
        return sprintf('<p %s>Note</p>', $this->functions->blockWrapperAttributes());
    }
}
