<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Block;

use Attribute;

/**
 * Marks a parameter of render() that receives the value of an attribute an
 * ancestor block provides (`#[ProvidesContext]`). The parameter has the type
 * of that attribute; it keeps its default (or null) if no ancestor provides
 * the value.
 *
 * @api Attributes/Blocks
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class Context
{
    /**
     * @param class-string $block     The providing block of the same theme or plugin.
     * @param string       $attribute Name of the attribute marked with #[ProvidesContext] in its attribute class.
     */
    public function __construct(
        public string $block,
        public string $attribute,
    ) {}
}
