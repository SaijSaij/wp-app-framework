<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Block;

use Saij\WpAppFramework\Twig\TemplateFunction;

/**
 * Twig functions for block templates.
 */
final class BlockTemplateFunctions
{
    /**
     * The attributes of the block's root element from its supports (class
     * names, anchor, alignment), as returned by get_block_wrapper_attributes().
     * Empty outside of rendering a block.
     *
     * @param array<string, string> $extra Additional attributes; `class` and `style` are merged.
     */
    #[TemplateFunction('block_wrapper_attributes', html: true)]
    public function blockWrapperAttributes(array $extra = []): string
    {
        return get_block_wrapper_attributes($extra);
    }

    /**
     * An image from the media library, as returned by wp_get_attachment_image().
     * Empty if the ID is null or no image exists.
     *
     * @param array<string, string> $attributes Additional attributes of the img element.
     */
    #[TemplateFunction('attachment_image', html: true)]
    public function attachmentImage(?int $id, string $size = 'full', array $attributes = []): string
    {
        return $id === null ? '' : wp_get_attachment_image($id, $size, false, $attributes);
    }
}
