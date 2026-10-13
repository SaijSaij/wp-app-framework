<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Block;

use Attribute;

/**
 * Marks an attribute of a block attribute class whose value the block passes
 * to all its inner blocks, nested ones included. A block reads it with a
 * render() parameter marked `#[Context(Block::class, 'attribute')]`.
 */
#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
final readonly class ProvidesContext {}
