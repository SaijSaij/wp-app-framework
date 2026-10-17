<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Value;

use Attribute;

/**
 * The label of a value in the editor: the inspector control, or the
 * placeholder of an inline field. Without it, the label is derived from the
 * parameter name ("imageId" becomes "Image id").
 *
 * @api Attributes/Values
 */
#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
final readonly class Label
{
    /**
     * @param string $label The label, e.g. `Background image`.
     */
    public function __construct(public string $label) {}
}
