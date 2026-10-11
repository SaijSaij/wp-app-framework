<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\SettingsValid;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;

/**
 * A block with the value class Link of a settings group: its image is edited in the canvas here.
 */
#[Block('fixture/links', title: 'Links', category: BlockCategory::Design)]
final class Links
{
    public function render(LinksAttributes $attrs): string
    {
        return (string) count($attrs->links);
    }
}
