<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Block;

use Attribute;

/**
 * An image from the media library, stored as its attachment ID. Allowed on
 * `?int`; the attribute gets `role: content`. In the editor the template marks
 * the image with `field()`, and the canvas offers to select or replace it.
 */
#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
final readonly class Image
{
    /**
     * @param bool $inspector Edit the field in the block inspector instead of the canvas; the template
     *                        then prints it without field(), and changing it re-renders the block.
     */
    public function __construct(public bool $inspector = false)
    {
    }
}
