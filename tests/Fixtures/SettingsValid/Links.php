<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\SettingsValid;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;

/**
 * A block with the value class Link of a settings group: its image is edited in the canvas here.
 */
#[Block('links', title: 'Links', category: BlockCategory::Design)]
final class Links
{
    public function render(LinksAttributes $attrs): string
    {
        return (string) count($attrs->links);
    }
}
