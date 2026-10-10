<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Block;

use Attribute;

/**
 * An inline text field without markup. Allowed on strings; the attribute gets
 * `role: content`. Templates print it with Twig's autoescaping.
 */
#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
final readonly class PlainText
{
    /**
     * @param bool $inspector Edit the field in the block inspector instead of the canvas; the template
     *                        then prints it without field(), and changing it re-renders the block.
     */
    public function __construct(public bool $inspector = false) {}
}
