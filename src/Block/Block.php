<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Block;

use Attribute;

/**
 * Declares a server-side rendered block. The class is a service with a public
 * `render()` method returning the block's HTML; its parameters are matched by
 * type: the block attribute class, `string` (the rendered inner blocks) and
 * `WP_Block`, besides values of ancestors marked with `#[Context]`.
 *
 * @api Attributes/Blocks
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Block
{
    /**
     * @param string       $name        Block name without namespace, e.g. "hero"; registered in the namespace of the
     *                                  theme or plugin (`extra.wp-app-framework.namespace`), e.g. "sections/hero".
     * @param string       $title       Title of the block in the inserter and the block inspector.
     * @param BlockCategory|CustomBlockCategory $category A category of WordPress or a case of an enum of the theme
     *                                  or plugin that implements CustomBlockCategory.
     * @param string       $icon        Name of a Dashicon, e.g. `cover-image`; `''` for the default icon.
     * @param string       $description Description shown in the inserter and the block inspector.
     * @param list<string> $keywords    Search terms for the inserter.
     * @param list<string> $usesContext Context values of other blocks the block reads from WP_Block::$context, e.g.
     *                                  "postId"; values of blocks of the same theme or plugin are read with
     *                                  #[Context] parameters of render().
     * @param bool         $excerptWrapper Add the block to `excerpt_allowed_wrapper_blocks`: automatic excerpts
     *                                  include its allowed inner blocks, as for core/group. Requires render() to
     *                                  accept string $content.
     * @param list<class-string|CoreBlock|string>|null $parent   The block can only be inserted directly into these
     *                                  blocks: block classes of the same theme or plugin, core blocks, other blocks
     *                                  by full name; null for anywhere.
     * @param list<class-string|CoreBlock|string>|null $ancestor The block can only be inserted somewhere inside these
     *                                  blocks; same values as $parent.
     */
    public function __construct(
        public string $name,
        public string $title,
        public BlockCategory|CustomBlockCategory $category,
        public string $icon = '',
        public string $description = '',
        public array $keywords = [],
        public array $usesContext = [],
        public bool $excerptWrapper = false,
        public ?array $parent = null,
        public ?array $ancestor = null,
    ) {}
}
