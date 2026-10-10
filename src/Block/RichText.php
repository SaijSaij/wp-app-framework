<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Block;

use Attribute;

/**
 * An inline field with HTML. Allowed on strings; the attribute gets
 * `role: content`. The framework does not restrict the markup: templates print
 * the value exactly as stored (`|raw`). WordPress filters it with wp_kses when
 * a user without `unfiltered_html` saves the post.
 *
 * With `postContent: true` the value is not stored in the block comment but as
 * the block's inner HTML in the post content (inline HTML without wrapping
 * element, `source: html`); the framework passes it to the attribute class like
 * any other value. Allowed once per block, on a parameter of the block
 * attribute class itself, in blocks without inner blocks and with the default
 * `''`.
 */
#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
final readonly class RichText
{
    /**
     * @param list<string>|null $allowedFormats Formats the editor offers, e.g. ["core/bold", "core/italic"];
     *                                          null allows all registered formats, [] none.
     * @param bool              $inspector Edit the field in the block inspector instead of the canvas; the template
     *                                          then prints it without field(), and changing it re-renders the block.
     * @param bool              $postContent Store the value as inline HTML in the post content instead of the block comment.
     * @param bool              $excerpt     Include the value in automatic excerpts; the block is then added to
     *                                       `excerpt_allowed_blocks`. Not allowed in blocks with inner blocks.
     */
    public function __construct(
        public ?array $allowedFormats = null,
        public bool $inspector = false,
        public bool $postContent = false,
        public bool $excerpt = false,
    ) {}
}
