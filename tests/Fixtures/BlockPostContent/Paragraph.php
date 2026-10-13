<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\BlockPostContent;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;

#[Block('paragraph', title: 'Paragraph', category: BlockCategory::Text)]
final readonly class Paragraph
{
    public function render(ParagraphAttributes $attrs): string
    {
        return '<p class="' . ($attrs->lead ? 'lead' : 'plain') . '">' . $attrs->text . '</p>';
    }
}
