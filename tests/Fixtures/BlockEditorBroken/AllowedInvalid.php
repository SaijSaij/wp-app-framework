<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\BlockEditorBroken;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;

#[Block('allowed-invalid', title: 'Allowed invalid', category: BlockCategory::Design, allowedBlocks: [''])]
final class AllowedInvalid
{
    public function render(string $content): string
    {
        return $content;
    }
}
