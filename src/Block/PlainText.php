<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Block;

use Attribute;

/**
 * An inline text field without markup. Allowed on strings; the attribute gets
 * `role: content`. Templates print it with Twig's autoescaping, a multiline
 * field with `|nl2br`.
 */
#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
final readonly class PlainText
{
    /**
     * @param bool $inspector Edit the field in the block inspector instead of the canvas; the template
     *                        then prints it without field(), and changing it re-renders the block.
     * @param bool $excerpt   Include the value in automatic excerpts; the block is then added to
     *                        `excerpt_allowed_blocks`. Not allowed in blocks with inner blocks.
     * @param bool $multiline Allow line breaks: Enter inserts a line break in the canvas, the inspector
     *                        and forms show a text area. The value contains "\n"; print it with `|nl2br`.
     */
    public function __construct(public bool $inspector = false, public bool $excerpt = false, public bool $multiline = false) {}
}
