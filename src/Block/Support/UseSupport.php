<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Block\Support;

use Attribute;

/**
 * Adds a block support declared with `#[BlockSupport]` to a `#[Block]`
 * class. The block's template must print its root element with
 * `block_wrapper_attributes()`, which adds the class names of the support.
 *
 * @api Attributes/Blocks
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class UseSupport
{
    /**
     * @param class-string $support A class with `#[BlockSupport]` of the same theme or plugin.
     * @param mixed        $default Default of the attribute for this block, of the type of the support's value;
     *                              without it, the default of the support applies.
     */
    public function __construct(
        public string $support,
        public mixed $default = null,
    ) {}
}
