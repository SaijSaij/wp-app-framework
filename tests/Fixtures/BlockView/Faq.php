<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\BlockView;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;
use WebCodingCat\WpAppFramework\Block\BlockTemplateFunctions;
use WebCodingCat\WpAppFramework\Block\Support\UseSupport;

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
