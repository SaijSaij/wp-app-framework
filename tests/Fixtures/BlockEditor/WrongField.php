<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\BlockEditor;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;
use WebCodingCat\WpAppFramework\Editor\EditorTemplateFunctions;

#[Block('wrong-field', title: 'Wrong field', category: BlockCategory::Text)]
final readonly class WrongField
{
    public function __construct(private EditorTemplateFunctions $editor) {}

    public function render(): string
    {
        return '<p' . $this->editor->field('missing') . '></p>';
    }
}
