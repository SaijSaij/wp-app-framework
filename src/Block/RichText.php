<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Block;

use Attribute;

/**
 * An inline field with HTML. Allowed on strings; the attribute gets
 * `role: content`. The framework does not restrict the markup: templates print
 * the value exactly as stored (`|raw`). WordPress filters it with wp_kses when
 * a user without `unfiltered_html` saves the post.
 */
#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
final readonly class RichText
{
    /**
     * @param list<string>|null $allowedFormats Formats the editor offers, e.g. ["core/bold", "core/italic"];
     *                                          null allows all registered formats, [] none.
     * @param bool              $inspector Edit the field in the block inspector instead of the canvas; the template
     *                                          then prints it without field(), and changing it re-renders the block.
     */
    public function __construct(public ?array $allowedFormats = null, public bool $inspector = false)
    {
    }
}
