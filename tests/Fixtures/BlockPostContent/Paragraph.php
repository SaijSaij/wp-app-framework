<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\BlockPostContent;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;

#[Block('paragraph', title: 'Paragraph', category: BlockCategory::Text)]
final readonly class Paragraph
{
    public function render(ParagraphAttributes $attrs): string
    {
        return '<p class="' . ($attrs->lead ? 'lead' : 'plain') . '">' . $attrs->text . '</p>';
    }
}
