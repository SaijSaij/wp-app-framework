<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\ApiReference\Valid;

use Attribute;

/**
 * Declares a widget.
 *
 * @see guides/blocks.md#variations
 *
 * @api Attributes/Blocks
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class Widget
{
    /**
     * @param string     $name Name of the widget.
     * @param list<Size> $sizes Sizes it comes in.
     */
    public function __construct(
        public string $name,
        public array $sizes = [Size::Small],
    ) {}
}
