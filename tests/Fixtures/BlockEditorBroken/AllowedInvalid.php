<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\BlockEditorBroken;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;
use WebCodingCat\WpAppFramework\Block\InnerBlocks;

#[Block('allowed-invalid', title: 'Allowed invalid', category: BlockCategory::Design)]
#[InnerBlocks(allowed: [''])]
final class AllowedInvalid
{
    public function render(string $content): string
    {
        return $content;
    }
}
