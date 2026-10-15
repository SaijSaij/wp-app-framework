<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Value;

use Attribute;

/**
 * A reference to terms: on `?int` the ID of one term (null without), on
 * `array` a list of term IDs in a fixed order. A term that no longer exists or
 * belongs to another taxonomy becomes null (or is left out of the list).
 *
 * @api Attributes/Values
 */
#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
final readonly class TermReference
{
    /**
     * @param list<string>|null $taxonomies Taxonomies to choose from (slugs, e.g. ["category"]); null for all viewable taxonomies.
     */
    public function __construct(public ?array $taxonomies = null) {}
}
