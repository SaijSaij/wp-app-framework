<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\BlockEditor;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;
use Saij\WpAppFramework\Block\InnerBlocks;
use Saij\WpAppFramework\Editor\EditorTemplateFunctions;

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
