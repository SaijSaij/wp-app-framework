<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Block;

use Attribute;

/**
 * Marks content that is not inline text, e.g. an image ID or a link, so the
 * attribute gets `role: content` and stays editable in contentOnly mode.
 */
#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
final readonly class Content
{
}
