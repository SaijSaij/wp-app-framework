<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\BlockView;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;
use Saij\WpAppFramework\Block\BlockTemplateFunctions;
use Saij\WpAppFramework\Block\Support\UseSupport;

/** A view script of its own (Faq.view.js) and the one of the Reveal support. */
#[Block('faq', title: 'FAQ', category: BlockCategory::Text)]
#[UseSupport(Reveal::class)]
final class Faq
{
    public function __construct(private readonly BlockTemplateFunctions $functions) {}

    public function render(FaqAttributes $attrs): string
    {
        return sprintf('<div %s>%s</div>', $this->functions->blockWrapperAttributes(['class' => 'faq']), esc_html($attrs->question));
    }
}
