<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\BlockValid;

use WebCodingCat\WpAppFramework\Block\Align;
use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;
use WebCodingCat\WpAppFramework\Block\Supports;
use WP_Block;

#[Block('hero', title: 'Hero', category: BlockCategory::Design, icon: 'cover-image', description: 'A hero.', keywords: ['banner'])]
#[Supports(anchor: true, align: [Align::Wide, Align::Full], multiple: false)]
final class Hero
{
    public function __construct(private readonly Formatter $formatter) {}

    public function render(string $content, HeroAttributes $attrs, WP_Block $block): string
    {
        return $this->formatter->format(implode('|', [$attrs->topline, $attrs->gradient->value, count($attrs->buttons), $content, $block->name]));
    }
}
