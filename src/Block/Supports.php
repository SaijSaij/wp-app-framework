<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Block;

use Attribute;

/**
 * Block supports of a `#[Block]` class. The defaults are those of WordPress;
 * only differing values are registered. `html` is always disabled, because
 * blocks store no markup.
 *
 * @api Attributes/Blocks
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Supports
{
    /**
     * @param bool        $contentRole     Container blocks: allows inserting inner blocks in contentOnly mode.
     * @param bool        $anchor          An HTML anchor (`id`) editors can set in the advanced panel.
     * @param list<Align> $align           Allowed alignments; empty disables the support.
     * @param bool        $customClassName The field "Additional CSS class(es)" in the advanced panel.
     * @param bool        $multiple        False allows the block only once per post.
     * @param bool        $reusable        False prevents turning the block into a synced pattern.
     * @param bool        $inserter        False hides the block from the inserter; it can still be used in
     *                                     templates, patterns and inner block templates.
     * @param bool        $lock            False hides the lock controls of the block.
     * @param bool        $renaming        False prevents editors from renaming the block in the list view.
     * @param bool        $customCSS       Free CSS per block instance for users with `edit_css`, stored in the
     *                                     `style` attribute; the theme decides whether it allows it.
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
        public bool $customCSS = true,
    ) {}
}
