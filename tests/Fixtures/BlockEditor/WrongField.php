<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\BlockEditor;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;
use Saij\WpAppFramework\Editor\EditorTemplateFunctions;

#[Block('wrong-field', title: 'Wrong field', category: BlockCategory::Text)]
final readonly class WrongField
{
    public function __construct(private EditorTemplateFunctions $editor) {}

    public function render(): string
    {
        return '<p' . $this->editor->field('missing') . '></p>';
    }
}
