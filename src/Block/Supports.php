<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Block;

use Attribute;

/**
 * Block supports of a `#[Block]` class. The defaults are those of WordPress;
 * only differing values are registered. `html` is always disabled, because
 * blocks store no markup.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Supports
{
    /**
     * @param bool        $contentRole Container blocks: allows inserting inner blocks in contentOnly mode.
     * @param list<Align> $align       Allowed alignments; empty disables the support.
     */
    public function __construct(
        public bool $contentRole = false,
        public bool $anchor = false,
        public array $align = [],
        public bool $customClassName = true,
        public bool $multiple = true,
        public bool $reusable = true,
        public bool $inserter = true,
        public bool $lock = true,
        public bool $renaming = true,
    ) {
    }
}
