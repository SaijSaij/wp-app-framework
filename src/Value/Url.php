<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Value;

use Attribute;

/**
 * Marks a string as URL: its schema gets `format: uri`, so WordPress sanitizes
 * it with sanitize_url() and removes disallowed protocols such as `javascript:`.
 */
#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
final readonly class Url
{
}
