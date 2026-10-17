<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\BlockEditor;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;
use WebCodingCat\WpAppFramework\Block\InnerBlocks;
use WebCodingCat\WpAppFramework\Editor\EditorTemplateFunctions;

#[Block('container', title: 'Container', category: BlockCategory::Design)]
#[InnerBlocks(allowed: [Teaser::class])]
final readonly class Container
{
    public function __construct(private EditorTemplateFunctions $editor) {}

    public function render(string $content): string
    {
        return '<section' . $this->editor->innerBlocks() . '>' . $content . '</section>';
    }
}
