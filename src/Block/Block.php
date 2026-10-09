<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Block;

use Attribute;

/**
 * Declares a server-side rendered block. The class is a service with a public
 * `render()` method returning the block's HTML; its parameters are matched by
 * type: the block attribute class, `string` (the rendered inner blocks) and
 * `WP_Block`.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Block
{
    /**
     * @param string       $name        Block name, e.g. "sections/hero".
     * @param list<string> $keywords    Search terms for the inserter.
     * @param list<string> $usesContext Context values the block reads from WP_Block::$context, e.g. "postId".
     * @param list<string>|null $allowedBlocks Blocks that may be inserted as inner blocks, e.g. ["sections/hero"];
     *                                         null allows all. Requires render() to accept string $content.
     */
    public function __construct(
        public string $name,
        public string $title,
        public BlockCategory $category,
        public string $icon = '',
        public string $description = '',
        public array $keywords = [],
        public array $usesContext = [],
        public ?array $allowedBlocks = null,
    ) {
    }
}
