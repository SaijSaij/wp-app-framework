<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Block;

/**
 * Where the editor offers a block variation (`#[Variation(scope)]`).
 */
enum VariationScope: string
{
    /** As an item of its own in the inserter. */
    case Inserter = 'inserter';
    /** In the placeholder of the block, like the layouts of core/columns. */
    case Block = 'block';
    /** In the block toolbar, to switch between the variations of a block. */
    case Transform = 'transform';
}
